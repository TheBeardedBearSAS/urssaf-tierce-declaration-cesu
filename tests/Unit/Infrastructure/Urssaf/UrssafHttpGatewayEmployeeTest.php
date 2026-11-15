<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayEmployeeTest extends TestCase
{
    /**
     * @return iterable<string, array{EmployeeReference, array<string, string>|null}>
     */
    public static function references(): iterable
    {
        yield 'cesu number' => [EmployeeSamples::byCesuNumber(), ['numeroCesu' => '00000123456789']];
        yield 'nir' => [EmployeeSamples::byNir(), ['nir' => EmployeeSamples::NIR]];
        yield 'identity' => [EmployeeSamples::byIdentity(), null];
    }

    /**
     * @param array<string, string>|null $expected null: the query of the identity variant
     */
    #[DataProvider('references')]
    public function testVerifyEmployee(EmployeeReference $reference, ?array $expected): void
    {
        $stack = Gateways::answering(200, Fixtures::load('verify_employee_response'));

        $result = Gateways::of($stack)->verifyEmployee(Stacks::tenant(), $reference);

        static::assertTrue($result->registered());
        static::assertSame('Z0003752390009', $result->cesuNumber()?->value());
        $request = Gateways::apiRequest($stack);
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/salaries',
            $request->getUri()->withQuery('')->__toString(),
        );
        static::assertSame($expected ?? QueryEncoding::employeeVerification($reference), Gateways::query($request));
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame(['tenant-1:verify_employee'], $stack->rateLimiter->acquired());
    }

    public function testAnUnreadableSuccessIsUnexpected(): void
    {
        $stack = Gateways::answering(200, 'not json');

        $this->expectException(UnexpectedUrssafResponse::class);

        Gateways::of($stack)->verifyEmployee(Stacks::tenant(), EmployeeSamples::byCesuNumber());
    }
}
