<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;

final class PayloadNumberTest extends TestCase
{
    public function testNumberReturnsIntegersAndFloatsAndNullWhenAbsent(): void
    {
        $payload = JsonDecoder::decode('{"i":2000,"f":63.1,"n":null}');

        static::assertSame(2000, $payload->number('i'));
        static::assertSame(63.1, $payload->number('f'));
        static::assertNull($payload->number('n'));
        static::assertNull($payload->number('missing'));
    }

    public function testNumberRejectsOtherTypes(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "s" is not a number.');

        JsonDecoder::decode('{"s":"12"}')->number('s');
    }

    public function testRequireNumberReadsIntegersAndFloats(): void
    {
        $payload = JsonDecoder::decode('{"i":2000,"f":63.1,"z":0}');

        static::assertSame(2000, $payload->requireNumber('i'));
        static::assertSame(63.1, $payload->requireNumber('f'));
        static::assertSame(0, $payload->requireNumber('z'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingNumbers(): iterable
    {
        yield 'absent' => ['{}'];
        yield 'null' => ['{"n":null}'];
        yield 'string' => ['{"n":"12"}'];
        yield 'boolean' => ['{"n":false}'];
    }

    #[DataProvider('missingNumbers')]
    public function testRequireNumberRejectsOtherValues(string $json): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('Unexpected URSSAF response: field "n" is missing or not a number.');

        JsonDecoder::decode($json)->requireNumber('n');
    }
}
