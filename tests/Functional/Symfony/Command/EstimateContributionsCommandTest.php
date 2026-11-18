<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;

final class EstimateContributionsCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:estimate';

    public function testTheContributionsAreEstimatedFromTheHourlyWage(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--hourly-wage' => '12.50', '--hours' => '10']));

        static::assertSame(['Employer contributions (€)' => '100.00', 'Employee contributions (€)' => '50.00'], Fields::of($command->getDisplay()));
        static::assertEquals([
            'typeEmploi' => 'PE',
            'revenusEf' => [
                'salaireHoraire' => 12.5,
                'congesPayes' => false,
                'heuresTravaillees' => 10,
                'typeSalaire' => 'NET',
                'codeExoneration' => '02',
                'codeZoneGeographique' => 'NATIO',
            ],
        ], self::lastBody($harness));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_040));
    }

    public function testEveryOptionReachesTheRequest(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([
            '--tenant' => 'tenant-1',
            '--hourly-wage' => '12.50',
            '--hours' => '40',
            '--first-tier-overtime-hours' => '4',
            '--second-tier-overtime-hours' => '0',
            '--paid-leave-hours' => '8',
            '--paid-leave-included' => true,
            '--salary-type' => 'BRUT',
            '--exemption' => '01',
            '--zone' => 'ALSMO',
        ]));

        static::assertEquals([
            'typeEmploi' => 'PE',
            'revenusEf' => [
                'salaireHoraire' => 12.5,
                'congesPayes' => true,
                'heuresTravaillees' => 40,
                'nbHeureSupplementaire1' => 4,
                'nbHeureSupplementaire2' => 0,
                'heuresCp' => 8,
                'typeSalaire' => 'BRUT',
                'codeExoneration' => '01',
                'codeZoneGeographique' => 'ALSMO',
            ],
        ], self::lastBody($harness));
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_040));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'no wage' => [['--hours' => '10'], 'The --hourly-wage option is required.'];
        yield 'no hours' => [['--hourly-wage' => '12.5'], 'The --hours option is required.'];
        yield 'hours not an integer' => [['--hourly-wage' => '12.5', '--hours' => '10.5'], 'The --hours option must be a non-negative integer.'];
        yield 'negative hours' => [['--hourly-wage' => '12.5', '--hours' => '-1'], 'The --hours option must be a non-negative integer.'];
        yield 'overtime not an integer' => [['--hourly-wage' => '12.5', '--hours' => '10', '--first-tier-overtime-hours' => 'x'], 'The --first-tier-overtime-hours option must be a non-negative integer.'];
        yield 'second tier not an integer' => [['--hourly-wage' => '12.5', '--hours' => '10', '--second-tier-overtime-hours' => '1.5'], 'The --second-tier-overtime-hours option must be a non-negative integer.'];
        yield 'paid leave not an integer' => [['--hourly-wage' => '12.5', '--hours' => '10', '--paid-leave-hours' => '01'], 'The --paid-leave-hours option must be a non-negative integer.'];
        yield 'malformed wage' => [['--hourly-wage' => 'abc', '--hours' => '10'], '(money.invalid_format)'];
        yield 'unknown salary type' => [['--hourly-wage' => '12.5', '--hours' => '10', '--salary-type' => 'X'], 'Invalid salary_type: the value is not one of the accepted codes. (salary_type.unknown)'];
        yield 'unknown exemption' => [['--hourly-wage' => '12.5', '--hours' => '10', '--exemption' => 'X'], '(exemption_code.unknown)'];
        yield 'unknown zone' => [['--hourly-wage' => '12.5', '--hours' => '10', '--zone' => 'X'], '(geographic_zone.unknown)'];
        yield 'too many hours' => [['--hourly-wage' => '12.5', '--hours' => '999'], '(worked_hours'];
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_040, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--hourly-wage' => '12.5', '--hours' => '10']));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function lastBody(Harness $harness): array
    {
        $requests = $harness->client->getRequests();
        // @mago-expect analysis:mixed-assignment json_decode() is untyped: the body is narrowed by the assertion below.
        $body = json_decode((string) $requests[\count($requests) - 1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
        \assert(\is_array($body), 'The estimate request is a JSON object.');

        return $body;
    }
}
