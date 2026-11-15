# ADR 0014 — Doctrine bridge

Status: Accepted (2026-11-15, lead-accepted: the recommendations of the lot 7 design and the lead decisions are
binding). Relates to [ADR 0000](0000-reference-architecture.md) (layers, bridges),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md), [ADR 0012](0012-integration-events.md) and
[ADR 0013](0013-symfony-bridge.md).

## Context

The package ships a Doctrine persistence bridge (`Bridge\Doctrine`, Doctrine ORM 3 / DBAL 4, XML mapping, custom DBAL
types, repositories implementing the Application ports) as the reference package does. The CESU workflow differs from
the reference one: no payment request and no pairing (so no progress table), a local mandate lifecycle with "one active
mandate per employer", a declaration record that can be superseded, a record claimed by an atomic `UPDATE`, and
personal data that is minimised (the employer birth date, the result amounts and the URSSAF descriptions are stored;
IBAN, NIR and employee names are not, ADR 0007).

## Decision

1. **Reference structure.** XML mapping, custom DBAL types (`urssaf_cesu_*`), tables prefixed `urssaf_cesu_`, a
   dedicated entity manager recommended, no migration shipped, `DoctrineTransactionManager`, `DoctrineOutbox`,
   `DoctrineWorkflowStatistics`. Both URSSAF packages can share one database and one entity manager: no table, type,
   index, alias nor proxy namespace collides.
2. **No progress table.** Every column a query filters or sorts on is a real column of the aggregate's own table.
3. **Active mandate key table.** "One active mandate per (tenant, Cesu number)" is enforced by the primary key of
   `urssaf_cesu_active_mandates`, written with DBAL in the transaction of the mandate save (a partial unique index is
   not portable to MySQL, a pre-check alone does not cover the race).
4. **Optimistic concurrency by a native Doctrine version field.** `EmployerAccount`, `Mandate` and `DeclarationRecord`
   carry a `private int $version = 1` persistence concurrency token (not a domain concept), mapped as
   `<field name="version" type="integer" version="true"/>`. An `OptimisticLockException` on flush is translated by the
   repositories into the `InvariantViolation` codes `employer_account.concurrent_modification`,
   `mandate.concurrent_modification` and `declaration_record.concurrent_modification` (translated en / fr). The claim
   `UPDATE` and any bulk update of these tables also bump `version = version + 1`. The in-memory repositories ignore
   the version. (A comparison of Doctrine's internal original entity data was considered and rejected.)
5. **JSON columns for nullable groups.** The last employer status, the last mandate presence check, the declaration
   result and the submission errors are JSON columns: Doctrine cannot hydrate a nullable embeddable and no query
   filters on them.
6. **Dedicated birth date type.** `urssaf_cesu_birth_date` is used by both employer birth date columns, so that the
   encryption recipe of ADR 0007 targets them without touching employment or payment dates.
7. **Masked conversion errors.** A conversion error on a column that may hold personal data (birth date, JSON
   documents) never echoes the stored value: DBAL's message receives `***`.
8. **Default driver and fail fast.** In the Symfony bundle the `doctrine` driver is the default when `doctrine/orm` is
   installed (`in_memory` otherwise); selecting it without DoctrineBundle registered fails at container build, in
   `prependExtension()`, with a `LogicException`.
9. **Standalone.** `withDoctrine(EntityManagerInterface)` sets the three repositories, the transaction manager and the statistics; the outbox stays
   separate (`withOutbox(new DoctrineOutbox($em), $sink)`).
10. **Portability.** DQL and the DBAL query builder only, no reliance on "affected rows" except where the update always
    changes a value (the claim, and Doctrine's versioned `UPDATE`, whose version always changes); `NULL` never decides an order (hidden rank column); every list ends with
    deterministic tie-breakers (creation instant, then id). CI runs on SQLite.
11. **Entity manager lifecycle.** Reads return managed instances; a failed flush closes the entity manager (ORM 3.7),
    the package does not reset it (Messenger's subscriber or the host does).
12. **Not in this lot.** No mandate erasure / `ForgetEmployer` (the mandate port has no `delete()`), deferred to a
    dedicated lot.

## Consequences

- Migrations are the host's: `doctrine:migrations:diff` after installing; the `database schema` diagnostic reports
  pending changes on the package tables.
- Declaration result amounts and URSSAF error descriptions are stored (ADR 0007 clarification); encryption is a
  documented recipe.
- Documented in `docs/doctrine.md`, `docs/symfony.md`, `docs/standalone.md` and `docs/workflow.md`.
