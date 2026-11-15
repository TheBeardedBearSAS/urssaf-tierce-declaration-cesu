<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Lock;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\RetryDelay;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class LockedHandlersTest extends TestCase
{
    /**
     * @param Closure(Harness): void $handle
     */
    #[DataProvider('messages')]
    public function testAHandlerAsksForARetryWhenItsOperationIsAlreadyRunning(Closure $handle, string $resource): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], false, true);
        Scenario::importedEmployer($harness);
        $held = HeldLocks::hold($harness, $resource);

        try {
            $handle($harness);
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertSame(0, $retry->getCode());
            static::assertStringContainsString(\sprintf('lock "%s" is held', $resource), $retry->getMessage());
        }
        static::assertSame([], $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{Closure(Harness): void, string}>
     */
    public static function messages(): iterable
    {
        yield 'sync the employers of a tenant' => [
            static function (Harness $harness): void {
                $harness->privateService(SyncEmployerStatusesMessageHandler::class, SyncEmployerStatusesMessageHandler::class)(new SyncEmployerStatusesMessage('tenant-1'));
            },
            'urssaf_cesu.employers.sync-status.tenant-1',
        ];
        yield 'sync one employer' => [
            static function (Harness $harness): void {
                $harness->privateService(SyncEmployerStatusMessageHandler::class, SyncEmployerStatusMessageHandler::class)(new SyncEmployerStatusMessage('tenant-1', 'account-1'));
            },
            'urssaf_cesu.employer.sync-status.account-1',
        ];
    }
}
