<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Scheduler;

use Override;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The crons of the package as a Symfony Scheduler schedule: each task runs the console commands through Messenger
 * ({@see RunCommandMessage}), exactly like the crontab of the documentation (same locks, same safety nets).
 * "urssaf:cesu:requeue-invalid-credentials" is not scheduled: it is run by hand after fixing the credentials.
 * Adapted from the reference package, lot 6 design 7.4.
 */
final readonly class UrssafScheduleProvider implements ScheduleProviderInterface
{
    /** Console commands of each task of the "scheduler.frequencies" option. */
    public const TASKS = [
        'sync_employer_statuses' => ['urssaf:cesu:employers:sync-status'],
        'reconcile_stale_declarations' => ['urssaf:cesu:declarations:reconcile-stale'],
        'relay_outbox' => ['urssaf:cesu:outbox:relay'],
        'purge_outbox' => ['urssaf:cesu:outbox:purge'],
    ];

    /**
     * @param array<string, ?string> $frequencies ISO 8601 duration of each task, null to leave it out
     */
    public function __construct(private array $frequencies) {}

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        foreach (self::TASKS as $task => $commands) {
            $frequency = $this->frequencies[$task] ?? null;
            if ($frequency === null) {
                continue;
            }
            foreach ($commands as $command) {
                $schedule->add(RecurringMessage::every($frequency, new RunCommandMessage($command)));
            }
        }

        return $schedule;
    }
}
