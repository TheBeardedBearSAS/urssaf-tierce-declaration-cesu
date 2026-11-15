<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;

final class ErrorPayloadParserStrictTest extends TestCase
{
    public function testParsePayloadIsStrictOnUnreadableStructures(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new ErrorPayloadParser())->parsePayload(JsonDecoder::decode('{"errors":"text"}'));
    }

    public function testParsePayloadIsStrictOnAListOfScalars(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new ErrorPayloadParser())->parsePayload(JsonDecoder::decode('[1, 2]'));
    }

    public function testParseEntriesWorksOnAnAlreadyExtractedList(): void
    {
        $entries = JsonDecoder::decode('{"errors":[{"code":"A"},{"code":" B "}]}')->items('errors');

        static::assertSame(['A', 'B'], (new ErrorPayloadParser())->parseEntries($entries)->codes());
    }
}
