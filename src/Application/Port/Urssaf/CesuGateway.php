<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

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
