<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;

final class ApiRequestTest extends TestCase
{
    public function testGetCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::get(Operation::VerifyEmployee, '/salaries', ['a' => 'b']);

        static::assertSame(Operation::VerifyEmployee, $request->operation());
        static::assertSame('GET', $request->method());
        static::assertSame('/salaries', $request->path());
        static::assertSame(['a' => 'b'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testPostCarriesABodyAndAnEmptyQuery(): void
    {
        $request = ApiRequest::post(Operation::RegisterEmployer, '/employeurs', [], '{"k":1}');

        static::assertSame(Operation::RegisterEmployer, $request->operation());
        static::assertSame('POST', $request->method());
        static::assertSame('/employeurs', $request->path());
        static::assertSame([], $request->query());
        static::assertSame('{"k":1}', $request->jsonBody());
    }

    public function testPostCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::post(Operation::RegisterMandate, '/mandats', ['numeroCesu' => '1'], null);

        static::assertSame('POST', $request->method());
        static::assertSame(['numeroCesu' => '1'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testDeleteCarriesAQueryAndNoBody(): void
    {
        $request = ApiRequest::delete(Operation::CancelMandate, '/mandats', ['numeroCesu' => '1']);

        static::assertSame(Operation::CancelMandate, $request->operation());
        static::assertSame('DELETE', $request->method());
        static::assertSame('/mandats', $request->path());
        static::assertSame(['numeroCesu' => '1'], $request->query());
        static::assertNull($request->jsonBody());
    }

    public function testDebugInfoShowsQueryNamesOnly(): void
    {
        $request = ApiRequest::post(
            Operation::RegisterMandate,
            '/mandats',
            ['nom' => 'FICTIF', 'dateNaissance' => '1990-01-01'],
            '{"iban":"x"}',
        );

        static::assertSame(
            [
                'operation' => Operation::RegisterMandate->methodNumber(),
                'method' => 'POST',
                'path' => '/mandats',
                'query' => ['nom', 'dateNaissance'],
                'jsonBody' => '***',
            ],
            $request->__debugInfo(),
        );
        static::assertStringNotContainsString('FICTIF', var_export($request->__debugInfo(), true));
    }

    public function testDebugInfoOfARequestWithoutBodyNorQuery(): void
    {
        $request = ApiRequest::delete(Operation::CancelMandate, '/mandats', []);

        static::assertSame(
            [
                'operation' => Operation::CancelMandate->methodNumber(),
                'method' => 'DELETE',
                'path' => '/mandats',
                'query' => [],
                'jsonBody' => null,
            ],
            $request->__debugInfo(),
        );
    }
}
