<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandReports;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;
use Throwable;

/**
 * Guard, exit codes and rendering of the commands (reference adaptation, lot 6 design §5.2, §5.3).
 *
 * @mago-expect lint:too-many-methods Each rendering and each exit code is one test method.
 */
final class CommandReportsTest extends TestCase
{
    public function testTheResultOfTheActionIsReturned(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::guard($output, static fn(): int => 0));
        static::assertSame(1, CommandReports::guard($output, static fn(): int => 1));
        static::assertSame('', $output->fetch());
    }

    public function testAnInvalidOptionIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw new InvalidOptionException('The --limit option must be a positive integer.'));

        static::assertSame(2, $status);
        static::assertSame("The --limit option must be a positive integer.\n", $output->fetch());
    }

    public function testAValidationFailureIsAnInvalidUsageWithOneLinePerError(): void
    {
        $output = new BufferedOutput();
        $failure = ValidationFailed::withResult(ValidationResult::of(
            Violation::error('employee.nir', 'nir.invalid_format', 'Invalid nir.'),
            Violation::warning('employee', 'employee.warning', 'A warning.'),
            Violation::error('hours', 'worked_hours.out_of_range', 'Out of range.'),
        ));

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(2, $status);
        static::assertSame("employee.nir: Invalid nir. (nir.invalid_format)\nhours: Out of range. (worked_hours.out_of_range)\nInvalid input: 2 error(s).\n", $output->fetch());
    }

    public function testAnInvalidValueIsAnInvalidUsage(): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw InvalidValue::because('cesu_number.invalid_format', 'Invalid cesu_number.'));

        static::assertSame(2, $status);
        static::assertSame("Invalid cesu_number. (cesu_number.invalid_format)\n", $output->fetch());
    }

    #[DataProvider('failures')]
    public function testAnyOtherPackageFailureIsAFailureWithItsMessage(Throwable $failure, string $message): void
    {
        $output = new BufferedOutput();

        $status = CommandReports::guard($output, static fn(): int => throw $failure);

        static::assertSame(Command::FAILURE, $status);
        static::assertSame($message . "\n", $output->fetch());
    }

    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'record not found' => [RecordNotFound::withIdentifier('declaration_record', 'x'), RecordNotFound::withIdentifier('declaration_record', 'x')->getMessage()];
        yield 'authentication failed' => [UrssafAuthenticationFailed::withStatus(401), UrssafAuthenticationFailed::withStatus(401)->getMessage()];
        yield 'invariant' => [InvariantViolation::because('workflow_policy.invalid_limit', 'A limit.'), 'A limit.'];
    }

    public function testAForeignFailureIsNotCaught(): void
    {
        $this->expectException(RuntimeException::class);

        CommandReports::guard(new BufferedOutput(), static fn(): int => throw new RuntimeException('Bug.'));
    }

    public function testAFailedItemCarriesAStableCode(): void
    {
        $domain = CommandReports::failureItem('record-1', RecordNotFound::withIdentifier('declaration_record', 'x'));
        $retryable = CommandReports::failureItem('record-2', RetryableUrssafFailure::causedBy(RateLimitExceeded::forOperation(Operation::VerifyEmployer, 5)));
        $other = CommandReports::failureItem('record-3', UrssafAuthenticationFailed::withStatus(401));

        static::assertEquals(new BatchItem('record-1', ItemOutcome::Failed, ['declaration_record.not_found']), $domain);
        static::assertEquals(new BatchItem('record-2', ItemOutcome::Failed, ['urssaf.unavailable']), $retryable);
        static::assertEquals(new BatchItem('record-3', ItemOutcome::Failed, [UrssafAuthenticationFailed::class]), $other);
    }

    public function testAReportIsATableFollowedByTheCountOfEachOutcome(): void
    {
        $output = new BufferedOutput();
        $report = BatchReport::of(
            new BatchItem('record-1', ItemOutcome::Changed),
            new BatchItem('record-2', ItemOutcome::Unchanged, ['a', 'b']),
            new BatchItem('record-3', ItemOutcome::Changed),
        );

        static::assertSame(0, CommandReports::renderReport($output, $report));

        $display = $output->fetch();
        static::assertSame([['Record', 'Outcome', 'Codes'], ['record-1', 'changed', ''], ['record-2', 'unchanged', 'a, b'], ['record-3', 'changed', '']], Output::rows($display));
        static::assertStringEndsWith("3 record(s) processed (changed: 2, unchanged: 1).\n", $display);
    }

    public function testAnEmptyReportHasNoCounts(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderReport($output, BatchReport::of()));

        static::assertStringEndsWith("0 record(s) processed.\n", $output->fetch());
    }

    #[DataProvider('failedOutcomes')]
    public function testAReportWithAFailureExitsWithOne(ItemOutcome $outcome): void
    {
        $output = new BufferedOutput();

        static::assertSame(1, CommandReports::renderReport($output, BatchReport::of(new BatchItem('record-1', ItemOutcome::Changed), new BatchItem('record-2', $outcome))));
    }

    /**
     * @return iterable<string, array{ItemOutcome}>
     */
    public static function failedOutcomes(): iterable
    {
        yield 'failed' => [ItemOutcome::Failed];
        yield 'invalid credentials' => [ItemOutcome::InvalidCredentials];
        yield 'manual review' => [ItemOutcome::ManualReview];
    }

    #[DataProvider('successfulOutcomes')]
    public function testAReportWithoutFailureExitsWithZero(ItemOutcome $outcome): void
    {
        static::assertSame(0, CommandReports::renderReport(new BufferedOutput(), BatchReport::of(new BatchItem('record-1', $outcome))));
    }

    /**
     * @return iterable<string, array{ItemOutcome}>
     */
    public static function successfulOutcomes(): iterable
    {
        foreach (ItemOutcome::cases() as $outcome) {
            if (\in_array($outcome, [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::ManualReview], true)) {
                continue;
            }

            yield $outcome->value => [$outcome];
        }
    }

    public function testBusyIsNotAFailure(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderBusy($output, 'urssaf_cesu.outbox.relay.*'));

        static::assertSame("Already running (lock \"urssaf_cesu.outbox.relay.*\" is held): skipped.\n", $output->fetch());
    }

    public function testAPlanListsTheRecordsAndSaysNothingWasCalled(): void
    {
        $output = new BufferedOutput();

        static::assertSame(0, CommandReports::renderPlan($output, [['tenant-1', 'record-1'], ['tenant-2', 'record-2']]));

        $display = $output->fetch();
        static::assertSame([['Tenant', 'Record'], ['tenant-1', 'record-1'], ['tenant-2', 'record-2']], Output::rows($display));
        static::assertStringEndsWith("Dry run: 2 record(s) would be processed, URSSAF was not called.\n", $display);
    }

    public function testAViewIsATwoColumnTable(): void
    {
        $output = new BufferedOutput();

        CommandReports::renderFields($output, [['Account id', 'a-1'], ['Stage', 'registered']]);

        static::assertSame([['Field', 'Value'], ['Account id', 'a-1'], ['Stage', 'registered']], Output::rows($output->fetch()));
    }

    public function testTheValuesAreFormattedForTheOperator(): void
    {
        static::assertSame('yes', CommandReports::yesNo(true));
        static::assertSame('no', CommandReports::yesNo(false));
        static::assertSame('-', CommandReports::yesNo(null));
        static::assertSame('value', CommandReports::orDash('value'));
        static::assertSame('', CommandReports::orDash(''));
        static::assertSame('-', CommandReports::orDash(null));
        static::assertSame('-', CommandReports::euros(null));
        static::assertSame('0.00', CommandReports::euros(0));
        static::assertSame('0.05', CommandReports::euros(5));
        static::assertSame('0.50', CommandReports::euros(50));
        static::assertSame('63.10', CommandReports::euros(6310));
        static::assertSame('12345.67', CommandReports::euros(1_234_567));
    }
}
