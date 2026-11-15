<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class EmailAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidAddresses(): iterable
    {
        yield 'official example' => ['pierre.durand@example.com'];
        yield 'minimal' => ['a@b.co'];
        yield 'upper case kept' => ['Pierre.Durand@Example.COM'];
        yield 'dash and underscore in local part' => ['pierre_du-rand@example.com'];
        yield 'leading dash in local part' => ['-a@example.com'];
        yield 'subdomains' => ['a@mail.sub.example.fr'];
        yield 'dash in domain' => ['a@my-domain.fr'];
        yield 'digits in domain' => ['a@d0main.fr'];
        yield 'maximum length' => [str_repeat('a', 251) . '@b.fr'];
    }

    #[DataProvider('provideValidAddresses')]
    public function testValidAddressesAreKeptVerbatim(string $value): void
    {
        $email = EmailAddress::fromString($value);

        static::assertSame($value, $email->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['pierre.durand.example.com'];
        yield 'no local part' => ['@example.com'];
        yield 'no domain' => ['a@'];
        yield 'no top level domain' => ['a@example'];
        yield 'digit top level domain' => ['a@example.c0m'];
        yield 'dash top level domain' => ['a@example.c-m'];
        yield 'leading dash in domain' => ['a@-example.com'];
        yield 'trailing dash in domain' => ['a@example-.com'];
        yield 'double dot in domain' => ['a@example..com'];
        yield 'double dot in local part' => ['a..b@example.com'];
        yield 'leading dot in local part' => ['.a@example.com'];
        yield 'trailing dot in local part' => ['a.@example.com'];
        yield 'two at signs' => ['a@b@example.com'];
        yield 'space inside' => ['a b@example.com'];
        yield 'leading space' => [' a@example.com'];
        yield 'trailing space' => ['a@example.com '];
        yield 'trailing newline' => ["a@example.com\n"];
        yield 'leading newline' => ["\na@example.com"];
        yield 'accent in local part' => ['é@example.com'];
        yield 'plus sign' => ['a+b@example.com'];
        yield 'multibyte at maximum characters' => [str_repeat('é', 256)];
    }

    #[DataProvider('provideInvalidAddresses')]
    public function testInvalidAddressesAreRejected(string $value): void
    {
        $exception = DomainAssertions::assertViolationCode('email_address.invalid_format', static fn(): EmailAddress => EmailAddress::fromString($value));

        static::assertSame(['expected_format' => 'contract e-mail pattern'], $exception->params());
    }

    public function testInvalidFormatMessage(): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('email_address', 'contract e-mail pattern'));

        EmailAddress::fromString('nope');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTooLongAddresses(): iterable
    {
        yield 'valid shape' => [str_repeat('a', 252) . '@b.fr'];
        yield 'multibyte' => [str_repeat('é', 257)];
        yield 'invalid shape wins' => [str_repeat(' ', 257)];
    }

    #[DataProvider('provideTooLongAddresses')]
    public function testTooLongAddressesAreRejectedBeforeTheFormatCheck(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('email_address', 256));

        EmailAddress::fromString($value);
    }

    public function testEquality(): void
    {
        $email = EmailAddress::fromString('a@example.com');

        static::assertTrue($email->equals(EmailAddress::fromString('a@example.com')));
        static::assertFalse($email->equals(EmailAddress::fromString('A@example.com')));
        static::assertFalse($email->equals(EmailAddress::fromString('b@example.com')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $email = EmailAddress::fromString('pierre.durand@example.com');

        static::assertSame(['value' => '***'], $email->__debugInfo());
        static::assertStringNotContainsString('pierre', print_r($email, true));
    }
}
