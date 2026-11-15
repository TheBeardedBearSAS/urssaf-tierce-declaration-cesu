<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;

/**
 * Execution guard and output of the console commands. Nothing personal is printed (ADR 0007): identifiers, codes,
 * states, amounts and instants only.
 *
 * Source: reference CommandReports, lot 6 design §5.2, §5.3.
 */
final class CommandReports
{
    /** Outcomes that make a batch command exit with a failure status. */
    private const FAILURES = [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::ManualReview];

    /**
     * Runs a command body. An invalid usage (option, input refused by the domain) prints its errors and gives
     * Command::INVALID (2); any other package failure prints its message and gives Command::FAILURE (1).
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     */
    public static function guard(OutputInterface $output, callable $action): int
    {
        try {
            return $action();
        } catch (InvalidOptionException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::INVALID;
        } catch (ValidationFailed $failure) {
            $errors = $failure->result()->errors();
            array_walk($errors, static fn(Violation $error) => $output->writeln(\sprintf('<error>%s: %s (%s)</error>', $error->path(), $error->message(), $error->code())));
            $output->writeln(\sprintf('<error>Invalid input: %d error(s).</error>', \count($errors)));

            return Command::INVALID;
        } catch (InvalidValue $failure) {
            $output->writeln(\sprintf('<error>%s (%s)</error>', $failure->getMessage(), $failure->violationCode()));

            return Command::INVALID;
        } catch (UrssafException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Failed item for a record, with a stable code describing the failure.
     */
    public static function failureItem(string $subject, UrssafException $failure): BatchItem
    {
        $code = match (true) {
            $failure instanceof DomainException => $failure->violationCode(),
            $failure instanceof RetryableException => 'urssaf.unavailable',
            default => $failure::class,
        };

        return new BatchItem($subject, ItemOutcome::Failed, [$code]);
    }

    /**
     * Prints the items of a report as a table followed by the count of each outcome.
     */
    public static function renderReport(OutputInterface $output, BatchReport $report): int
    {
        $table = new Table($output);
        $table->setHeaders(['Record', 'Outcome', 'Codes']);
        $table->setRows(array_map(
            static fn(BatchItem $item): array => [$item->subject, $item->outcome->value, implode(', ', $item->codes)],
            $report->items(),
        ));
        $table->render();

        $counts = array_map(
            static fn(ItemOutcome $outcome): string => \sprintf('%s: %d', $outcome->value, $report->count($outcome)),
            array_filter(ItemOutcome::cases(), static fn(ItemOutcome $outcome): bool => $report->count($outcome) > 0),
        );
        $output->writeln(\sprintf('%d record(s) processed%s.', $report->total(), $counts === [] ? '' : ' (' . implode(', ', $counts) . ')'));

        $failed = array_filter(self::FAILURES, static fn(ItemOutcome $outcome): bool => $report->count($outcome) > 0);

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Tells that the same operation is already running (its lock is held): nothing is done, which is not a failure.
     */
    public static function renderBusy(OutputInterface $output, string $resource): int
    {
        $output->writeln(\sprintf('<comment>Already running (lock "%s" is held): skipped.</comment>', $resource));

        return Command::SUCCESS;
    }

    /**
     * Prints what a dry run would process.
     *
     * @param list<array{string, string}> $rows tenant id and record identifier
     */
    public static function renderPlan(OutputInterface $output, array $rows): int
    {
        $table = new Table($output);
        $table->setHeaders(['Tenant', 'Record']);
        $table->setRows($rows);
        $table->render();
        $output->writeln(\sprintf('Dry run: %d record(s) would be processed, URSSAF was not called.', \count($rows)));

        return Command::SUCCESS;
    }

    /**
     * Prints a view as a two-column table.
     *
     * @param list<array{string, string}> $rows field and value
     */
    public static function renderFields(OutputInterface $output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Field', 'Value']);
        $table->setRows($rows);
        $table->render();
    }

    public static function yesNo(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '-',
        };
    }

    public static function orDash(?string $value): string
    {
        return $value ?? '-';
    }

    /**
     * An amount in cents as euros ("63.10"), "-" when unknown.
     */
    public static function euros(?int $cents): string
    {
        return $cents === null ? '-' : \sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
