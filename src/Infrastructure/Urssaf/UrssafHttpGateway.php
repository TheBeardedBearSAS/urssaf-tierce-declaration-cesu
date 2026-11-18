<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\NewEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\AiciActivationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\AiciActivationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ApiPaths;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\CesuPlusActivationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployeeVerificationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerRegistrationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerRegistrationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EmployerVerificationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EstimateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EstimateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\MandateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\QueryEncoding;

/**
 * HTTP implementation of every operation of the API Tierce Déclaration CESU (010 to 070). Build it with
 * {@see UrssafGatewayFactory}. 010, 050, 060 and 070 are never resent: an answer that may have been processed but
 * cannot be read throws {@see OutcomeUnknown} (ADR 0003).
 *
 * Status-level failures (transport, 4xx, 5xx) are mapped by the JSON client (ADR 0002); this class only turns an
 * unreadable 2xx answer into an unknown outcome for the operations that are not safe to retry.
 *
 * One method per operation of the {@see CesuGateway} port, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafHttpGateway implements CesuGateway
{
    public function __construct(
        private CredentialsProvider $credentialsProvider,
        private JsonApiClient $client,
        private AgreementEncoding $agreementEncoding,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function registerEmployer(TenantId $tenantId, #[SensitiveParameter] NewEmployer $employer): CesuNumber
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::post(
            Operation::RegisterEmployer,
            ApiPaths::registerEmployer(),
            [],
            JsonEncoder::encode(JsonObject::of((new EmployerRegistrationRequestMapper())->toBody($employer))),
        );
        $response = $this->client->send($tenantId, $credentials, $request);

        return $this->read(
            Operation::RegisterEmployer,
            $response,
            static fn(Payload $payload): CesuNumber => (new EmployerRegistrationResponseMapper())->map($payload),
        );
    }

    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerVerification $verification): EmployerStatus
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::get(
            Operation::VerifyEmployer,
            ApiPaths::verifyEmployer($verification->employer()->cesuNumber()),
            QueryEncoding::employerVerification($verification),
        );
        $response = $this->client->send($tenantId, $credentials, $request);

        return $this->read(
            Operation::VerifyEmployer,
            $response,
            static fn(Payload $payload): EmployerStatus => (new EmployerVerificationResponseMapper())->map($payload),
        );
    }

    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeReference $employee): EmployeeVerificationResult
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::get(
            Operation::VerifyEmployee,
            ApiPaths::verifyEmployee(),
            QueryEncoding::employeeVerification($employee),
        );
        $response = $this->client->send($tenantId, $credentials, $request);

        return $this->read(
            Operation::VerifyEmployee,
            $response,
            static fn(Payload $payload): EmployeeVerificationResult => (new EmployeeVerificationResponseMapper())->map($payload),
        );
    }

    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::post(Operation::RegisterMandate, ApiPaths::mandates(), QueryEncoding::mandate($employer), null);
        $response = $this->client->send($tenantId, $credentials, $request);

        $this->readEmpty(Operation::RegisterMandate, $response);
    }

    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] Employer $employer): void
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::delete(Operation::CancelMandate, ApiPaths::mandates(), QueryEncoding::mandate($employer));
        $response = $this->client->send($tenantId, $credentials, $request);

        $this->readEmpty(Operation::CancelMandate, $response);
    }

    public function estimate(TenantId $tenantId, EstimateRequest $request): ContributionsEstimate
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $apiRequest = ApiRequest::post(
            Operation::EstimateContributions,
            ApiPaths::estimate(),
            [],
            JsonEncoder::encode(JsonObject::of((new EstimateRequestMapper())->toBody($request))),
        );
        $response = $this->client->send($tenantId, $credentials, $apiRequest);

        return $this->read(
            Operation::EstimateContributions,
            $response,
            static fn(Payload $payload): ContributionsEstimate => (new EstimateResponseMapper())->map($payload),
        );
    }

    public function declare(TenantId $tenantId, #[SensitiveParameter] Declaration $declaration): DeclarationResult
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $request = ApiRequest::post(
            Operation::DeclareSalary,
            ApiPaths::declare($declaration->content()->employerCesuNumber()),
            [],
            JsonEncoder::encode(JsonObject::of((new DeclarationRequestMapper())->toBody($declaration))),
        );
        $response = $this->client->send($tenantId, $credentials, $request);

        return $this->read(
            Operation::DeclareSalary,
            $response,
            static fn(Payload $payload): DeclarationResult => (new DeclarationResponseMapper())->map($payload),
        );
    }

    public function activateCesuPlus(TenantId $tenantId, CesuPlusActivationRequest $request): CesuPlusActivation
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $apiRequest = ApiRequest::post(
            Operation::ActivateCesuPlus,
            ApiPaths::activateCesuPlus($request->employer()->cesuNumber()),
            QueryEncoding::cesuPlusActivation($request, $this->agreementEncoding),
            null,
        );
        $response = $this->client->send($tenantId, $credentials, $apiRequest);

        return $this->read(
            Operation::ActivateCesuPlus,
            $response,
            static fn(Payload $payload): CesuPlusActivation => (new CesuPlusActivationResponseMapper())->map($payload),
        );
    }

    public function activateAici(TenantId $tenantId, #[SensitiveParameter] AiciActivationRequest $request): AiciActivation
    {
        $credentials = $this->credentialsProvider->forTenant($tenantId);
        $apiRequest = ApiRequest::post(
            Operation::ActivateAici,
            ApiPaths::activateAici($request->employer()->cesuNumber()),
            [],
            JsonEncoder::encode(JsonObject::of((new AiciActivationRequestMapper())->toBody($request))),
        );
        $response = $this->client->send($tenantId, $credentials, $apiRequest);

        return $this->read(
            Operation::ActivateAici,
            $response,
            static fn(Payload $payload): AiciActivation => (new AiciActivationResponseMapper())->map($payload),
        );
    }

    /**
     * Method 030 / 031: the answer must carry no payload.
     */
    private function readEmpty(Operation $operation, ApiResponse $response): void
    {
        $this->read($operation, $response, static function (Payload $payload): null {
            (new MandateResponseMapper())->assertEmpty($payload);

            return null;
        });
    }

    /**
     * Decodes and maps an already received 2xx answer. The only ADR 0003 decision of the gateway: when it cannot be
     * read, a safe operation reports the unexpected response, an unsafe one (that URSSAF did process) an unknown outcome.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $map
     * @param callable(Payload): T $map
     *
     * @return T
     *
     * @throws UnexpectedUrssafResponse safe operations
     * @throws OutcomeUnknown           unsafe operations
     */
    private function read(Operation $operation, ApiResponse $response, callable $map): mixed
    {
        try {
            return $map($response->payload());
        } catch (UnexpectedUrssafResponse $unreadable) {
            if ($operation->isSafeToRetry()) {
                throw $unreadable;
            }

            $this->logger->error('URSSAF method answered a success that could not be read: outcome unknown.', [
                'operation' => $operation->methodNumber(),
                'status' => $response->status(),
                'reason' => $unreadable->getMessage(),
            ]);

            throw OutcomeUnknown::afterUnreadableResponse($operation, $response->status(), $unreadable);
        }
    }
}
