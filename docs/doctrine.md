# Doctrine persistence

The Doctrine bridge (`TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine`) persists the three aggregates
(employer accounts, mandates, declaration records) with Doctrine ORM 3 / DBAL 4 (`composer.json` declares a `conflict`
with `doctrine/orm < 3.5.3`, `doctrine/dbal < 4.0` and `doctrine/doctrine-bundle < 2.19`). The domain classes carry no
Doctrine metadata: the mapping is a set of XML files in `src/Bridge/Doctrine/Resources/mapping`. The decisions that
differ from the other URSSAF package are in [ADR 0014](adr/0014-doctrine-bridge.md).

Classes: `DoctrineEmployerAccountRepository`, `DoctrineMandateRepository`, `DoctrineDeclarationRecordRepository`
(`Bridge\Doctrine\Repository`, implementing the domain repository interfaces), `DoctrineWorkflowStatistics`,
`DoctrineOutbox`, `DoctrineTransactionManager` (implements `TransactionManager`), `DoctrineIntegration` (types, mapping
driver, bundle configuration), plus the DBAL types (`Bridge\Doctrine\Type`) and JSON serializers.

## Tables

All tables are prefixed with `urssaf_cesu_` (`DoctrineIntegration::TABLE_PREFIX`), and the DBAL types, indexes and
constraints carry the same prefix: the package can share a database, and even an entity manager, with the other URSSAF
package (`urssaf_tps_*`) without a collision.

| Table | Entity | Content |
|---|---|---|
| `urssaf_cesu_employer_accounts` | `Domain\Employer\EmployerAccount` | Tracked employer: id, tenant, external reference, Cesu number, birth date, registration origin, creation instant, tracking (stage, last status as JSON, employee read with it, last check and update instants). |
| `urssaf_cesu_mandates` | `Domain\Mandate\Mandate` | Mandate of an employer: id, tenant, external reference, Cesu number, birth date, state, timeline (notified, confirmed, cancelled, updated) and the last presence check (JSON). |
| `urssaf_cesu_declaration_records` | `Domain\Declaration\DeclarationRecord` | Declaration record: id, tenant, external reference, creation instant, snapshot of the declaration (employer and employee Cesu numbers, type, employment period, payment date, employment kind, fingerprint) and status (state, attempts, last attempt, URSSAF errors as JSON, `idDeclaration`, result as JSON, outcome-unknown reason, update instant). |
| `urssaf_cesu_active_mandates` | `Bridge\Doctrine\Entity\ActiveMandate` | Key table, see below. |
| `urssaf_cesu_outbox` | `Bridge\Doctrine\Entity\OutboxMessage` | Integration events waiting to be relayed (`DoctrineOutbox`, written with DBAL in the transaction of the state change). See [integration-events.md](integration-events.md). |

Unique constraints:

- `urssaf_cesu_employer_accounts`: `(tenant_id, cesu_number)` and `(tenant_id, external_reference)`.
- `urssaf_cesu_declaration_records`: `(tenant_id, external_reference)` and `(tenant_id, declaration_id)` (a null
  `declaration_id` never collides).
