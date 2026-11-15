# Integration events (outbox)

The host application usually needs to react to the workflow: tell an operator that a declaration needs a manual check,
mark a payslip as declared, know that the mandate is now effective. The package publishes these facts as **integration
events**: public, scalar classes (`TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\*`,
[ADR 0012](adr/0012-integration-events.md)) versioned independently of the domain model, written to an **outbox** in
the same transaction as the state change, then delivered by a **relay**.

Guarantees:

- **Durable**: an event exists if and only if its state change was committed (a rolled back use case writes none).
- **At least once**: an event is marked as dispatched only once the sink accepted it; a crash between the delivery and
  the mark delivers it again. Deduplicate on `eventId`: the same fact always has the same id.
- **Ordered per record**: the events of one employer account, mandate or declaration record are delivered in recording
  order; when one fails, the next events of the same record wait for the next run (the other records go on).

The domain events (`Domain\...\Event\*`, dispatched after the commit) are still published: they are an internal
mechanism and may change with the domain model. Integrate with the integration events.

Payloads carry identifiers, states and codes, never names, birth dates, NIR, bank data nor a URSSAF description.

## Catalogue

Common properties: `eventId`, `occurredAt` (ISO 8601, UTC), `tenantId`, `aggregateId` (employer account id, mandate id
or declaration record id), `externalReference` (your reference of the employer or the declaration); `type()` and
`version()` (1 for every type today; a change of payload bumps it).

| Class | `type()` | When | Specific properties |
|---|---|---|---|
| `EmployerRegistered` | `urssaf.cesu.employer.registered` | an employer is tracked: registered (010) or imported | `cesuNumber`, `origin` (`registered`, `imported`) |
| `EmployerStatusChanged` | `urssaf.cesu.employer.status_changed` | a 020 refresh, or a stage change after 030 / 031 | `cesuNumber`, `previousStage`, `stage`, `mandatePresent`, `aiciActive`, `onlineAccountState`, `sepaMandateState` |
| `MandateNotified` | `urssaf.cesu.mandate.notified` | the mandate was notified (030) | `cesuNumber` |
| `MandateConfirmed` | `urssaf.cesu.mandate.confirmed` | 020 reports the mandate present: the prerequisite of 050 | `cesuNumber` |
| `MandateCancelled` | `urssaf.cesu.mandate.cancelled` | the mandate was cancelled (031) | `cesuNumber` |
| `DeclarationDeclared` | `urssaf.cesu.declaration.declared` | URSSAF recorded the declaration (050), or an operator resolved an unknown outcome | `declarationId` |
| `DeclarationRejected` | `urssaf.cesu.declaration.rejected` | URSSAF refused the declaration (final) | `errorCodes` |
| `DeclarationOutcomeUnknown` | `urssaf.cesu.declaration.outcome_unknown` | the outcome is unknown, manual review needed ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)) | `reason` (`may_have_reached_urssaf`, `stale_submission`) |
| `DeclarationAlreadyDeclared` | `urssaf.cesu.declaration.already_declared` | URSSAF answered that the declaration already exists (final) | none |

Notes:

- `EmployerStatusChanged` raised by a local change (030 / 031) has `mandatePresent`, `aiciActive`,
  `onlineAccountState` and `sepaMandateState` set to `null`: there is no 020 answer. The state values are the raw wire
  values (unknown ones are kept). The Cesu+ state and its blocking reason are pair-relative free text and are not part
  of the event; read them with `GetEmployerAccount`.
- `DeclarationDeclared` carries `idDeclaration` only; the amounts are read with `GetDeclaration`.
- No event exists for requeued declarations, failed submissions and rejected credentials (their domain events carry no
  external reference): monitor them with `GetWorkflowOverview` (`needingAttention()`, see
  [workflow.md](workflow.md#monitoring)) and list them with `FindDeclarations::inState()`.

## Wiring

Give an outbox to the `UnitOfWork` of the handlers, and run the relay periodically:

```php
$outbox = new InMemoryOutbox();   // or your Application\Port\Outbox, written in the transaction of the repositories
$unitOfWork = new UnitOfWork(new ImmediateTransactionManager(), new NullEventPublisher(), new IgnoredFailures(), $outbox);

// cron
$report = (new RelayOutboxHandler($outbox, new Psr14IntegrationEventSink($dispatcher), $clock, $policy))(new RelayOutbox(null));
(new PurgeOutboxHandler($outbox, $clock))(new PurgeOutbox(new DateInterval('P30D')));
```

`RelayOutbox` returns a `RelayReport` (`dispatched`, `failed`, `deferred`). Runs must not overlap. With the PSR-14 sink,
a listener that throws keeps the event in the outbox, delivered again on the next run.

The outbox must share the transaction of the repositories so that the events join it. `InMemoryOutbox` and
`ImmediateTransactionManager` are for tests and prototypes: not transactional, lost at the end of the process.

Handle the events idempotently:

```php
final readonly class MarkDeclarationDeclared
{
    public function __construct(private PayslipRepository $payslips) {}

    public function __invoke(DeclarationDeclared $event): void
    {
        // The same event may be delivered twice: deduplicate on $event->eventId.
        $this->payslips->byReference($event->externalReference)->markDeclared($event->declarationId, $event->eventId);
    }
}
```

The relay stores events with `IntegrationEventCodec::encode()` / `decode()` (PHP serialization restricted to the nine
event classes) when the outbox needs a string.
