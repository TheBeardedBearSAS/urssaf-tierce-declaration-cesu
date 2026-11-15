<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Catalogue of api-reference §3.13: wire value, documented operations and application meaning of every code.
 */
final class ApiErrorCodeCatalogueTest extends TestCase
{
    private const ALL = [
        Operation::RegisterEmployer,
        Operation::VerifyEmployer,
        Operation::VerifyEmployee,
        Operation::RegisterMandate,
        Operation::CancelMandate,
        Operation::EstimateContributions,
        Operation::DeclareSalary,
        Operation::ActivateCesuPlus,
        Operation::ActivateAici,
    ];
    private const BUSINESS = [
        Operation::RegisterEmployer,
        Operation::VerifyEmployer,
        Operation::EstimateContributions,
        Operation::DeclareSalary,
        Operation::ActivateCesuPlus,
        Operation::ActivateAici,
    ];
    private const TECHNICAL = [
        Operation::RegisterEmployer,
        Operation::VerifyEmployer,
        Operation::VerifyEmployee,
        Operation::EstimateContributions,
        Operation::DeclareSalary,
        Operation::ActivateCesuPlus,
        Operation::ActivateAici,
    ];
    private const REGISTER = [Operation::RegisterEmployer];
    private const DECLARE = [Operation::DeclareSalary];
    private const DECLARE_CESU_PLUS = [Operation::DeclareSalary, Operation::ActivateCesuPlus];
    private const MANDATES = [Operation::RegisterMandate, Operation::CancelMandate];

    /**
     * @return iterable<string, array{ApiErrorCode, string, list<Operation>, ErrorKind}>
     */
    public static function catalogue(): iterable
    {
        yield 'PARAM_INVALIDE' => [ApiErrorCode::InvalidParameter, 'PARAM_INVALIDE', self::ALL, ErrorKind::InvalidParameter];
        yield 'ERR_METIER' => [ApiErrorCode::Business, 'ERR_METIER', self::BUSINESS, ErrorKind::Business];
        yield 'SEPA_ABSENT' => [ApiErrorCode::SepaMissing, 'SEPA_ABSENT', self::REGISTER, ErrorKind::SepaMissing];
        yield 'MANDAT_INEXISTANT' => [ApiErrorCode::MandateMissing, 'MANDAT_INEXISTANT', self::DECLARE_CESU_PLUS, ErrorKind::MandateMissing];
        yield 'RIB_ABSENT' => [ApiErrorCode::RibMissing, 'RIB_ABSENT', self::DECLARE, ErrorKind::RibMissing];
        yield 'COMPTE_DEJA_MANDATE' => [ApiErrorCode::MandateHeldElsewhere, 'COMPTE_DEJA_MANDATE', self::MANDATES, ErrorKind::MandateHeldElsewhere];
        yield 'ER_API_020_0000' => [ApiErrorCode::AccountUnknown, 'ER_API_020_0000', self::MANDATES, ErrorKind::AccountUnknown];
        yield 'AICI_NON_ACTIVE' => [ApiErrorCode::AiciNotActive, 'AICI_NON_ACTIVE', self::DECLARE, ErrorKind::AiciNotActive];
        yield 'NATURE_INCORRECTE' => [ApiErrorCode::NatureIncompatible, 'NATURE_INCORRECTE', self::DECLARE, ErrorKind::NatureIncompatible];
        yield 'NATURE_INCORRECTE_AF' => [ApiErrorCode::NatureForbiddenForFosterCarer, 'NATURE_INCORRECTE_AF', self::DECLARE, ErrorKind::NatureForbiddenForFosterCarer];
        yield 'NATURE_INCONNUE' => [ApiErrorCode::NatureUnknown, 'NATURE_INCONNUE', self::DECLARE, ErrorKind::NatureUnknown];
        yield 'NATURE_ABSENTE' => [ApiErrorCode::NatureMissing, 'NATURE_ABSENTE', self::DECLARE, ErrorKind::NatureMissing];
        yield 'ACOMPTE_ELEVE' => [ApiErrorCode::DepositTooHigh, 'ACOMPTE_ELEVE', self::DECLARE, ErrorKind::DepositTooHigh];
        yield 'ERR_TECHNIQUE' => [ApiErrorCode::TechnicalError, 'ERR_TECHNIQUE', self::TECHNICAL, ErrorKind::Technical];
        yield 'ERREUR_TECHNIQUE' => [ApiErrorCode::MandateTechnicalError, 'ERREUR_TECHNIQUE', self::MANDATES, ErrorKind::Technical];
    }

    /**
     * @param list<Operation> $operations
     */
    #[DataProvider('catalogue')]
    public function testEveryCodeMatchesTheCatalogue(ApiErrorCode $code, string $value, array $operations, ErrorKind $kind): void
    {
        static::assertSame($value, $code->value);
        static::assertSame($code, ApiErrorCode::fromCode($value));
        static::assertSame($operations, $code->operations());
        static::assertSame($kind, $code->errorKind());
    }

    public function testTheCatalogueHoldsExactlyTheDocumentedCodes(): void
    {
        $documented = [];
        foreach (self::catalogue() as [, $value]) {
            $documented[] = $value;
        }

        static::assertCount(15, ApiErrorCode::cases());
        static::assertSame($documented, array_map(static fn(ApiErrorCode $code): string => $code->value, ApiErrorCode::cases()));
    }

    public function testEveryOperationIsReachableByAtLeastOneCode(): void
    {
        foreach (Operation::cases() as $operation) {
            $codes = array_filter(ApiErrorCode::cases(), static fn(ApiErrorCode $code): bool => \in_array($operation, $code->operations(), true));

            static::assertNotSame([], $codes, $operation->value);
        }
    }

    public function testOnlyTheTechnicalCodesMapToTheTechnicalKind(): void
    {
        foreach (ApiErrorCode::cases() as $code) {
            static::assertSame($code->isTechnical(), $code->errorKind() === ErrorKind::Technical, $code->value);
        }
    }
}
