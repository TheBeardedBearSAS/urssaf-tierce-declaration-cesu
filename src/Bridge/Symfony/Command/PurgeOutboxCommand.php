<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use DateInterval;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;

/**
 * Deletes the outbox entries dispatched for longer than the retention ("outbox.retention").
 *
 * Source: reference PurgeOutboxCommand, lot 6 design §5.4.
 */
#[AsCommand(name: 'urssaf:cesu:outbox:purge', description: 'Delete the outbox entries dispatched for longer than the retention')]
final class PurgeOutboxCommand extends Command
{
    public function __construct(private readonly PurgeOutboxHandler $purge, private readonly DateInterval $retention)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($output): int {
            $output->writeln(\sprintf('%d dispatched outbox entries deleted.', ($this->purge)(new PurgeOutbox($this->retention))));

            return Command::SUCCESS;
        });
    }
}
