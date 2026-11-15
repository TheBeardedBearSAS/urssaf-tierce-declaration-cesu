<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status;

/**
 * "etatCesuPlus" of method 020 (api-reference §2.2, §3.4). The PDF spells the values with accents (§5.1), the Swagger
 * without: both are accepted (precomposed UTF-8 only, no intl normaliser).
 */
enum CesuPlusState: string
{
    case Active = 'active';
    case Blocked = 'bloque';
    case Deactivated = 'desactive';

    /**
     * Null for an unknown value (the raw string is kept by {@see EmployerStatus}).
     */
    public static function tryFromWire(string $raw): ?self
    {
        $value = trim($raw);

        return match ($value) {
            'activé' => self::Active,
            'bloqué' => self::Blocked,
            'désactivé' => self::Deactivated,
            default => self::tryFrom($value),
        };
    }
}
