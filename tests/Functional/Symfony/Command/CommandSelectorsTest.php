<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandSelectors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

final class CommandSelectorsTest extends TestCase
{
    public function testTheSelectorOptionsAreDeclared(): void
    {
        $command = new Command('test');

        CommandSelectors::addEmployerSelector($command);
        CommandSelectors::addDeclarationSelector($command);

        static::assertSame(['employer', 'cesu-number', 'account-id', 'declaration', 'record-id'], array_keys($command->getDefinition()->getOptions()));
        foreach ($command->getDefinition()->getOptions() as $option) {
            static::assertTrue($option->isValueRequired());
        }
    }

    public function testAnEmployerIsDesignatedByItsExternalReference(): void
    {
        $expected = EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1'));

        static::assertEquals($expected, CommandSelectors::employerSelector(self::input(['--employer' => 'employer-1'])));
        static::assertEquals($expected, CommandSelectors::optionalEmployerSelector(self::input(['--employer' => 'employer-1'])));
    }

    public function testAnEmployerIsDesignatedByItsCesuNumber(): void
    {
        $expected = EmployerSelector::byCesuNumber(CesuNumber::fromString('Z0003752390009'));

        static::assertEquals($expected, CommandSelectors::employerSelector(self::input(['--cesu-number' => 'Z0003752390009'])));
        static::assertEquals($expected, CommandSelectors::optionalEmployerSelector(self::input(['--cesu-number' => 'Z0003752390009'])));
    }

    public function testAnEmployerIsDesignatedByItsAccountId(): void
    {
        $expected = EmployerSelector::byAccountId(EmployerAccountId::fromString('account-1'));

        static::assertEquals($expected, CommandSelectors::employerSelector(self::input(['--account-id' => 'account-1'])));
        static::assertEquals($expected, CommandSelectors::optionalEmployerSelector(self::input(['--account-id' => 'account-1'])));
    }

    public function testTheOptionalEmployerSelectorIsNullWhenNoneIsGiven(): void
    {
        static::assertNull(CommandSelectors::optionalEmployerSelector(self::input([])));
    }

    public function testTheRequiredEmployerSelectorRefusesNone(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give one of --employer, --cesu-number or --account-id.');

        CommandSelectors::employerSelector(self::input([]));
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('severalEmployerOptions')]
    public function testSeveralEmployerOptionsAreRefused(array $options): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give one of --employer, --cesu-number or --account-id.');

        CommandSelectors::optionalEmployerSelector(self::input($options));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function severalEmployerOptions(): iterable
    {
        yield 'the first two' => [['--employer' => 'a', '--cesu-number' => 'Z0003752390009']];
        yield 'the first and the last' => [['--employer' => 'a', '--account-id' => 'b']];
        yield 'the last two' => [['--cesu-number' => 'Z0003752390009', '--account-id' => 'b']];
        yield 'three' => [['--employer' => 'a', '--cesu-number' => 'Z0003752390009', '--account-id' => 'b']];
    }

    public function testAnInvalidValueIsRefusedByTheValueObject(): void
    {
        $this->expectException(InvalidValue::class);

        CommandSelectors::employerSelector(self::input(['--cesu-number' => 'short']));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function input(array $arguments): ArrayInput
    {
        $command = new Command('test');
        CommandSelectors::addEmployerSelector($command);
        CommandSelectors::addDeclarationSelector($command);

        return new ArrayInput($arguments, $command->getDefinition());
    }
}
