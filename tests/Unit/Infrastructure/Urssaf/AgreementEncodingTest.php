<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\AgreementEncoding;

final class AgreementEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{AgreementEncoding, bool, string}>
     */
    public static function encodings(): iterable
    {
        yield 'boolean true' => [AgreementEncoding::Boolean, true, 'true'];
        yield 'boolean false' => [AgreementEncoding::Boolean, false, 'false'];
        yield 'numeric true' => [AgreementEncoding::Numeric, true, '1'];
        yield 'numeric false' => [AgreementEncoding::Numeric, false, '0'];
    }

    #[DataProvider('encodings')]
    public function testItEncodesTheAgreement(AgreementEncoding $encoding, bool $agreement, string $expected): void
    {
        static::assertSame($expected, $encoding->encode($agreement));
    }

    public function testBundleValues(): void
    {
        static::assertSame('boolean', AgreementEncoding::Boolean->value);
        static::assertSame('numeric', AgreementEncoding::Numeric->value);
        static::assertSame(AgreementEncoding::Numeric, AgreementEncoding::from('numeric'));
        static::assertCount(2, AgreementEncoding::cases());
    }
}
