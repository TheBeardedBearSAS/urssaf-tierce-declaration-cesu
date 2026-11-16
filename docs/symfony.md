# Symfony bundle

Bundle class: `TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle` (alias
`urssaf_tierce_declaration_cesu`), for Symfony 6.4 and 7.x. Register it in `config/bundles.php`. The design decisions
that differ from the other URSSAF packages are in [ADR 0013](adr/0013-symfony-bridge.md): the names carry `cesu`
(`urssaf:cesu:` commands, `urssaf_cesu` lock prefix, Monolog channel and schedule, `URSSAF_CESU_*` variables), so the
two packages can be installed in the same application.

## Symfony Flex recipe

The recipe lives in `recipe/thebeardedbear/urssaf-tierce-declaration-cesu/<version>/` (manifest and copied files), in
the layout of [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), where it is published: copy the
version directory there in a pull request when a release changes it (Flex picks the highest recipe version not above the
installed package version). A test boots the bundle with the copied configuration, so that the recipe follows the
configuration tree.

To try a recipe change before it is published, or to serve it from your own endpoint, `bin/build-flex-endpoint
<output-dir> <base-url>` builds a Flex endpoint (`index.json` and one JSON file per recipe) to serve over HTTPS at
`<base-url>` and declare in `extra.symfony.endpoint` of the application (`["<base-url>/index.json", "flex://defaults"]`).
The recipe of version 0.1 is in `recipe/thebeardedbear/urssaf-tierce-declaration-cesu/0.1/`; it is prepared for
symfony/recipes-contrib and not published there yet.

## Configuration reference

The configuration tree is defined in `Bridge\Symfony\DependencyInjection\BundleConfiguration`.

