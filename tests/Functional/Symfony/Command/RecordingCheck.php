<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use ArrayObject;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * A check of the host that records the context it was run with.
 */
final readonly class RecordingCheck implements InstallationCheck
{
    /**
     * @param ArrayObject<int, string> $seen
     */
    public function __construct(private string $name, private ArrayObject $seen) {}

    public function run(CheckContext $context): array
    {
        $this->seen->append(\sprintf('%s:%d:%s', $this->name, \count($context->tenants), $context->remoteCallsAllowed ? 'remote' : 'local'));

        return [
            new CheckResult($this->name, '', CheckStatus::Ok, 'Fine.'),
            new CheckResult($this->name . '+', '', CheckStatus::Warning, 'Careful.'),
        ];
    }
}
