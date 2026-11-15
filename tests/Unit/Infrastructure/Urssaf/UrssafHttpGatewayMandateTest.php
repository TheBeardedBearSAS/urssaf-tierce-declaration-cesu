<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayMandateTest extends TestCase
{
    public function testRegisterMandate(): void
    {
        $stack = Gateways::answering(200, '');

        Gateways::of($stack)->registerMandate(Stacks::tenant(), GatewaySamples::employer());

        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/mandats?numeroCesu=Z0003752390009&dateNaissance=1975-01-30',
            (string) $request->getUri(),
        );
        static::assertSame('', (string) $request->getBody());
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame(['tenant-1:register_mandate'], $stack->rateLimiter->acquired());
    }

    public function testCancelMandate(): void
    {
        $stack = Gateways::answering(200, '{}');

        Gateways::of($stack)->cancelMandate(Stacks::tenant(), GatewaySamples::employer());

        $request = Gateways::apiRequest($stack);
        static::assertSame('DELETE', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/mandats?numeroCesu=Z0003752390009&dateNaissance=1975-01-30',
            (string) $request->getUri(),
        );
        static::assertSame('', (string) $request->getBody());
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame(['tenant-1:cancel_mandate'], $stack->rateLimiter->acquired());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function acceptedAnswers(): iterable
    {
        yield 'empty body' => [200, ''];
        yield 'blank body' => [200, "  \n"];
        yield 'empty object' => [201, '{}'];
    }

    #[DataProvider('acceptedAnswers')]
    public function testAnEmptyAnswerIsAccepted(int $status, string $body): void
    {
        $register = Gateways::answering($status, $body);
        $cancel = Gateways::answering($status, $body);

        Gateways::of($register)->registerMandate(Stacks::tenant(), GatewaySamples::employer());
        Gateways::of($cancel)->cancelMandate(Stacks::tenant(), GatewaySamples::employer());

        static::assertCount(2, $register->requests());
        static::assertCount(2, $cancel->requests());
    }

    public function testANonEmptyAnswerIsUnexpectedAndNeverAnUnknownOutcome(): void
    {
        $stack = Gateways::answering(200, '{"a":1}');

        try {
            Gateways::of($stack)->registerMandate(Stacks::tenant(), GatewaySamples::employer());
            static::fail('An unexpected response was expected.');
        } catch (UnexpectedUrssafResponse $unexpected) {
            static::assertSame('Unexpected URSSAF response: expected an empty body.', $unexpected->getMessage());
        }

        $cancel = Gateways::answering(200, '[1]');

        $this->expectException(UnexpectedUrssafResponse::class);

        Gateways::of($cancel)->cancelMandate(Stacks::tenant(), GatewaySamples::employer());
    }

    public function testAMandateHeldElsewhereIsClassified(): void
    {
        $stack = Gateways::answering(400, Fixtures::load('error_compte_deja_mandate'));

        try {
            Gateways::of($stack)->registerMandate(Stacks::tenant(), GatewaySamples::employer());
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $rejected) {
            static::assertTrue($rejected->isMandateHeldElsewhere());
        }
    }

    public function testATechnicalErrorIsRetryableNotUnknown(): void
    {
        $stack = Gateways::answering(500, Fixtures::load('error_technical_mandate'));

        try {
            Gateways::of($stack)->registerMandate(Stacks::tenant(), GatewaySamples::employer());
            static::fail('An unavailable service was expected.');
        } catch (UrssafUnavailable $unavailable) {
            static::assertSame(500, $unavailable->httpStatus());
            static::assertTrue($unavailable->errors()->hasCode('ERREUR_TECHNIQUE'));
        }
    }
}
