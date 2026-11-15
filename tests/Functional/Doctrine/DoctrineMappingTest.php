<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Mapping scenarios (schema, round trips through the entity manager only, UTC instants): one method per scenario
 * (reference DoctrineMappingTest, lot 7 design §4.7).
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMappingTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
    }

    public function testTheMappingIsValidAndInSyncWithTheSchema(): void
    {
        $validator = new SchemaValidator($this->entityManager);

        static::assertSame([], $validator->validateMapping());
        static::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testTablesArePrefixedAndConstraintsAndIndexesExist(): void
    {
        $manager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map(static fn(Table $table): string => $table->getObjectName()->getUnqualifiedName()->getValue(), $manager->introspectTables());
        sort($tables);

        static::assertSame(
            [
                'urssaf_cesu_active_mandates',
                'urssaf_cesu_declaration_records',
                'urssaf_cesu_employer_accounts',
                'urssaf_cesu_mandates',
                'urssaf_cesu_outbox',
            ],
            $tables,
        );
        static::assertSame(
            [
                'idx_urssaf_cesu_employer_checked' => [false, ['tenant_id', 'last_checked_at']],
                'idx_urssaf_cesu_employer_stage' => [false, ['tenant_id', 'stage']],
                'uniq_urssaf_cesu_employer_cesu_number' => [true, ['tenant_id', 'cesu_number']],
                'uniq_urssaf_cesu_employer_external_reference' => [true, ['tenant_id', 'external_reference']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_cesu_employer_accounts')),
        );
        static::assertSame(
            [
                'idx_urssaf_cesu_mandate_employer' => [false, ['tenant_id', 'cesu_number', 'notified_at']],
                'idx_urssaf_cesu_mandate_state' => [false, ['tenant_id', 'state']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_cesu_mandates')),
        );
        static::assertSame(
            [
                'idx_urssaf_cesu_declaration_stale' => [false, ['state', 'last_attempt_at']],
                'idx_urssaf_cesu_declaration_state' => [false, ['tenant_id', 'state', 'updated_at']],
                'uniq_urssaf_cesu_declaration_declaration_id' => [true, ['tenant_id', 'declaration_id']],
                'uniq_urssaf_cesu_declaration_external_reference' => [true, ['tenant_id', 'external_reference']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_cesu_declaration_records')),
        );
        static::assertSame(
            ['uniq_urssaf_cesu_active_mandate_id' => [true, ['mandate_id']]],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_cesu_active_mandates')),
        );
        static::assertSame(
            [
                'idx_urssaf_cesu_outbox_pending' => [false, ['dispatched_at', 'sequence']],
                'uniq_urssaf_cesu_outbox_event_id' => [true, ['event_id']],
            ],
            self::indexes($manager->introspectTableByUnquotedName('urssaf_cesu_outbox')),
        );
    }

    public function testTheActiveMandateKeyIsTheTenantAndTheCesuNumber(): void
    {
        $table = $this->entityManager->getConnection()->createSchemaManager()->introspectTableByUnquotedName('urssaf_cesu_active_mandates');
        $primary = $table->getPrimaryKeyConstraint();

        static::assertNotNull($primary);
        static::assertSame(
            ['tenant_id', 'cesu_number'],
            array_map(static fn(UnqualifiedName $column): string => $column->getIdentifier()->getValue(), $primary->getColumnNames()),
        );
    }

    public function testNoColumnHoldsPersonalDataOtherThanTheEmployerBirthDate(): void
    {
        $manager = $this->entityManager->getConnection()->createSchemaManager();
        $columns = [];
        foreach ($manager->introspectTables() as $table) {
            foreach ($table->getColumns() as $column) {
                $columns[] = $column->getObjectName()->getIdentifier()->getValue();
            }
        }

        static::assertContains('birth_date', $columns);
        static::assertGreaterThan(40, \count($columns));
        foreach ($columns as $column) {
            static::assertDoesNotMatchRegularExpression('/iban|bic|nir|first_name|birth_name|email|phone|address/i', $column, $column);
        }
    }

    public function testTheVersionIsAnOptimisticLockColumnOfTheThreeAggregates(): void
    {
        $account = ContractSamples::account(1);
        $mandate = ContractSamples::mandate(1);
        $record = ContractSamples::record(1, DeclarationState::Pending);
        $this->entityManager->persist($account);
        $this->entityManager->persist($mandate);
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        static::assertSame(1, $this->version('urssaf_cesu_employer_accounts'));
        static::assertSame(1, $this->version('urssaf_cesu_mandates'));
        static::assertSame(1, $this->version('urssaf_cesu_declaration_records'));

        $account->markMandateNotified(ContractSamples::at('2026-11-10T10:00:00Z'));
        $mandate->cancel(ContractSamples::at('2026-11-10T10:00:00Z'));
        $record->startSubmission(ContractSamples::at('2026-11-10T10:00:00Z'));
        $this->entityManager->flush();

        static::assertSame(2, $this->version('urssaf_cesu_employer_accounts'));
        static::assertSame(2, $this->version('urssaf_cesu_mandates'));
        static::assertSame(2, $this->version('urssaf_cesu_declaration_records'));
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'accounts')]
    public function testAnAccountIsReadBackIdentically(string $case): void
    {
        $account = DoctrineMappingSamples::account($case);

        $read = $this->roundTrip($account, EmployerAccount::class, $account->id()->value());

        static::assertNotSame($account, $read);
        static::assertEquals($account, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertTrue($read->tracking()->equals($account->tracking()));
        static::assertSame($account->birthDate()->toString(), $read->birthDate()->toString());
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'mandates')]
    public function testAMandateIsReadBackIdentically(string $case): void
    {
        $mandate = DoctrineMappingSamples::mandate($case);

        $read = $this->roundTrip($mandate, Mandate::class, $mandate->id()->value());

        static::assertNotSame($mandate, $read);
        static::assertEquals($mandate, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame($mandate->state(), $read->state());
        static::assertEquals($mandate->lastCheck(), $read->lastCheck());
    }

    #[DataProviderExternal(DoctrineMappingCases::class, 'records')]
    public function testARecordIsReadBackIdentically(string $case): void
    {
        $record = DoctrineMappingSamples::record($case);

        $read = $this->roundTrip($record, DeclarationRecord::class, $record->id()->value());

        static::assertNotSame($record, $read);
        static::assertEquals($record, $read);
        static::assertSame([], $read->releaseEvents());
        static::assertSame($record->status()->state(), $read->status()->state());
        static::assertSame($record->snapshot()->type(), $read->snapshot()->type());
        static::assertSame($record->snapshot()->employmentKind(), $read->snapshot()->employmentKind());
        static::assertEquals($record->status()->errors(), $read->status()->errors());
        static::assertEquals($record->status()->result(), $read->status()->result());
    }

    public function testTheSnapshotColumnsAreStoredWithTheirWireCodes(): void
    {
        $this->entityManager->persist(DoctrineMappingSamples::record('cesu-plus'));
        $this->entityManager->persist(DoctrineMappingSamples::record('foster-carer'));
        $this->entityManager->persist(DoctrineMappingSamples::record('nir'));
        $this->entityManager->flush();

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT declaration_type, employment_kind, employee_reference_kind, employee_cesu_number, employment_start, employment_end, payment_date FROM urssaf_cesu_declaration_records ORDER BY id',
        );

        static::assertSame(
            [
                ['declaration_type' => 1, 'employment_kind' => 'PE', 'employee_reference_kind' => 'cesu_number', 'employee_cesu_number' => DeclarationSamples::EMPLOYEE_CESU_NUMBER, 'employment_start' => '2026-10-01', 'employment_end' => '2026-10-31', 'payment_date' => '2026-10-31'],
                ['declaration_type' => 0, 'employment_kind' => 'AF', 'employee_reference_kind' => 'cesu_number', 'employee_cesu_number' => DeclarationSamples::EMPLOYEE_CESU_NUMBER, 'employment_start' => '2026-10-01', 'employment_end' => '2026-10-31', 'payment_date' => '2026-10-31'],
                ['declaration_type' => 0, 'employment_kind' => 'AF', 'employee_reference_kind' => 'nir', 'employee_cesu_number' => null, 'employment_start' => '2026-10-01', 'employment_end' => '2026-10-31', 'payment_date' => '2026-10-31'],
            ],
            $rows,
        );
    }

    public function testAbsentGroupsAreStoredAsSqlNull(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->persist(ContractSamples::mandate(1));
        $this->entityManager->persist(ContractSamples::record(1, DeclarationState::Pending));
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();

        static::assertNull($connection->fetchOne('SELECT last_status FROM urssaf_cesu_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT last_status_employee_kind FROM urssaf_cesu_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT last_status_employee_cesu_number FROM urssaf_cesu_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT last_checked_at FROM urssaf_cesu_employer_accounts'));
        static::assertNull($connection->fetchOne('SELECT last_check FROM urssaf_cesu_mandates'));
        static::assertNull($connection->fetchOne('SELECT confirmed_at FROM urssaf_cesu_mandates'));
        static::assertNull($connection->fetchOne('SELECT result FROM urssaf_cesu_declaration_records'));
        static::assertNull($connection->fetchOne('SELECT declaration_id FROM urssaf_cesu_declaration_records'));
        static::assertNull($connection->fetchOne('SELECT outcome_unknown_reason FROM urssaf_cesu_declaration_records'));
        static::assertSame('[]', $connection->fetchOne('SELECT errors FROM urssaf_cesu_declaration_records'));
    }

    public function testInstantsAreStoredInUtcAndBirthDatesAsCalendarDatesWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $account = ContractSamples::account(1, EmployerStage::Registered, ContractSamples::TENANT, '2026-11-10T23:30:00+01:00');
            $this->entityManager->persist($account);
            $this->entityManager->flush();
            $this->entityManager->clear();

            $read = $this->entityManager->find(EmployerAccount::class, $account->id());
            $stored = $this->entityManager->getConnection()->fetchAssociative('SELECT created_at, updated_at, birth_date FROM urssaf_cesu_employer_accounts');
        } finally {
            date_default_timezone_set($previous);
        }

        static::assertEquals($account, $read);
        static::assertSame(
            ['created_at' => '2026-11-10 22:30:00', 'updated_at' => '2026-11-10 22:30:00', 'birth_date' => '1975-01-30'],
            $stored,
        );
    }

    public function testTheUniqueConstraintsAreEnforcedByTheSchema(): void
    {
        $this->entityManager->persist(ContractSamples::account(1));
        $this->entityManager->flush();
        $duplicate = EmployerAccount::register(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-2'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );
        $this->entityManager->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function version(string $table): int
    {
        $version = $this->entityManager->getConnection()->fetchOne('SELECT version FROM ' . $table);
        static::assertIsNumeric($version);

        return (int) $version;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function roundTrip(object $aggregate, string $class, string $id): object
    {
        $this->entityManager->persist($aggregate);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->entityManager->find($class, $this->identifier($class, $id));
        static::assertInstanceOf($class, $read);

        return $read;
    }

    /**
     * @param class-string $class
     */
    private function identifier(string $class, string $id): object
    {
        return match ($class) {
            EmployerAccount::class => EmployerAccountId::fromString($id),
            Mandate::class => MandateId::fromString($id),
            default => DeclarationRecordId::fromString($id),
        };
    }

    /**
     * @return array<string, array{bool, list<string>}> unique flag and columns by index name
     */
    private static function indexes(Table $table): array
    {
        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $name = $index->getObjectName()->getIdentifier()->getValue();
            if (str_starts_with($name, 'idx_') || str_starts_with($name, 'uniq_')) {
                $indexes[$name] = [
                    $index->getType() === IndexType::UNIQUE,
                    array_map(static fn(IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()),
                ];
            }
        }
        ksort($indexes);

        return $indexes;
    }
}