- `urssaf_cesu_mandates`: none (an employer can have several mandates over time, the external reference is the
  employer's one).

Every column that a query filters or sorts on is a real column of the aggregate's own table; the last status of an
employer, the last presence check of a mandate, the URSSAF errors and the declaration result are JSON columns (no query
filters on them, and Doctrine cannot hydrate a nullable embeddable).

### One active mandate per employer

"At most one non-cancelled mandate per (tenant, Cesu number)" ([ADR 0006](adr/0006-local-lifecycles.md)) is enforced by
the primary key `(tenant_id, cesu_number)` of `urssaf_cesu_active_mandates`: `DoctrineMandateRepository::save()` rewrites
the key row of the mandate with DBAL, in the transaction of the flush (the row is deleted when the mandate is
cancelled). Two concurrent notifications therefore cannot both succeed, on any platform (a partial unique index would
not exist on MySQL). The table is read-only for the ORM: never write it yourself.

## Registering types and mapping without Symfony

```php
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineIntegration;

DoctrineIntegration::registerTypes();                     // idempotent, global DBAL type registry
$configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());   // SimplifiedXmlDriver
```

`DoctrineIntegration::types()` returns the custom types by name (`urssaf_cesu_*`), `mappingDirectory()` the XML
directory, `MAPPING_NAMESPACE` the namespace prefix handled by the simplified driver, and
`doctrineBundleConfiguration()` the array that the Symfony bundle prepends to the `doctrine` extension.

Then build the persistence from your `EntityManagerInterface` (see [standalone.md](standalone.md)):

```php
$package = UrssafTierceDeclarationCesuBuilder::create($transport, $credentials, $configuration)
    ->withDoctrine($entityManager)
    ->withOutbox(new DoctrineOutbox($entityManager), $sink)   // optional
    ->build();
```

### Lazy loading and PHP 8.4

Doctrine ORM 3 loads lazy references either through generated proxies built on the lazy ghosts of `symfony/var-exporter`
6.4 / 7 (the only option before PHP 8.4, and the default), or through the native lazy objects of PHP 8.4 (ORM 3.4+). The
lazy ghosts are gone from `symfony/var-exporter` 8: with that version, and without native lazy objects, the ORM throws
"Symfony LazyGhost is not available" as soon as it builds its proxy factory.

Without the Symfony bundle, enable the native objects yourself on PHP 8.4+:

```php
if (DoctrineIntegration::nativeLazyObjectsAvailable()) {   // PHP >= 8.4 and an ORM that implements them
    $configuration->enableNativeLazyObjects(true);
}
```

`doctrineBundleConfiguration()` adds `orm.enable_native_lazy_objects: true` under the same condition
(`doctrineBundleConfigurationWith([...])` takes the ORM options explicitly); with the Symfony bundle this is done for
you, and your own configuration still wins.

The mapped entities (`EmployerAccount`, `Mandate`, `DeclarationRecord` and the two bridge entities) are not declared
`final` so that Doctrine can generate their lazy-loading proxies with any `auto_generate_proxy_classes` mode (including
pre-generation at cache warm-up); they are final by convention (`@final`) and must not be extended. Value objects are
embeddables and custom DBAL types (`DoctrineIntegration::types()`); enums are mapped as backed enums.

## Dedicated entity manager (strongly recommended)

The repositories `persist()` and then `flush()` the entity manager they are built with on **every** `save()`. A Doctrine
flush has no per-entity variant (`flush($entity)` was removed in ORM 3): it writes **all** the pending changes of the
unit of work, host entities included. If the package shares the entity manager of your application, a `save()` made by
the package would therefore also flush half-finished changes of your own entities (and a rollback of the package
transaction would roll them back too).

Use a **dedicated entity manager** (and connection) for the package tables, and point the bundle at it:

```yaml
doctrine:
    dbal:
        connections:
            default: { url: '%env(resolve:DATABASE_URL)%' }
            urssaf_cesu: { url: '%env(resolve:URSSAF_CESU_DATABASE_URL)%' }   # same database is fine, different connection
    orm:
        entity_managers:
            default: { connection: default, mappings: { App: { type: attribute, dir: '%kernel.project_dir%/src/Entity', prefix: App\Entity } } }
            urssaf_cesu: { connection: urssaf_cesu }                          # the bundle adds the package mapping to this one

urssaf_tierce_declaration_cesu:
    persistence: { driver: doctrine, entity_manager: urssaf_cesu }
```

With a non-default `persistence.entity_manager` the bundle attaches the package mapping to that entity manager only, so
the host entities and the package entities never share a unit of work. When you must share the entity manager, flush
your own changes before calling a package handler, and accept that the handler flushes whatever is pending. The
`database schema` check of `urssaf:cesu:check` warns when the `default` entity manager is used.

## Identity and concurrency

**Managed instances.** Unlike the in-memory repositories, which hand out copies, a read returns the instance managed by
the entity manager: two reads of the same record give the same object, and a change made to it is visible to every
holder before it is saved. Call `EntityManager::clear()` to forget the managed instances (the Symfony Messenger worker
does it between two messages when the entity manager is the default one of DoctrineBundle).

**Optimistic locking.** `EmployerAccount`, `Mandate` and `DeclarationRecord` carry a persistence concurrency token
(`version`, a native Doctrine version field: not a domain concept, no accessor) incremented by every update. When a
record was changed by another process (or another entity manager) since it was read, the flush is refused and the
repository throws an `InvariantViolation` with the code `employer_account.concurrent_modification`,
`mandate.concurrent_modification` or `declaration_record.concurrent_modification` (translated, see
[symfony.md](symfony.md)). Nothing was written: read the record again and redo the operation. The claim of a
declaration (see below) and any bulk update of these tables also increment the version.

The race it closes: a `DeclareSalary` call slower than `workflow.stale_submission_after` while the stale reconciliation
moves the record to `outcome_unknown`. The slow call loses: its outcome transaction is refused with
`declaration_record.concurrent_modification` and the record stays `outcome_unknown` for the operator
([workflow.md](workflow.md)).

**A failed flush closes the entity manager.** When a flush fails (a concurrent insert violated a unique constraint, a
lost optimistic lock, a database error), the ORM closes the entity manager: it cannot be used again. The repositories
still report the unique-constraint case as the domain code (`employer_account.duplicate_cesu_number`...), but the
caller of a long-running process must reset the entity manager (`ManagerRegistry::resetManager()`, or let the Messenger
worker do it). The package never resets it for you.

## Personal data (GDPR)

The tables hold the personal data that the workflow needs ([ADR 0007](adr/0007-personal-data.md)):

- the **employer birth date** (`urssaf_cesu_employer_accounts.birth_date` and `urssaf_cesu_mandates.birth_date`,
  required by methods 020, 030, 031, 050 and 060), under its own DBAL type `urssaf_cesu_birth_date` so that it can be
  encrypted alone;
- the **URSSAF error descriptions** of a rejected declaration (`urssaf_cesu_declaration_records.errors`), which may echo
  values that the host sent;
- the **amounts of the declaration result** (`urssaf_cesu_declaration_records.result`: gross and net taxable salary of
  the employee, contributions, debits), needed to answer `GetDeclaration`.

Never stored: IBAN, BIC, NIR, employee names, employee birth data, address, e-mail and phone number (a test asserts that no
column of the package tables looks like one). The Cesu numbers of the employer and the employee are stored. A conversion
error on a birth date or a JSON document never contains the stored value (DBAL's message receives `***`). The package
gives you the tools, the policy is yours:

1. **Retention.** Define how long an account, a mandate and a declaration record are kept after they are no longer
   needed, and run a scheduled job in your application that selects the expired records.
2. **Erasure by deletion.** `EmployerAccountRepository::delete(EmployerAccountId)` and
   `DeclarationRecordRepository::delete(DeclarationRecordId)` remove the row (no event, nothing happens when it does
   not exist). The mandate repository has no `delete()` yet: the use case that erases an employer with its mandates
   comes in a dedicated lot ([ADR 0013](adr/0013-symfony-bridge.md), deferred GDPR command). Until then, remove the
   rows of `urssaf_cesu_mandates` and `urssaf_cesu_active_mandates` of the employer with your own SQL. Nothing is erased
   at URSSAF: the data they hold is subject to their own retention rules.
3. **Encryption at rest.** The types are `final`: to encrypt a column, write a type of your own that wraps the package
   one and register it under the same name, after `DoctrineIntegration::registerTypes()`. Example for the employer
   birth date:

   ```php
   final class EncryptedBirthDateType extends Type
   {
       public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
       {
           // The mapping declares a date column: widen it to a string for the ciphertext.
           return $platform->getStringTypeDeclarationSQL(['length' => 255] + $column);
       }

       public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
       {
           $plain = (new BirthDateType())->convertToDatabaseValue($value, $platform);

           return $plain === null ? null : $this->cipher()->encrypt($plain);
       }

       public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
       {
           return (new BirthDateType())->convertToPHPValue($value === null ? null : $this->cipher()->decrypt((string) $value), $platform);
       }
   }

   DoctrineIntegration::registerTypes();
   Type::overrideType(BirthDateType::NAME, EncryptedBirthDateType::class);
   ```

   With the Symfony bundle, register it in the configuration of DoctrineBundle (your configuration wins over the one
   the bundle prepends):

   ```yaml
   doctrine:
       dbal:
           types:
               urssaf_cesu_birth_date: App\Doctrine\EncryptedBirthDateType
   ```

   The same pattern covers `DeclarationResultType` and `SubmissionErrorsType` (the JSON column becomes text). No query
   filters on these columns and no constraint uses them, so encryption breaks nothing. Use authenticated encryption with
   a key kept outside the database (KMS, Symfony secrets), and re-run `doctrine:migrations:diff` so that the column is
   wide enough for the ciphertext.
4. **Backups and logs.** Backups keep the data that was erased afterwards: bound their retention and replay the erasures
   after a restore. The package never logs personal data, and the value objects mask it in `var_dump()`.

## Migrations

The package ships **no migration**: the schema depends on your platform and on the rest of your schema. Generate it in
your application:

```bash
php bin/console doctrine:migrations:diff --em=urssaf_cesu
php bin/console doctrine:migrations:migrate
```

(or `doctrine:schema:update --dump-sql` for a quick look). Re-run `diff` after each upgrade of the package: the mapping
may evolve (see the changelog). `urssaf:cesu:check` reports the pending changes of the package tables.

## Transactions

`DoctrineTransactionManager::transactional()` runs the operation in a DBAL transaction. The repositories flush on every
save, so those flushes join the surrounding transaction and become durable together (this is what makes a supersede,
`delete()` then `save()` of the declaration record, and the outbox write atomic). On failure the transaction is rolled
back, the package entities still managed by the entity manager (`EmployerAccount`, `Mandate`, `DeclarationRecord`) are
detached so that none of them keeps a state that was rolled back, and the throwable is rethrown unchanged. The entity
manager is neither cleared nor closed: the entities of your application stay managed (domain exceptions are ordinary
control flow). Domain events are published by `UnitOfWork` only after the commit.

Because every repository `save()` flushes (see [Dedicated entity manager](#dedicated-entity-manager-strongly-recommended)),
changes to host entities pending in the same entity manager are written, and rolled back, with the package
transaction: use a dedicated entity manager.

If a handler runs inside a transaction opened by your application, the package transaction becomes a nested one
(a savepoint when the platform supports it): its changes become durable only when your outer transaction commits, but
the domain events are published when the nested transaction returns, i.e. before that commit. See the outbox pattern in
[workflow.md](workflow.md).

## Claim semantics

`claimForSubmission(id, now): bool` is the concurrency guard of `DeclareSalary`. It is one conditional
`UPDATE ... SET state = 'submitting', attempts = attempts + 1, last_attempt_at = :now ... WHERE id = :id AND state =
'pending'`: exactly one worker wins (returns `true`); the others get `false` and the handler reports `not_claimed`. It
records no domain event, never loads the aggregate (the managed copy is detached, so call `get()` again to read the
claimed row) and increments the version.

A record left in `submitting` (worker crash, lost answer) is never resubmitted blindly: see the stale reconciliation
in [workflow.md](workflow.md).

## Ordering and portability

The queries use DQL and the DBAL query builder only, no driver-specific SQL, and never rely on "affected rows" except
for the claim (whose `UPDATE` always changes a value) and for Doctrine's versioned `UPDATE` (safe because the version always changes). `NULL` never decides an order: accounts never checked come first
through an explicit rank, then ties are broken by creation instant and id (the in-memory "insertion order" has no SQL
equivalent). `tenants()` lists the tenants in the order of the database collation, which is the byte order for the ASCII
tenant ids the package expects. The test suite runs on SQLite.

## Uniqueness error codes

`save()` translates a duplicate into an `InvariantViolation` (a `UrssafException`) with the same stable code as the
in-memory repositories; a concurrent insert that hits the unique constraint first is reported with the same code:

| Code | Constraint |
|---|---|
| `employer_account.duplicate_cesu_number` | `(tenant_id, cesu_number)` of the accounts |
| `employer_account.duplicate_external_reference` | `(tenant_id, external_reference)` of the accounts |
| `mandate.duplicate_active` | one non-cancelled mandate per `(tenant_id, cesu_number)` (key table) |
| `declaration_record.duplicate_external_reference` | `(tenant_id, external_reference)` of the records |
| `declaration_record.duplicate_declaration_id` | `(tenant_id, declaration_id)` of the records |
| `employer_account.concurrent_modification`, `mandate.concurrent_modification`, `declaration_record.concurrent_modification` | optimistic lock lost (see above) |

The use cases check these conditions first and raise their own exceptions; the constraints are the safety net against
concurrent inserts.
