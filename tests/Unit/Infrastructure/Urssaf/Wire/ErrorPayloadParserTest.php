<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class ErrorPayloadParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, ?string}>
     */
    public static function officialBodies(): iterable
    {
        yield 'error_param_invalide' => ['error_param_invalide', 'PARAM_INVALIDE', 'Un des paramètres de votre demande est invalide', 'Le paramètre dateNaissance est invalide'];
        yield 'error_swagger_example' => ['error_swagger_example', 'PARAM_INVALIDE', 'Requete incorrecte', 'Le format du champ numeroCesu est incorrect'];
        yield 'error_sepa_absent' => ['error_sepa_absent', 'SEPA_ABSENT', "Absence d''autorisation de mandat SEPA", null];
        yield 'error_err_metier_iban' => ['error_err_metier_iban', 'ERR_METIER', ' Une erreur métier est survenue', 'Veuillez indiquer un IBAN valide.'];
        yield 'error_err_metier_cesuplus_unpaid_salary' => ['error_err_metier_cesuplus_unpaid_salary', 'ERR_METIER', 'Une erreur métier cesu+ est survenue', "Présence d'un impayé sur salaire sur le compte employeur. Il est impossible de réaliser de déclaration Cesu+ sur ce compte."];
        yield 'error_mandat_inexistant' => ['error_mandat_inexistant', 'MANDAT_INEXISTANT', 'Mandat de tierce déclaration absent', null];
        yield 'error_compte_deja_mandate' => ['error_compte_deja_mandate', 'COMPTE_DEJA_MANDATE', 'Le compte est déjà mandaté', 'Il existe déjà un autre établissement mandataire pour ce numéro de compte'];
        yield 'error_account_unknown' => ['error_account_unknown', 'ER_API_020_0000', 'Aucun compte CESU trouvé avec ces paramètres', 'Aucun compte CESU trouvé avec ces paramètres'];
        yield 'error_technical' => ['error_technical', 'ERR_TECHNIQUE', 'Erreur technique', 'Une erreur système est survenue'];
        yield 'error_technical_mandate' => ['error_technical_mandate', 'ERREUR_TECHNIQUE', 'Une erreur technique est survenue', "Impossible de joindre le service cesu afin de vérifier l'existence du compte fourni"];
    }

    #[DataProvider('officialBodies')]
    public function testItParsesTheOfficialBodiesVerbatim(string $fixture, string $code, string $message, ?string $description): void
    {
        $errors = (new ErrorPayloadParser())->parseBody(Fixtures::load($fixture));

        static::assertCount(1, $errors);
        static::assertSame($code, $errors->all()[0]->code());
        static::assertSame($message, $errors->all()[0]->message());
        static::assertSame($description, $errors->all()[0]->description());
    }

    public function testItParsesAListOfErrorsAndNormalisesCodes(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[{"code":" ERR_ METIER ","message":"a","description":"b"},{"code":"PARAM_INVALIDE"}]');

        static::assertSame(['ERR_METIER', 'PARAM_INVALIDE'], $errors->codes());
        static::assertSame('a', $errors->all()[0]->message());
        static::assertSame('b', $errors->all()[0]->description());
    }

    public function testItParsesTheErrorsEnvelope(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('{"errors":[{"code":"ERR_ METIER","message":"m","description":"d"}]}');

        static::assertSame(['ERR_METIER'], $errors->codes());
        static::assertSame('d', $errors->all()[0]->description());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ["  \n"];
        yield 'html' => ['<html>Bad gateway</html>'];
        yield 'scalar' => ['"oops"'];
        yield 'empty object' => ['{}'];
        yield 'empty list' => ['[]'];
        yield 'list of scalars' => ['[1, 2]'];
        yield 'errors is not a list' => ['{"errors": {"code": "X"}}'];
        yield 'errors is a string' => ['{"errors": "text"}'];
    }

    #[DataProvider('unreadableBodies')]
    public function testItIsLenientWithUnreadableBodies(string $body): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody($body)->isEmpty());
    }

    public function testEntriesWithoutACodeAreIgnoredAndMissingTextsDefaultToEmptyOrNull(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[{"message":"no code"},{"code":""},{"code":"  "},{"code":null},{"code":7},{"code":"ERR_X"}]');

        static::assertSame(['ERR_X'], $errors->codes());
        static::assertSame('', $errors->all()[0]->message());
        static::assertNull($errors->all()[0]->description());
    }

    public function testMessagesAndDescriptionsAreKeptRaw(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('{"code":"ERR_METIER","message":"  a  ","description":" b "}');

        static::assertSame('  a  ', $errors->all()[0]->message());
        static::assertSame(' b ', $errors->all()[0]->description());
    }

    public function testAnObjectWithAnEmptyCodeIsAnEmptySingleError(): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody('{"code":"","errors":[{"code":"A"}]}')->isEmpty());
    }
}
