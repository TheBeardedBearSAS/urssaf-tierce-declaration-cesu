<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Entity;

use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\CesuNumberType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Key row of a non-cancelled mandate: the primary key (tenant, Cesu number) is the "one active mandate per employer"
 * constraint (ADR 0006, lot 7 design §4.5). Mapped so that the schema tools and migrations know the table; written with
 * DBAL by {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository} in
 * the transaction of the mandate flush, never through the ORM.
 *
 * Not final only because Doctrine may generate a proxy for any mapped entity: final by convention, never extend it.
 *
 * @final
 */
class ActiveMandate
{
    /** DBAL types of the columns of {@see self::row()}. */
    public const TYPES = [
        'tenant_id' => TenantIdType::NAME,
        'cesu_number' => CesuNumberType::NAME,
        'mandate_id' => MandateIdType::NAME,
    ];

    private function __construct(
        protected TenantId $tenantId,
        protected CesuNumber $cesuNumber,
        private readonly MandateId $mandateId,
    ) {}

    public static function of(Mandate $mandate): self
    {
        return new self($mandate->tenantId(), $mandate->cesuNumber(), $mandate->id());
    }

    /**
     * The columns of the row, keyed by column name (see {@see self::TYPES}).
     *
     * @return array{tenant_id: TenantId, cesu_number: CesuNumber, mandate_id: MandateId}
     */
    public function row(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'cesu_number' => $this->cesuNumber,
            'mandate_id' => $this->mandateId,
        ];
    }
}