| Key | Type | Default | Description |
|---|---|---|---|
| `environment` | enum `sandbox` \| `production` | required | Target platform (`https://api-edi.urssaf.fr` / `https://api.urssaf.fr`); usually `%env(URSSAF_CESU_ENV)%`, resolved at runtime. |
| `base_uri` | string \| null | `null` | Overrides the base URI of the selected environment (sandbox host, proxy); validated at runtime. |
| `request_id_header` | string \| null | `null` | Name of the optional request-id header; its value is a new UUID for every attempt. Null: not sent ([ADR 0002](adr/0002-http-client-adaptations.md)). |
| `application_id_header` | `{name, value}` | `{name: null, value: null}` | Optional fixed header sent on every call. Both `name` and `value`, or neither. |
| `agreement_encoding` | `boolean` \| `numeric` | `boolean` | How `accord` (060) is written: `true`/`false` or `1`/`0` ([ADR 0008](adr/0008-accord-encoding.md)). |
| `token_safety_margin` | int >= 0 | `60` | Seconds before expiry from which an access token is renewed. |
| `business_timezone` | string | `Europe/Paris` | "Today" of the employment period checks ([ADR 0001](adr/0001-date-only-encoding.md)); feeds the API configuration and the workflow policy; validated against `DateTimeZone::listIdentifiers()`. |
| `http_client` | string \| null | `null` | Service id of a PSR-18 client. Default: a dedicated Symfony HttpClient wrapped in `Psr18Client`. |
| `http_timeout` | float >= 0.1 | `15.0` | Timeout in seconds of the default HTTP client. |
| `credentials_provider` | string \| null | `null` | Service id of a `CredentialsProvider`. Mutually exclusive with `credentials`. |
| `credentials` | map tenant id => `{client_id, client_secret}` | `[]` | Static credentials by tenant ([ADR 0010](adr/0010-tenant-model.md)); both keys required, non-empty. Use `%env(...)%` placeholders. |
| `tenants` | list of string | keys of `credentials` | Tenants processed by the commands that cannot discover them. |
| `token_store.cache_pool` | string | `cache.app` | PSR-6 pool that stores the access tokens. Recommended: a **dedicated pool** (see below). |
| `token_store.service` | string \| null | `null` | Service id of a `TokenStore`; takes precedence over the cache pool. |
| `gateway.simulated` | bool | `false` | Tests only: replaces the URSSAF API with the in-memory `Testing\SimulatedCesuGateway`, exposed as a public service to script the scenarios (see [testing.md](testing.md)). Refused with `environment: production`. |
| `rate_limiter.enabled` | bool | `false` | Enforces the hourly quota per tenant, in the current process (`SlidingWindowRateLimiter`, [ADR 0011](adr/0011-quotas-and-rate-limit.md)). |
| `rate_limiter.limit` | int >= 1 | `2500` | Calls per tenant within the window. |
| `rate_limiter.window` | int >= 1 | `3600` | Window length in seconds. |
| `lock.enabled` | `auto` \| bool | `auto` | Non-blocking lock per command / handler (see [Locks](#locks-against-overlapping-runs)). `auto`: enabled when `symfony/lock` is installed and the factory service exists; `true`: required; `false`: disabled. |
| `lock.factory` | string | `lock.factory` | Service id of a `Symfony\Component\Lock\LockFactory`. |
| `lock.ttl` | float >= 1 | `3600.0` | Seconds after which the lock of a crashed process expires. Set it above the longest run of a command. |
| `outbox.enabled` | bool | `false` | Writes the integration events in an outbox, in the transaction of each state change (see [integration-events.md](integration-events.md)). |
| `outbox.service` | string \| null | `null` | Service id of your `Outbox` (required with the `custom` driver). Default: the in-memory outbox. |
| `outbox.sink` | string | `messenger` | `messenger`, `event_dispatcher`, or the service id of an `IntegrationEventSink`. |
| `outbox.bus` | string | `messenger.default_bus` | Bus of the `messenger` sink. |
| `outbox.retention` | ISO 8601 duration | `P30D` | How long a dispatched entry is kept (`urssaf:cesu:outbox:purge`). |
| `scheduler.enabled` | bool | `false` | Registers a Symfony Scheduler schedule that runs the safety crons of the package (see [Scheduler](#scheduler)). Requires `symfony/scheduler`. |
| `scheduler.name` | string | `urssaf_cesu` | Name of the schedule (transport `scheduler_<name>`). |
| `scheduler.frequencies.sync_employer_statuses` | ISO 8601 duration \| null | `PT1H` | `urssaf:cesu:employers:sync-status`; null leaves the task out. |
| `scheduler.frequencies.reconcile_stale_declarations` | ISO 8601 duration \| null | `PT15M` | `urssaf:cesu:declarations:reconcile-stale`. |
| `scheduler.frequencies.relay_outbox` | ISO 8601 duration \| null | `PT1M` | `urssaf:cesu:outbox:relay` (only with the outbox). |
| `scheduler.frequencies.purge_outbox` | ISO 8601 duration \| null | `P1D` | `urssaf:cesu:outbox:purge` (only with the outbox). |
| `workflow.stale_submission_after` | ISO 8601 duration | `PT15M` | Age after which a `submitting` declaration is stale ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). |
| `workflow.max_submission_attempts` | int >= 1 | `5` | Attempts after which a technical failure is no longer requeued. |
| `workflow.require_local_mandate` | bool | `true` | Refuses 050 / 060 locally when no active mandate is tracked ([ADR 0009](adr/0009-guards-and-opt-outs.md)). |
| `workflow.allow_foster_carer_declarations` | bool | `false` | Accepts `typeEmploi = AF` declarations locally (ADR 0009). |
| `workflow.default_page_size` | int >= 1 | `100` | Records loaded by a batch use case when no limit is given. |
| `workflow.max_page_size` | int >= 1 | `1000` | Upper bound of the records loaded by a batch use case. |
| `workflow.erasure.allow_unknown_outcomes` | bool | `false` | Default of `ForgetEmployer` for declarations whose outcome is unknown: refused when `false` ([ADR 0015](adr/0015-employer-erasure.md)). A submission in flight is always refused. |
| `workflow.erasure.allow_active_mandate` | bool | `false` | Default of `ForgetEmployer` for an employer whose mandate is still active (it stays active at URSSAF): refused when `false` (ADR 0015). |
| `persistence.driver` | enum `in_memory` \| `doctrine` \| `custom` | `doctrine` if `doctrine/orm` is installed, else `in_memory` | Persistence adapter. `doctrine` needs DoctrineBundle (the container build fails without it); `in_memory` is for tests and prototypes. |
| `persistence.entity_manager` | string | `default` | Entity manager that gets the mapping (driver `doctrine`). **Use a dedicated entity manager** (see [Doctrine](#doctrine)): the repositories flush the whole unit of work of the entity manager they use. |
| `persistence.employer_account_repository` | string \| null | `null` | Service id of your `EmployerAccountRepository` (driver `custom`). |
| `persistence.mandate_repository` | string \| null | `null` | Service id of your `MandateRepository` (driver `custom`). |
| `persistence.declaration_record_repository` | string \| null | `null` | Service id of your `DeclarationRecordRepository` (driver `custom`). |
| `persistence.transaction_manager` | string \| null | `null` | Service id of your `TransactionManager`, shared with your repositories (driver `custom`; default `ImmediateTransactionManager`). |
| `persistence.workflow_statistics` | string \| null | `null` | Service id of your `WorkflowStatistics` (driver `custom`, optional: without it `GetWorkflowOverviewHandler` and `urssaf:cesu:status` are not registered). |
| `messenger.enabled` | bool | `false` | Registers the Messenger handlers of [Messenger](#messenger). |
| `messenger.bus` | string | `messenger.default_bus` | Bus used by `urssaf:cesu:employers:sync-status --async`. |

Validation rules enforced at container build time:

- `credentials` and `credentials_provider` are mutually exclusive, and one of them is required.
- `gateway.simulated` cannot be used with `environment: production` (a placeholder that resolves to `production` at
  runtime is refused when the gateway is built).
- `outbox.enabled` with the `custom` driver requires `outbox.service`.
- `lock.enabled: true` requires `symfony/lock`; `scheduler.enabled: true` requires `symfony/scheduler`.
- `workflow.default_page_size` must not exceed `workflow.max_page_size`.
- The `custom` driver requires the three repository service ids.
- The `doctrine` driver requires DoctrineBundle to be registered (`LogicException` when the container is built otherwise):
  an application with `doctrine/orm` but without DoctrineBundle must set `persistence.driver` explicitly.
- `request_id_header` and `application_id_header.name` must be different headers; `application_id_header` needs both
  `name` and `value`, or neither.
- `business_timezone` must be a known time zone, `outbox.retention`, `workflow.stale_submission_after` and the
  scheduler frequencies valid ISO 8601 durations, `environment` and `agreement_encoding` known values.

`base_uri`, the header names and values and `token_safety_margin` are validated when the configuration is built at
runtime (they may be environment placeholders): `urssaf:cesu:check` reports them.

The `in_memory` driver keeps everything in the process memory and loses it when the process ends: use it for tests and
prototypes only.

### Dedicated cache pool for the access tokens

The access tokens are stored in the `token_store.cache_pool` pool (`cache.app` by default). Declare a dedicated pool, so
that `cache:pool:clear cache.app` (a routine deployment step) does not force a new token request for every tenant, and
so that the tokens live in an adapter you control (Redis with authentication, for instance):

```yaml
framework:
    cache:
        pools:
            urssaf_cesu.token_cache:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_tierce_declaration_cesu:
    token_store: { cache_pool: urssaf_cesu.token_cache }
```

The pool must be shared by every process that calls URSSAF (web, workers, cron): a per-process `array` adapter would
request a token per run. Tokens are secrets: do not expose the pool through a cache dashboard or a shared namespace.

## Persistence

- `in_memory` (default): the three repositories, the statistics and an immediate transaction manager, in memory.
- `custom`: your own implementations, as service ids: `employer_account_repository`, `mandate_repository` and
  `declaration_record_repository`. Give the `transaction_manager` they share: with the outbox, the integration events are
  written in the transaction of the state change ([ADR 0012](adr/0012-integration-events.md)), which an
  `ImmediateTransactionManager` (the default) cannot guarantee. `workflow_statistics` is optional.
- `doctrine` (default when `doctrine/orm` is installed): the Doctrine repositories, transaction manager, statistics and
  outbox on the entity manager `persistence.entity_manager`, see [Doctrine](#doctrine) and [doctrine.md](doctrine.md).
  The tables are yours to migrate (`doctrine:migrations:diff`).

## Console commands

Every command is in the `urssaf:cesu:` namespace. The operator never types a personal value on the command line: the
employer birth date and the employee NIR are read from a hidden prompt (not echoed, run the command interactively) and
the commands print identifiers, Cesu numbers, states, codes, amounts and instants only ([ADR 0007](adr/0007-personal-data.md)).
URSSAF error descriptions are not printed.

| Command | Purpose | `--tenant` |
|---|---|---|
| `urssaf:cesu:verify-employer` | Verify an employer account (method 020), optionally with an employee reference; writes nothing. | optional if one tenant is configured |
| `urssaf:cesu:verify-employee` | Verify an employee account (method 021). | optional if one tenant is configured |
| `urssaf:cesu:estimate` | Estimate the contributions of a home employment (method 040). | optional if one tenant is configured |
| `urssaf:cesu:employer:import` | Track an employer already registered at URSSAF (no URSSAF call). | optional if one tenant is configured |
| `urssaf:cesu:mandate:register` | Notify the third-party declaration mandate of a tracked employer (method 030). | optional if one tenant is configured |
| `urssaf:cesu:mandate:cancel` | Cancel the mandate (method 031), after a confirmation (`--force` in non-interactive mode). | optional if one tenant is configured |
| `urssaf:cesu:employer:forget` | Erase a tracked employer, its mandates and its declaration records from the local store (GDPR, [ADR 0015](adr/0015-employer-erasure.md)); nothing is sent to URSSAF. Asks for a confirmation (`--force` in non-interactive mode, it only skips the confirmation); `--allow-active-mandate` and `--allow-unknown-outcomes` override the `workflow.erasure.*` defaults for the run. See [workflow.md](workflow.md#erasure-gdpr). | optional if one tenant is configured |
| `urssaf:cesu:employers:sync-status` | Refresh the tracked employers, least recently checked first (method 020, employer only); `--async` dispatches one message per tenant. | optional, default: every tenant with employers; **required with `--employer`** (an employer is identified within a tenant) |
| `urssaf:cesu:declarations:reconcile-stale` | Move the declarations left `submitting` for `workflow.stale_submission_after` to manual review: they are never resent ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). | none (all tenants) |
| `urssaf:cesu:declarations:resolve` | Settle an `outcome_unknown` declaration after a manual check: `--declared-as=<idDeclaration>` or `--not-received`. | optional if one tenant is configured |
| `urssaf:cesu:requeue-invalid-credentials` | Move the declarations refused for invalid credentials back to `pending`, once the credentials are fixed; send them again with `DeclareSalary`. | optional if one tenant is configured |
| `urssaf:cesu:outbox:relay` | Deliver the pending integration events (exit `1` when a delivery failed). Only with the outbox. | none |
| `urssaf:cesu:outbox:purge` | Delete the outbox entries dispatched for longer than `outbox.retention`. Only with the outbox. | none |
| `urssaf:cesu:status` | Count the employers, mandates and declarations by tenant and state (local store only); `--format=json` for monitoring. Not available with a `custom` driver without `workflow_statistics`. | optional, default: every tenant with records |
| `urssaf:cesu:check` | Check the installation (see below); `--skip-remote` does not call URSSAF. | optional, default: configured `tenants` |

The batch commands accept `--limit=N` (positive integer, capped by the workflow policy) and `--dry-run` (lists what would
be done without calling URSSAF or changing anything), and print a table of the processed items followed by the count of
each outcome. The employer commands select an employer with one of `--employer=<external reference>`,
`--cesu-number=<Cesu number>` or `--account-id=<account id>`; `urssaf:cesu:declarations:resolve` selects a declaration
with `--declaration=<external reference>` or `--record-id=<id>`.

| Exit code | When |
|---|---|
| `0` | Done (whatever the remote answer of a read: an unregistered employer is a result); lock held (skipped); `--dry-run`. |
| `1` | A package exception stops the command (URSSAF refusal, authentication failure, unavailability, unknown outcome, record not found, erasure refused...); a batch item ended `failed`, `invalid_credentials` or `manual_review`; a failed delivery or check. |
| `2` | Invalid usage: missing, conflicting or malformed option, no tenant, input rejected by validation, a hidden value requested in non-interactive mode. |

Outcomes (`Application\Dto\ItemOutcome`) are printed in the report of the batch commands: `declared`, `rejected`,
`already_declared`, `outcome_unknown`, `invalid_credentials`, `exhausted`, `not_claimed`, `replayed`, `manual_review`,
`changed`, `unchanged`, `failed`.

A typical crontab (or the [Scheduler](#scheduler)):

```cron
0 * * * *      php bin/console urssaf:cesu:employers:sync-status
*/15 * * * *   php bin/console urssaf:cesu:declarations:reconcile-stale
* * * * *      php bin/console urssaf:cesu:outbox:relay
0 3 * * *      php bin/console urssaf:cesu:outbox:purge
```

### Checking the installation

`urssaf:cesu:check` prints one line per check and exits with `1` when one of them fails (warnings do not fail): run it
after each deployment, or in the CI of the application with `--skip-remote`.

| Check | Failure / warning |
|---|---|
| `configuration` | failure: the configuration cannot be built (unknown `URSSAF_CESU_ENV`, invalid base URI, header or safety margin) |
| `credentials` | failure: no credentials for a tenant in the configured provider |
| `authentication` | failure: URSSAF refuses the credentials or cannot be reached. A new access token is requested for every tenant (any cached one is ignored), the tokens of the application are not touched. Skipped with `--skip-remote`; replaced by a `gateway` warning when `gateway.simulated` is enabled |
| `token store` | warning: the tokens are stored in `cache.app` instead of a dedicated pool |
| `persistence` | warning: `in_memory` persistence (every record is lost when the process ends) |
| `database schema` | failure (driver `doctrine`): the package tables are missing or differ from the mapping; warning: the entity manager is the `default` one |
| `lock` | warning: the lock is disabled or `symfony/lock` is missing |

Add checks of your own by implementing `Bridge\Symfony\Diagnostic\InstallationCheck` (autoconfigured, tag
`urssaf_tierce_declaration_cesu.installation_check`): they run after the package checks, unless their tag has a positive
`priority`.

## Messenger

With `messenger.enabled: true`, the following messages (namespace `Bridge\Symfony\Message`) are handled by the handlers
of `Bridge\Symfony\MessageHandler`. They are plain `final readonly` classes holding identifiers only, so they serialize
with any Messenger serializer and carry no personal data.

| Message | Properties | Effect |
|---|---|---|
| `SyncEmployerStatusesMessage` | `tenantId` | Refreshes the least recently checked employers of the tenant (method 020, employer only). |
| `SyncEmployerStatusMessage` | `tenantId`, `employerAccountId` | Refreshes one tracked employer, e.g. dispatched with a `DelayStamp` after the mandate (030). |

There is **no declaration message**: `DeclareSalary` is synchronous and the record never holds the payload
([ADR 0006](adr/0006-local-lifecycles.md)); the other operations need an answer for the user, and 010, 060 and 070 are
never resent ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). Wrap the handler in a message of your own if you want
one of them asynchronous. `urssaf:cesu:employers:sync-status --async` dispatches one `SyncEmployerStatusesMessage` per
tenant on `messenger.bus`.

Failure mapping (`MessageFailures`):

- `RetryableException` (URSSAF unavailable, rate limit exceeded...) => `RecoverableMessageHandlingException`: the
  message is retried by the retry strategy of the transport, with the delay requested by URSSAF (`Retry-After`) or by
  the local rate limiter when known (on symfony/messenger older than 7.2 the exception carries no delay).
- Operation already running (lock held) => `RecoverableMessageHandlingException` with a 30 s delay.
- Any other `UrssafException` (unknown account, authentication failure, rejection) => `UnrecoverableMessageHandlingException`:
  retrying the same message cannot succeed.
- Failures of other origins (database down...) are left to the retry strategy of the host.

## Scheduler

Instead of the crontab, the bundle can register a [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)
schedule that runs the same console commands, through Messenger (`Symfony\Component\Console\Messenger\RunCommandMessage`,
handled by the framework): same locks, same reports, same safety nets.

```bash
composer require symfony/scheduler
```

```yaml
urssaf_tierce_declaration_cesu:
    scheduler:
        enabled: true
        frequencies: { sync_employer_statuses: PT1H, reconcile_stale_declarations: PT15M }
```

Run one worker for the schedule (and keep it running, like any Messenger worker):

```bash
php bin/console messenger:consume scheduler_urssaf_cesu
```

`urssaf:cesu:requeue-invalid-credentials` is not scheduled: run it by hand after fixing the credentials. A command that
exits with a non-zero code makes its message fail (`RunCommandFailedException`). Run a single scheduler worker (or enable
the lock): two workers of the same schedule would run each task twice.

## Locks against overlapping runs

Two crons that overlap, a cron racing a Messenger worker, or two workers handling the same tenant could poll the same
records at the same time. The atomic claims keep that safe but wasteful, and the quota is shared. When `symfony/lock` is
installed, the batch commands and the Messenger handlers take a **non-blocking** lock named
`urssaf_cesu.<operation>.<scope>` before they work, and release it afterwards:

| Operation | Used by | Scope |
|---|---|---|
| `employers.sync-status` | `urssaf:cesu:employers:sync-status` (batch), `SyncEmployerStatusesMessage` | tenant, or `*` without `--tenant` |
| `employer.sync-status` | `urssaf:cesu:employers:sync-status` with an employer selector, `SyncEmployerStatusMessage` | selector value (command) / account id (message) |
| `declarations.reconcile-stale` | `urssaf:cesu:declarations:reconcile-stale` | `*` |
| `requeue-invalid-credentials` | `urssaf:cesu:requeue-invalid-credentials` | tenant |
| `outbox.relay` | `urssaf:cesu:outbox:relay` | `*` |

The interactive commands (verify, estimate, import, mandate, resolve) are operator actions and take no lock: the domain
uniqueness rules and the atomic claim protect them. An external reference and an account id of the same employer do not
exclude each other (method 020 is a read and the refresh is idempotent).

When the lock is already held: a **command** prints that the operation is already running and exits with `0`; a
**Messenger handler** throws `RecoverableMessageHandlingException` with a 30 s delay. `--dry-run` takes no lock.

```bash
composer require symfony/lock
```

```yaml
framework:
    lock: '%env(LOCK_DSN)%'   # redis://..., or `flock`; must be shared by every host that runs the crons / workers

urssaf_tierce_declaration_cesu:
    lock: { enabled: auto, factory: lock.factory, ttl: 3600 }
```

The store must be visible to all the processes that may overlap: `flock` only protects processes of the same machine.
`ttl` bounds how long the lock of a crashed process blocks the operation. Without `symfony/lock` (or with
`enabled: false`) everything just runs, protected by the claims only.

## Doctrine

When the driver is `doctrine` and DoctrineBundle is registered, the bundle prepends the Doctrine configuration: the
custom DBAL types and the XML mapping (see [doctrine.md](doctrine.md)). With a non-default `entity_manager`, the mapping
is attached to that entity manager only.

**Use a dedicated entity manager** (`persistence.entity_manager: urssaf_cesu`, with its own DBAL connection). The
repositories `flush()` on every save and a Doctrine flush writes *all* pending changes of the entity manager: on a shared
entity manager, a package call would also flush (and, on failure, roll back) half-finished changes of your own entities.
`DoctrineTransactionManager` detaches only the package entities after a rollback and never clears your entities.

The violation codes `employer_account.concurrent_modification`, `mandate.concurrent_modification` and
`declaration_record.concurrent_modification` (optimistic lock lost, [doctrine.md](doctrine.md#identity-and-concurrency))
are translated like every other code (English and French shipped).

The bundle does not touch `doctrine.orm.auto_generate_proxy_classes`: the mapped entities are not `final` (final by
convention), so the proxy cache warmer can pre-generate their proxies in every mode.

**Native lazy objects (PHP 8.4+).** On PHP 8.4 or higher, with an ORM that implements them (3.4+), the bundle also
prepends `doctrine.orm.enable_native_lazy_objects: true`: the ORM then uses PHP lazy objects instead of generated
proxies, which is required when `symfony/var-exporter` 8 is installed (it no longer ships the lazy ghosts the ORM
proxies rely on; without it the ORM throws "Symfony LazyGhost is not available"). It is only a default: your own
`doctrine` configuration wins, for instance `enable_native_lazy_objects: false` with `symfony/var-exporter` 6.4 or 7. On
PHP 8.2 and 8.3 nothing is prepended.

## Validation and forms

The use cases validate their input themselves and throw `ValidationFailed` with every violation at once (code
`<subject>.<reason>`, path such as `employmentPeriod.start`, scalar params). When `symfony/validator` is installed, the
bundle registers the public service `Bridge\Symfony\Validation\ViolationListConverter`, which turns them into a Symfony
`ConstraintViolationList`:

| `ConstraintViolation` | Value |
|---|---|
| `getPropertyPath()` | the violation path, with the optional prefix |
| `getCode()`, `getMessageTemplate()` | the violation code (`employment_period.starts_after_today`) |
| `getMessage()` | the translation of the code in the domain `urssaf_tierce_declaration_cesu` (current locale of the `translator`), or the English message without translator |
| `getParameters()` | the violation params as `%name%` placeholders |
| `getCause()` | the original `Domain\Validation\Violation` |
| `getInvalidValue()` | always `null`: the rejected raw values (personal data) are never exposed |

`convert()` keeps the errors only; `convertWithWarnings()` keeps the warnings too.

The drafts (`Domain\Declaration\Input\DeclarationDraft`, `Domain\Employer\Input\NewEmployerDraft` and their nested
drafts) are plain readonly objects with nullable properties: the Serializer builds them from a request body
(`#[MapRequestPayload]`, or `$serializer->deserialize($json, DeclarationDraft::class, 'json')`). Missing JSON keys become
`null` and are reported by the use case as `field.required`.

An API controller:

```php
#[Route('/employers/{reference}/declarations/{declaration}', methods: ['PUT'])]
public function declare(
    string $reference,
    string $declaration,
    #[MapRequestPayload] DeclarationDraft $draft,
    DeclareSalaryHandler $declareSalary,
    ViolationListConverter $violations,
): Response {
    try {
        $submission = $declareSalary(new DeclareSalary(
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString($declaration),
            $draft,
        ));
    } catch (ValidationFailed $failed) {
        // API Platform / Problem Details style: the list serializes with the "constraint_violation_list" normalizer.
        throw new ValidationFailedException($draft, $violations->convert($failed));
    }

    return $this->json(['state' => $submission->outcome->value], Response::HTTP_OK);
}
```

With a form, map each violation on the form (the paths follow the property paths of the draft):

```php
foreach ($violations->convert($failed) as $violation) {
    $form->addError(new FormError((string) $violation->getMessage(), $violation->getMessageTemplate(), $violation->getParameters(), null, $violation));
}
```

## Translations

The violation codes (`<subject>.<reason>`, for example `employment_period.starts_after_today`) are translatable messages
of the translation domain `urssaf_tierce_declaration_cesu`. The bundle ships English and French:
`Resources/translations/urssaf_tierce_declaration_cesu.en.yaml` and `urssaf_tierce_declaration_cesu.fr.yaml` (same keys,
kept in sync by the test suite).

To add another locale: create `translations/urssaf_tierce_declaration_cesu.<locale>.yaml` in your application, with the
same keys as the English file. To reword a shipped message, define the key in your application's file of that locale:

```yaml
# translations/urssaf_tierce_declaration_cesu.fr.yaml
"employment_period.starts_after_today": "La période d'emploi ne peut pas commencer dans le futur."
```

Keys defined in your application's `translations/` directory override the shipped messages. Translate a violation with
`$translator->trans($violation->code(), [], 'urssaf_tierce_declaration_cesu')`.
