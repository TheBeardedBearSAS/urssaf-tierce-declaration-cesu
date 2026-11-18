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

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryDeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Wires the package without a framework. Every "with" method returns a new builder.
 *
 * Defaults: in-memory repositories, statistics and token store (tests and prototypes: use withDoctrine() or your own
 * repositories, and a shared token store in production), the UTC system clock,
 * random UUIDs, no event publisher, no outbox, the default workflow policy, the HTTP gateway of
 * {@see UrssafGatewayFactory}.
 *
 * Source: reference UrssafTiersPrestationsBuilder; docs/standalone.md.
 *
 * One property and one "with" method per collaborator, hence the counts.
 *
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:too-many-methods
 */
final class UrssafTierceDeclarationCesuBuilder
{
    private ?CesuGateway $gateway = null;
    private TokenStore $tokenStore;
    private Clock $clock;
    private IdGenerator $ids;
    private EventPublisher $events;
    private ?LoggerInterface $logger = null;
    private ?RateLimiter $rateLimiter = null;
    private WorkflowPolicy $policy;
    private EmployerAccountRepository $accounts;
    private MandateRepository $mandates;
    private DeclarationRecordRepository $declarations;
    private TransactionManager $transactions;
    private ?WorkflowStatistics $statistics;
    private Outbox $outbox;
    private ?IntegrationEventSink $sink = null;

    private function __construct(
        private readonly HttpTransport $transport,
        private readonly CredentialsProvider $credentials,
        private readonly ApiConfiguration $configuration,
    ) {
        $this->tokenStore = new InMemoryTokenStore();
        $this->clock = new UtcSystemClock();
        $this->ids = new RandomUuidGenerator(new SecureRandomBytes());
        $this->events = new NullEventPublisher();
        $this->policy = new WorkflowPolicy();
        $accounts = new InMemoryEmployerAccountRepository();
        $mandates = new InMemoryMandateRepository();
        $declarations = new InMemoryDeclarationRecordRepository();
        $this->accounts = $accounts;
        $this->mandates = $mandates;
        $this->declarations = $declarations;
        $this->transactions = new ImmediateTransactionManager();
        $this->statistics = new InMemoryWorkflowStatistics($accounts, $mandates, $declarations);
        $this->outbox = new IgnoredOutbox();
    }

    public static function create(HttpTransport $transport, CredentialsProvider $credentials, ApiConfiguration $configuration): self
    {
        return new self($transport, $credentials, $configuration);
    }

    /**
     * A token store shared by every process that calls URSSAF (PSR-6 / PSR-16 store).
     */
    public function withTokenStore(TokenStore $tokenStore): self
    {
        return $this->with(static function (self $builder) use ($tokenStore): void {
            $builder->tokenStore = $tokenStore;
        });
    }

    public function withClock(Clock $clock): self
    {
        return $this->with(static function (self $builder) use ($clock): void {
            $builder->clock = $clock;
        });
    }

    public function withIdGenerator(IdGenerator $ids): self
    {
        return $this->with(static function (self $builder) use ($ids): void {
            $builder->ids = $ids;
        });
    }

    /**
     * Where the domain events are published after each commit (for instance a Psr14EventPublisher).
     */
    public function withEventPublisher(EventPublisher $events): self
    {
        return $this->with(static function (self $builder) use ($events): void {
            $builder->events = $events;
        });
    }

    /**
     * The logger of the HTTP gateway and of the event publication failures.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(static function (self $builder) use ($logger): void {
            $builder->logger = $logger;
        });
    }

    /**
     * Replaces the default SlidingWindowRateLimiter of the HTTP gateway (for instance NoopRateLimiter), ADR 0011.
     */
    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return $this->with(static function (self $builder) use ($rateLimiter): void {
            $builder->rateLimiter = $rateLimiter;
        });
    }

    public function withPolicy(WorkflowPolicy $policy): self
    {
        return $this->with(static function (self $builder) use ($policy): void {
            $builder->policy = $policy;
        });
    }

    /**
     * Your own persistence; without statistics, getWorkflowOverview() is not available.
     */
    public function withRepositories(
        EmployerAccountRepository $accounts,
        MandateRepository $mandates,
        DeclarationRecordRepository $declarations,
        TransactionManager $transactions,
        ?WorkflowStatistics $statistics,
    ): self {
        return $this->with(static function (self $builder) use ($accounts, $mandates, $declarations, $transactions, $statistics): void {
            $builder->accounts = $accounts;
            $builder->mandates = $mandates;
            $builder->declarations = $declarations;
            $builder->transactions = $transactions;
            $builder->statistics = $statistics;
        });
    }

    /**
     * The Doctrine persistence of the package, on a dedicated entity manager (docs/doctrine.md): repositories,
     * transaction manager and workflow statistics. The outbox is separate: withOutbox(new DoctrineOutbox($entityManager), $sink).
     *
     * Source: reference UrssafTiersPrestationsBuilder::withDoctrine(); ADR 0014.
     */
    public function withDoctrine(EntityManagerInterface $entityManager): self
    {
        return $this->withRepositories(
            new DoctrineEmployerAccountRepository($entityManager),
            new DoctrineMandateRepository($entityManager),
            new DoctrineDeclarationRecordRepository($entityManager),
            new DoctrineTransactionManager($entityManager),
            new DoctrineWorkflowStatistics($entityManager),
        );
    }

    /**
     * Writes the integration events to the outbox (in the transaction of the repositories) and relays them to the sink.
     */
    public function withOutbox(Outbox $outbox, IntegrationEventSink $sink): self
    {
        return $this->with(static function (self $builder) use ($outbox, $sink): void {
            $builder->outbox = $outbox;
            $builder->sink = $sink;
        });
    }

    /**
     * Replaces the HTTP gateway (for instance with the Testing\SimulatedCesuGateway in tests).
     */
    public function withGateway(CesuGateway $gateway): self
    {
        return $this->with(static function (self $builder) use ($gateway): void {
            $builder->gateway = $gateway;
        });
    }

    public function build(): UrssafTierceDeclarationCesu
    {
        return new UrssafTierceDeclarationCesu(
            $this->gateway(),
            $this->accounts,
            $this->mandates,
            $this->declarations,
            $this->unitOfWork(),
            $this->clock,
            $this->ids,
            $this->policy,
            $this->statistics,
            $this->outbox,
            $this->sink,
        );
    }

    /**
     * @param Closure(self): void $change applied to a copy of the builder
     */
    private function with(Closure $change): self
    {
        $builder = clone $this;
        $change($builder);

        return $builder;
    }

    private function gateway(): CesuGateway
    {
        return $this->gateway ?? UrssafGatewayFactory::createWith($this->transport, $this->credentials, $this->tokenStore, $this->configuration, $this->gatewayOptions());
    }

    private function unitOfWork(): UnitOfWork
    {
        $failures = $this->logger instanceof LoggerInterface ? new PsrLoggerFailureReporter($this->logger) : new IgnoredFailures();

        return new UnitOfWork($this->transactions, $this->events, $failures, $this->outbox);
    }

    private function gatewayOptions(): GatewayOptions
    {
        $options = GatewayOptions::defaults()->withClock($this->clock)->withIdGenerator($this->ids);
        if ($this->rateLimiter instanceof RateLimiter) {
            $options = $options->withRateLimiter($this->rateLimiter);
        }

        return $this->logger instanceof LoggerInterface ? $options->withLogger($this->logger) : $options;
    }
}
