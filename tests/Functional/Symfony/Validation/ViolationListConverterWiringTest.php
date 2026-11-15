<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * Reference ViolationListConverterWiringTest (lot 6 design §8.1).
 */
final class ViolationListConverterWiringTest extends TestCase
{
    public function testTheConverterIsRegisteredWithTheOptionalTranslator(): void
    {
        // Compiled afresh: the booted test containers are cached by configuration, not by source code.
        $definition = (new TestKernel())->definitions()->getDefinition(ViolationListConverter::class);

        static::assertTrue($definition->isPublic());
        static::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $definition->getArguments());
    }

    public function testTheConverterIsPublicAndTranslatesWithTheShippedMessages(): void
    {
        $app = Harness::boot();
        $app->privateService('translator', LocaleAwareInterface::class)->setLocale('fr');
        $result = ValidationResult::of(Violation::error('employmentPeriod.startDate', 'employment_period.starts_after_today', 'The employment period must not start after today.'));

        $violation = $app->get(ViolationListConverter::class)->convert($result, 'declaration')->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('La période d\'emploi ne doit pas commencer après aujourd\'hui.', $violation->getMessage());
        static::assertSame('declaration.employmentPeriod.startDate', $violation->getPropertyPath());
    }
}
