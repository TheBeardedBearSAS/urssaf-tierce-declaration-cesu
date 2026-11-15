<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

/**
 * Every operation of the API Tierce Déclaration CESU, implemented by the infrastructure layer.
 */
interface CesuGateway extends
    EmployerRegistrar,
    EmployerVerifier,
    EmployeeVerifier,
    MandateNotifier,
    ContributionEstimator,
    SalaryDeclarer,
    CesuPlusActivator,
    AiciActivator {}
