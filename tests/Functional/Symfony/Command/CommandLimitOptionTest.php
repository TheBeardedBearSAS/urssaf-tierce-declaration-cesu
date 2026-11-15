<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandOptions;

final class CommandLimitOptionTest extends TestCase
{
    #[DataProvider('limits')]
    public function testTheLimitIsAPositiveInteger(?string $value, ?int $expected): void
    {
        static::assertSame($expected, CommandOptions::limit(self::input($value === null ? [] : ['--limit' => $value])));
    }

    /**
     * @return iterable<string, array{?string, ?int}>
     */
    public static function limits(): iterable
    {
        yield 'none' => [null, null];
        yield 'one' => [ '1', 1];
        yield 'ten' => ['10', 10];
        yield 'the largest accepted' => ['999999999', 999_999_999];
    }

    #[DataProvider('invalidLimits')]
    public function testAnInvalidLimitIsRefused(string $value): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --limit option must be a positive integer.');

        CommandOptions::limit(self::input(['--limit' => $value]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'text' => ['many'];
        yield 'decimal' => ['1.5'];
        yield 'leading zero' => ['01'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["5\n"];
        yield 'leading text' => ['x5'];
        yield 'too large' => ['1000000000'];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function input(array $arguments): ArrayInput
    {
        $command = new Command('test');
        CommandOptions::addLimit($command);

        return new ArrayInput($arguments, $command->getDefinition());
    }
}
