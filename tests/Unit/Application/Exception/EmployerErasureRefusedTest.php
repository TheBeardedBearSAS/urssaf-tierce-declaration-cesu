<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;

final class EmployerErasureRefusedTest extends TestCase
{
    public function testActiveMandate(): void
    {
        $exception = EmployerErasureRefused::activeMandate(EmployerAccountId::fromString('account-1'));

        static::assertSame('employer_account.forget_active_mandate', $exception->violationCode());
        static::assertSame(['employer_account_id' => 'account-1'], $exception->params());
        static::assertSame('The employer still has an active mandate: cancel it first (method 031) or allow its erasure explicitly.', $exception->getMessage());
    }

    public function testSubmissionInFlight(): void
    {
        $exception = EmployerErasureRefused::submissionInFlight(EmployerAccountId::fromString('account-1'), 2);

        static::assertSame('employer_account.forget_submission_in_flight', $exception->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 2], $exception->params());
        static::assertSame('2 declaration(s) of the employer are being submitted: wait for their outcome, then erase the employer.', $exception->getMessage());
    }

    public function testUnknownOutcomes(): void
    {
        $exception = EmployerErasureRefused::unknownOutcomes(EmployerAccountId::fromString('account-1'), 3);

        static::assertSame('employer_account.forget_unknown_outcome', $exception->violationCode());
        static::assertSame(['employer_account_id' => 'account-1', 'count' => 3], $exception->params());
        static::assertSame('3 declaration(s) of the employer have an unknown outcome: resolve them first or allow their erasure explicitly.', $exception->getMessage());
    }
}
