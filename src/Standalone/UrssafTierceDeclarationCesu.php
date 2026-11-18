<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Standalone;

use LogicException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAiciHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateCesuPlusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ForgetEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationSubmitter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateGuard;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;

/**
 * Every use case of the package wired together, for applications without the Symfony bundle; built by
 * {@see UrssafTierceDeclarationCesuBuilder}. Each method returns a new handler (handlers are stateless).
 *
 * Source: reference UrssafTiersPrestations; docs/standalone.md.
 *
 * One accessor per use case, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafTierceDeclarationCesu
{
    private const STATISTICS_MISSING = 'No WorkflowStatistics: give one with the repositories (withRepositories()).';
    private const SINK_MISSING = 'No IntegrationEventSink: give the outbox and its sink (withOutbox()).';

    public function __construct(
        private CesuGateway $gateway,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private DeclarationRecordRepository $declarations,
        private UnitOfWork $unitOfWork,
        private Clock $clock,
        private IdGenerator $ids,
        private WorkflowPolicy $policy,
        private ?WorkflowStatistics $statistics,
        private Outbox $outbox,
        private ?IntegrationEventSink $sink,
    ) {}

    public function gateway(): CesuGateway
    {
        return $this->gateway;
    }

    public function registerEmployer(): RegisterEmployerHandler
    {
        return new RegisterEmployerHandler($this->accounts, $this->gateway, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function importEmployer(): ImportEmployerHandler
    {
        return new ImportEmployerHandler($this->accounts, $this->mandates, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function registerMandate(): RegisterMandateHandler
    {
        return new RegisterMandateHandler($this->employerLocator(), $this->mandates, $this->accounts, $this->gateway, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function cancelMandate(): CancelMandateHandler
    {
        return new CancelMandateHandler($this->employerLocator(), $this->mandates, $this->accounts, $this->gateway, $this->clock, $this->unitOfWork);
    }

    public function forgetEmployer(): ForgetEmployerHandler
    {
        return new ForgetEmployerHandler($this->employerLocator(), $this->accounts, $this->mandates, $this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function syncEmployerStatus(): SyncEmployerStatusHandler
    {
        return new SyncEmployerStatusHandler($this->employerLocator(), $this->refresher(), $this->mandates, $this->accounts);
    }

    public function syncEmployerStatuses(): SyncEmployerStatusesHandler
    {
        return new SyncEmployerStatusesHandler($this->accounts, $this->refresher(), $this->policy);
    }

    public function syncAllEmployerStatuses(): SyncAllEmployerStatusesHandler
    {
        return new SyncAllEmployerStatusesHandler($this->accounts, $this->syncEmployerStatuses());
    }

    public function activateCesuPlus(): ActivateCesuPlusHandler
    {
        return new ActivateCesuPlusHandler($this->gateway, $this->mandateGuard());
    }

    public function activateAici(): ActivateAiciHandler
    {
        return new ActivateAiciHandler($this->gateway);
    }

    public function declareSalary(): DeclareSalaryHandler
    {
        return new DeclareSalaryHandler($this->declarations, $this->submitter(), $this->mandateGuard(), $this->ids, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function resolveDeclarationOutcome(): ResolveDeclarationOutcomeHandler
    {
        return new ResolveDeclarationOutcomeHandler($this->declarationLocator(), $this->declarations, $this->clock, $this->unitOfWork);
    }

    public function reconcileStaleDeclarations(): ReconcileStaleDeclarationsHandler
    {
        return new ReconcileStaleDeclarationsHandler($this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function requeueAfterInvalidCredentials(): RequeueAfterInvalidCredentialsHandler
    {
        return new RequeueAfterInvalidCredentialsHandler($this->declarations, $this->clock, $this->unitOfWork, $this->policy);
    }

    /**
     * @throws LogicException when no outbox sink was given (withOutbox())
     */
    public function relayOutbox(): RelayOutboxHandler
    {
        return new RelayOutboxHandler($this->outbox, $this->sink ?? throw new LogicException(self::SINK_MISSING), $this->clock, $this->policy);
    }

    public function purgeOutbox(): PurgeOutboxHandler
    {
        return new PurgeOutboxHandler($this->outbox, $this->clock);
    }

    public function verifyEmployer(): VerifyEmployerHandler
    {
        return new VerifyEmployerHandler($this->gateway, $this->clock);
    }

    public function verifyEmployee(): VerifyEmployeeHandler
    {
        return new VerifyEmployeeHandler($this->gateway);
    }

    public function getEmployerAccount(): GetEmployerAccountHandler
    {
        return new GetEmployerAccountHandler($this->employerLocator(), $this->mandates);
    }

    public function estimateContributions(): EstimateContributionsHandler
    {
        return new EstimateContributionsHandler($this->gateway);
    }

    public function getDeclaration(): GetDeclarationHandler
    {
        return new GetDeclarationHandler($this->declarationLocator());
    }

    public function findDeclarations(): FindDeclarationsHandler
    {
        return new FindDeclarationsHandler($this->declarations, $this->policy);
    }

    /**
     * @throws LogicException when no statistics were given (withRepositories())
     */
    public function getWorkflowOverview(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler($this->statistics ?? throw new LogicException(self::STATISTICS_MISSING));
    }

    private function employerLocator(): EmployerLocator
    {
        return new EmployerLocator($this->accounts);
    }

    private function refresher(): EmployerStatusRefresher
    {
        return new EmployerStatusRefresher($this->gateway, $this->mandates, $this->accounts, $this->clock, $this->unitOfWork);
    }

    private function mandateGuard(): MandateGuard
    {
        return new MandateGuard($this->mandates, $this->accounts, $this->policy);
    }

    private function declarationLocator(): DeclarationLocator
    {
        return new DeclarationLocator($this->declarations);
    }

    private function submitter(): DeclarationSubmitter
    {
        return new DeclarationSubmitter($this->declarations, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }
}
