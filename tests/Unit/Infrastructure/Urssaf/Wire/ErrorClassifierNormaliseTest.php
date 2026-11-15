<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;

final class ErrorClassifierNormaliseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalisations(): iterable
    {
        yield 'curly apostrophe' => ['l’emploi', "l'emploi"];
        yield 'doubled apostrophes' => ["l''emploi", "l'emploi"];
        yield 'tripled apostrophes' => ["l'''emploi", "l'emploi"];
        yield 'doubled curly apostrophes' => ['l’’emploi', "l'emploi"];
        yield 'whitespace runs' => ["a  b\t\nc\r d", 'a b c d'];
        yield 'no-break spaces' => ["a\u{00A0}\u{00A0}b", 'a b'];
        yield 'narrow no-break spaces' => ["a\u{202F}b\u{202F}\u{202F}c", 'a b c'];
        yield 'trim' => ["  \u{00A0}a b \n", 'a b'];
        yield 'lower case with accents' => ['ÉCOLE À PÉRIODE', 'école à période'];
        yield 'all steps' => ["  L’’ÉTAT  D'\u{00A0}OR\n", "l'état d' or"];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('normalisations')]
    public function testNormalise(string $text, string $expected): void
    {
        static::assertSame($expected, ErrorClassifier::normalise($text));
    }

    public function testTextsThatAreNotValidUtf8AreNotClassifiedAndDoNotFail(): void
    {
        static::assertNull(ErrorClassifier::classifyDescription(Operation::RegisterEmployer, "Veuillez indiquer un IBAN valide.\xFF"));
        static::assertSame('a  b?', ErrorClassifier::normalise("a  b\xFF"));
    }
}
