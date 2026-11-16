# Without the Symfony bundle

`TheBeardedBear\UrssafTierceDeclarationCesu\Standalone\UrssafTierceDeclarationCesuBuilder` builds every use case of the
package from a PSR-18 transport (`HttpTransport`), a `CredentialsProvider` and the `ApiConfiguration`; `build()` returns
`Standalone\UrssafTierceDeclarationCesu`, whose methods return the 23 handlers (`registerEmployer()`,
`importEmployer()`, `registerMandate()`, `cancelMandate()`, `forgetEmployer()`, `syncEmployerStatus()`, `syncEmployerStatuses()`,
`syncAllEmployerStatuses()`, `activateCesuPlus()`, `activateAici()`, `declareSalary()`,
`resolveDeclarationOutcome()`, `reconcileStaleDeclarations()`, `requeueAfterInvalidCredentials()`, `relayOutbox()`,
`purgeOutbox()`, `verifyEmployer()`, `verifyEmployee()`, `getEmployerAccount()`, `estimateContributions()`,
`getDeclaration()`, `findDeclarations()`, `getWorkflowOverview()`) and the gateway (`gateway()`). Each accessor returns a
new, stateless handler.

```php
$urssaf = UrssafTierceDeclarationCesuBuilder::create(
    new HttpTransport($psr18Client, $psr17Factory, $psr17Factory),
    new InMemoryCredentialsProvider(['tenant-1' => Credentials::of($clientId, $clientSecret)]),
    ApiConfiguration::forEnvironment(Environment::Sandbox),
)->build();
```

Every `with...()` method returns a new builder:

| Method | Default | Use |
|---|---|---|
| `withTokenStore(TokenStore)` | in-memory | A store shared by every process that calls URSSAF (`Psr6TokenStore`, `Psr16TokenStore`). |
| `withClock(Clock)` | UTC system clock | Also used by the HTTP gateway. |
| `withIdGenerator(IdGenerator)` | random UUIDs | Record ids and request ids. |
| `withEventPublisher(EventPublisher)` | none | Domain events after each commit, e.g. `new Psr14EventPublisher($dispatcher)`. |
| `withLogger(LoggerInterface)` | none | Logs of the HTTP gateway and of the event publication failures (never personal data, [ADR 0007](adr/0007-personal-data.md)). |
| `withRateLimiter(RateLimiter)` | `SlidingWindowRateLimiter` | The HTTP gateway enforces the URSSAF quota (2 500 calls per hour per tenant, [ADR 0011](adr/0011-quotas-and-rate-limit.md)) in the process; `NoopRateLimiter` disables it. |
| `withPolicy(WorkflowPolicy)` | defaults | Page sizes, stale submission delay, attempts, business time zone, mandate guard, defaults of the erasure opt-ins... |
| `withDoctrine(EntityManagerInterface)` | in-memory repositories | Doctrine repositories, transaction manager and statistics on a **dedicated** entity manager ([doctrine.md](doctrine.md)); the outbox stays separate: `withOutbox(new DoctrineOutbox($entityManager), $sink)`. |
| `withRepositories($accounts, $mandates, $declarations, $transactions, ?$statistics)` | in-memory | Your own persistence; without statistics `getWorkflowOverview()` throws. |
| `withOutbox(Outbox, IntegrationEventSink)` | no outbox | Integration events (see [integration-events.md](integration-events.md)); `relayOutbox()` needs it. |
| `withGateway(CesuGateway)` | HTTP gateway | For instance the `Testing\SimulatedCesuGateway` in tests ([testing.md](testing.md)). |

The defaults (in-memory repositories, statistics and token store) are meant for tests and prototypes. In production,
use `withDoctrine()` or give your own repositories, and a token store shared by every process.

## Scheduling

Run the polling and recovery use cases yourself (cron, worker), like the console commands of the
[Symfony bundle](symfony.md#console-commands) (`urssaf:cesu:employers:sync-status`, `urssaf:cesu:declarations:reconcile-stale`,
`urssaf:cesu:requeue-invalid-credentials`, `urssaf:cesu:outbox:relay`, `urssaf:cesu:outbox:purge`):

- `syncAllEmployerStatuses()` (method 020) to follow the employer accounts and their mandates;
- `reconcileStaleDeclarations()` to mark declarations stuck in `submitting` as `outcome_unknown`;
- `requeueAfterInvalidCredentials()` per tenant once the credentials are fixed, then send the requeued declarations
  again with `declareSalary()`: the package does not keep the payload ([ADR 0007](adr/0007-personal-data.md));
- `relayOutbox()` and `purgeOutbox()` when the outbox is used.

Two runs of the same use case must not overlap (use a lock of your own); the atomic claim keeps `declareSalary()` safe.

## Unknown outcomes

A declaration whose answer was lost is `outcome_unknown` and is never resent ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)):
check it on cesu.urssaf.fr, then call `resolveDeclarationOutcome()` (`asDeclared` with the URSSAF declaration id, or
`asNotReceived`). See [workflow.md](workflow.md).

## Manual wiring

The builder only calls public constructors: wire the classes yourself when you need another combination.

```php
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory;

$gateway = UrssafGatewayFactory::createWith($transport, $credentials, $tokenStore, $configuration, GatewayOptions::defaults()->withClock($clock));
$unitOfWork = new UnitOfWork(new ImmediateTransactionManager(), $eventPublisher, new PsrLoggerFailureReporter($logger), $outbox);

$registerEmployer = new RegisterEmployerHandler($accounts, $gateway, $ids, $clock, $unitOfWork);
$declareSalary = new DeclareSalaryHandler(
    $declarations,
    new DeclarationSubmitter($declarations, $gateway, $clock, $unitOfWork, $policy),
    new MandateGuard($mandates, $accounts, $policy),
    $ids,
    $clock,
    $unitOfWork,
    $policy,
);
```

The same wiring on Doctrine, without a framework (the entity manager must be dedicated to the package, see
[doctrine.md](doctrine.md)):

```php
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineOutbox;

DoctrineIntegration::registerTypes();   // and DoctrineIntegration::mappingDriver() in the ORM configuration

$unitOfWork = new UnitOfWork(new DoctrineTransactionManager($entityManager), $eventPublisher, new PsrLoggerFailureReporter($logger), new DoctrineOutbox($entityManager));
$accounts = new DoctrineEmployerAccountRepository($entityManager);
$mandates = new DoctrineMandateRepository($entityManager);
$declarations = new DoctrineDeclarationRecordRepository($entityManager);
```
