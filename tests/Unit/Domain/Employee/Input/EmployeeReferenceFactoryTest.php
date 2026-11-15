<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

final class EmployeeReferenceFactoryTest extends TestCase
{
    private const CESU = '00000123456789';

    public function testByCesuNumber(): void
    {
        $draft = new EmployeeReferenceDraft(self::CESU, null, null);

        foreach ([EmployeeReferenceFactory::forLookup(), EmployeeReferenceFactory::forDeclaration()] as $factory) {
            $reference = $factory->fromDraft($draft);

            static::assertInstanceOf(EmployeeByCesuNumber::class, $reference);
            static::assertSame(self::CESU, $reference->cesuNumber()->value());
            static::assertSame([], DomainAssertions::summary($factory->validate($draft)));
        }
    }

    public function testEmptyStringsCountAsMissing(): void
    {
        $draft = new EmployeeReferenceDraft(self::CESU, '', new EmployeeIdentityDraft('', '', '', '', '', '', ''));

        static::assertInstanceOf(EmployeeByCesuNumber::class, EmployeeReferenceFactory::forDeclaration()->fromDraft($draft));
    }

    public function testByNirAlone(): void
    {
        $draft = new EmployeeReferenceDraft(null, EmployeeSamples::NIR, null);

        foreach ([EmployeeReferenceFactory::forLookup(), EmployeeReferenceFactory::forDeclaration()] as $factory) {
            $reference = $factory->fromDraft($draft);

            static::assertInstanceOf(EmployeeByNir::class, $reference);
            static::assertSame(EmployeeSamples::NIR, $reference->nir()->value());
            static::assertNull($reference->identity());
        }
    }

    public function testByNirWithIdentityForADeclaration(): void
    {
        $reference = EmployeeReferenceFactory::forDeclaration()->fromDraft(
            new EmployeeReferenceDraft(null, EmployeeSamples::NIR, EmployeeSamples::identityDraft()),
        );

        static::assertInstanceOf(EmployeeByNir::class, $reference);
        static::assertTrue($reference->identity()?->equals(EmployeeSamples::identity()));
    }

    public function testByNirWithAPartialIdentityForADeclaration(): void
    {
        $reference = EmployeeReferenceFactory::forDeclaration()->fromDraft(
            new EmployeeReferenceDraft(null, EmployeeSamples::NIR, new EmployeeIdentityDraft('Martin', null, 'Julie', null, null, null, '212')),
        );

        static::assertInstanceOf(EmployeeByNir::class, $reference);
        $identity = $reference->identity();
        static::assertNotNull($identity);
        static::assertNull($identity->birthDate());
        static::assertNull($identity->birthCity());
    }

    public function testByIdentity(): void
    {
        $reference = EmployeeReferenceFactory::forLookup()->fromDraft(new EmployeeReferenceDraft(null, null, EmployeeSamples::identityDraft()));

        static::assertInstanceOf(EmployeeByIdentity::class, $reference);
        static::assertTrue($reference->identity()->equals(EmployeeSamples::identity()));
        static::assertInstanceOf(
            EmployeeByIdentity::class,
            EmployeeReferenceFactory::forDeclaration()->fromDraft(new EmployeeReferenceDraft('', '', EmployeeSamples::identityDraft())),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProviderExternal(InvalidEmployeeReferenceDrafts::class, 'provideInvalidLookupDrafts')]
    #[DataProviderExternal(InvalidEmployeeReferenceDrafts::class, 'provideInvalidDeclarationDrafts')]
    public function testInvalidDrafts(EmployeeReferenceFactory $factory, EmployeeReferenceDraft $draft, array $expected): void
    {
        $collector = new ViolationCollector();

        $reference = $factory->parse($draft, $collector);

        static::assertSame($expected, DomainAssertions::summary($collector->result()));
        static::assertSame($expected, DomainAssertions::summary($factory->validate($draft)));
        if ($expected === []) {
            static::assertNotNull($reference);
            static::assertInstanceOf(EmployeeByIdentity::class, $factory->fromDraft($draft));

            return;
        }
        static::assertNull($reference);
        try {
            $factory->fromDraft($draft);
            static::fail('Expected a ValidationFailed.');
        } catch (ValidationFailed $failed) {
            static::assertSame($expected, DomainAssertions::summary($failed->result()));
        }
    }

    public function testMessages(): void
    {
        $factory = EmployeeReferenceFactory::forLookup();
        $messages = static fn(EmployeeReferenceDraft $draft): array => array_map(
            static fn(Violation $violation): string => $violation->message(),
            $factory->validate($draft)->errors(),
        );

        static::assertSame(
            ['An employee is identified by a Cesu number or a NIR, not both.'],
            $messages(new EmployeeReferenceDraft(self::CESU, EmployeeSamples::NIR, null)),
        );
        static::assertSame(
            ['The administrative data must not be given with an employee Cesu number.'],
            $messages(new EmployeeReferenceDraft(self::CESU, null, EmployeeSamples::identityDraft())),
        );
        static::assertSame(
            ['The administrative data must not be given with a NIR.'],
            $messages(new EmployeeReferenceDraft(null, EmployeeSamples::NIR, EmployeeSamples::identityDraft())),
        );
        static::assertSame(
            ['The birth department is required for an employee born in France.'],
            $messages(new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', '1990-05-12', 'Lyon', null, '100'))),
        );
        static::assertSame(
            ['The birth date is required.', 'The birth city is required.'],
            $messages(new EmployeeReferenceDraft(null, null, new EmployeeIdentityDraft('Martin', null, 'Julie', null, null, null, '212'))),
        );
    }

    public function testPreviousErrorsOfTheCollectorAreKept(): void
    {
        $collector = new ViolationCollector();
        EmployeeReferenceFactory::forLookup()->parse(new EmployeeReferenceDraft('1', null, null), $collector);

        $reference = EmployeeReferenceFactory::forLookup()->parse(new EmployeeReferenceDraft(self::CESU, null, null), $collector);

        static::assertNotNull($reference);
        static::assertCount(1, $collector->result()->errors());
    }
}
