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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\BusinessErrorCatalogue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ErrorClassifier;

/**
 * The 87 texts of the PDF v2.0.2 (§3.4), typed from the documentation: the verbatim text and the method numbers that
 * document it. This provider is shared with {@see ErrorClassifierTest}.
 */
final class BusinessErrorCatalogueTest extends TestCase
{
    /**
     * One row per case: the size of the data set is the point of this provider.
     *
     * @mago-expect lint:halstead
     *
     * @return iterable<string, array{BusinessError, string, list<string>}>
     */
    public static function catalogue(): iterable
    {
        yield 'iban_invalid' => [BusinessError::IbanInvalid, 'Veuillez indiquer un IBAN valide.', ['010']];
        yield 'iban_not_sepa' => [BusinessError::IbanNotSepa, 'Veuillez indiquer un IBAN valide – préfixe de l’IBAN non SEPA.', ['010']];
        yield 'bic_invalid' => [BusinessError::BicInvalid, 'Veuillez indiquer un BIC valide.', ['010']];
        yield 'bank_details_blacklisted' => [BusinessError::BankDetailsBlacklisted, 'CoordBancaires blacklist.', ['010']];
        yield 'civility_missing' => [BusinessError::CivilityMissing, 'Veuillez indiquer votre civilité.', ['010']];
        yield 'last_name_missing' => [BusinessError::LastNameMissing, 'Veuillez indiquer votre nom.', ['010']];
        yield 'last_name_invalid' => [BusinessError::LastNameInvalid, 'Veuillez indiquer un nom valide.', ['010']];
        yield 'first_name_missing' => [BusinessError::FirstNameMissing, 'Veuillez indiquer votre prénom.', ['010']];
        yield 'first_name_invalid' => [BusinessError::FirstNameInvalid, 'Veuillez indiquer un prénom valide.', ['010']];
        yield 'forbidden_legal_form' => [BusinessError::ForbiddenLegalForm, 'Vous ne pouvez pas créer de compte Cesu en tant que : SCP, SNC, SCM, SA, SARL, EURL, SCI, SELARL, UDAF, église, paroisse, association.', ['010']];
        yield 'birth_date_missing' => [BusinessError::BirthDateMissing, 'Veuillez indiquer votre date de naissance.', ['010']];
        yield 'employer_under_sixteen' => [BusinessError::EmployerUnderSixteen, 'Vous devez avoir au moins 16 ans pour activer ce service.', ['010']];
        yield 'birth_date_invalid' => [BusinessError::BirthDateInvalid, 'Veuillez indiquer une date de naissance valide.', ['010']];
        yield 'address_incomplete' => [BusinessError::AddressIncomplete, 'Veuillez indiquer votre adresse complète.', ['010']];
        yield 'street_number_missing' => [BusinessError::StreetNumberMissing, 'Veuillez indiquer votre numéro de voie.', ['010']];
        yield 'street_name_missing' => [BusinessError::StreetNameMissing, 'Veuillez indiquer votre nom de voie.', ['010']];
        yield 'municipality_invalid' => [BusinessError::MunicipalityInvalid, 'Veuillez indiquer un code postal et une commune valides.', ['010']];
        yield 'phone_invalid' => [BusinessError::PhoneInvalid, 'Veuillez indiquer un numéro de téléphone valide.', ['010']];
        yield 'email_invalid' => [BusinessError::EmailInvalid, 'Veuillez indiquer un identifiant valide, avec au moins 1 caractère, le séparateur @, l\'hébergeur et l\'extension.', ['010']];
        yield 'email_already_known' => [BusinessError::EmailAlreadyKnown, 'Cet identifiant est déjà connu. Si vous disposez d\'un compte Cesu vous pouvez réinitialiser votre mot de passe à partir de la rubrique "Problème d\'identification".', ['010']];
        yield 'birth_date_mismatch' => [BusinessError::BirthDateMismatch, ' La date de naissance ne correspond pas à celle de l\'\'employeur identifié par le numéro CESU renseigné.', ['020']];
        yield 'no_employer_employee_link' => [BusinessError::NoEmployerEmployeeLink, ' Il n\'existe aucune lien entre le particulier employeur et le salarié (aucune prestation n\'a été réalisée par ce salarié pour l\'employeur)', ['020']];
        yield 'hourly_wage_below_minimum' => [BusinessError::HourlyWageBelowMinimum, ' Le salaire horaire ne peut être inférieur à XX,XX €.', ['040']];
        yield 'overtime_requires_forty_normal_hours' => [BusinessError::OvertimeRequiresFortyNormalHours, ' La déclaration d\'heures majorées est impossible si le nombre d\'heures normales est inférieur à 40.', ['040', '050']];
        yield 'second_tier_overtime_requires_eight_hours' => [BusinessError::SecondTierOvertimeRequiresEightHours, 'Pour déclarer des heures majorées à 50%, le nombre d\'heures majorées à 25% doit être supérieur ou égal à 8.', ['040', '050']];
        yield 'specific_hours_wage_below_minimum' => [BusinessError::SpecificHoursWageBelowMinimum, ' Le salaire horaire net pour une heure spécifique XXX ne peut être inférieur à XX,XX€.', ['040', '050']];
        yield 'employee_contribution_without_benefits_in_kind' => [BusinessError::ContributionWithoutBenefitsInKind, 'La déclaration d\'une participation financière du salarié est impossible si aucun avantage en nature n\'est renseigné.', ['040', '050']];
        yield 'employee_contribution_without_hours_and_wage' => [BusinessError::ContributionWithoutHoursAndWage, 'La déclaration d\'une participation financière du salarié est impossible si le nombre d\'heures et le salaire horaire net ne sont pas renseignés.', ['040', '050']];
        yield 'benefits_in_kind_contributions_above_net_salary' => [BusinessError::BenefitsInKindContributionsAboveNetSalary, 'Le montant des cotisations salariales calculé sur Avantage en nature est supérieur au salaire net déclaré.', ['040', '050']];
        yield 'remuneration_missing' => [BusinessError::RemunerationMissing, ' Vous devez saisir au moins un élément de rémunération.', ['040', '050']];
        yield 'reception_days_above_limit' => [BusinessError::ReceptionDaysAboveLimit, ' Le nombre total de journées d\'\'accueil ne peut être supérieur à 32.', ['040', '050']];
        yield 'full_time_daily_wage_below_minimum' => [BusinessError::FullTimeDailyWageBelowMinimum, ' Le salaire journalier à temps complet ne peut pas être inférieur à X SMIC.', ['040', '050']];
        yield 'part_time_daily_wage_below_minimum' => [BusinessError::PartTimeDailyWageBelowMinimum, ' Le salaire journalier à temps partiel ne peut pas être inférieur à X SMIC.', ['040', '050']];
        yield 'particular_constraint_days_above_limit' => [BusinessError::ParticularConstraintDaysAboveLimit, ' Le nombre de jours de sujétions particulières ne peut être supérieur à 32.', ['040', '050']];
        yield 'particular_constraint_smic_out_of_range' => [BusinessError::ParticularConstraintSmicOutOfRange, ' Pour les sujétions particulières le nombre de smic net par jour ne peut être inférieur à XX et supérieur à YY', ['040', '050']];
        yield 'maintenance_allowance_days_above_limit' => [BusinessError::MaintenanceAllowanceDaysAboveLimit, ' Le nombre de jours d\'indemnités d\'entretien ne peut être supérieur à 32.', ['040', '050']];
        yield 'guaranteed_minimum_out_of_range' => [BusinessError::GuaranteedMinimumOutOfRange, ' Pour l\'indemnité d\'entretien le nombre de minimum garanti par jour ne peut être inférieur à XX et supérieur à YY', ['040', '050']];
        yield 'calculation_failed' => [BusinessError::CalculationFailed, 'Une erreur s\'est produite durant la simulation des cotisations.', ['040', '050']];
        yield 'paid_leave_declared_while_included' => [BusinessError::PaidLeaveDeclaredWhileIncluded, 'La déclaration réalisée est soumise à la règle des congés inclus de 10% (congesPayes = true). Les informations heures de CP (heuresCp) et/ou périodes de congés payés (periodesCongesPayes) ne doivent donc pas être renseignées.', ['050']];
        yield 'duplicate_declaration' => [BusinessError::DuplicateDeclaration, 'Une déclaration est déjà enregistrée pour cette période d’emploi.', ['050']];
        yield 'employment_starts_after_today' => [BusinessError::EmploymentStartsAfterToday, 'La date de début d\'emploi doit être antérieure ou égale à la date du jour.', ['050']];
        yield 'employment_starts_after_end' => [BusinessError::EmploymentStartsAfterEnd, 'La date de début d\'emploi doit être inférieure ou égale à la date de fin.', ['050']];
        yield 'employment_spans_several_months' => [BusinessError::EmploymentSpansSeveralMonths, 'La période d\'emploi ne peut pas s\'étaler sur plusieurs mois.', ['050']];
        yield 'worked_hours_missing' => [BusinessError::WorkedHoursMissing, 'Veuillez indiquer un nombre d\'heures effectuées.', ['050']];
        yield 'net_total_inconsistent' => [BusinessError::NetTotalInconsistent, 'Le total net déclaré est incohérent.', ['050']];
        yield 'net_hourly_wage_inconsistent' => [BusinessError::NetHourlyWageInconsistent, 'Le salaire horaire net est incohérent.', ['050']];
        yield 'net_hourly_wage_below_minimum' => [BusinessError::NetHourlyWageBelowMinimum, 'Le salaire horaire net ne peut être inférieur à XX,XX €.', ['050']];
        yield 'paid_leave_hours_missing' => [BusinessError::PaidLeaveHoursMissing, 'Le nombre d\'heures déclarées au titre des congés payés doit être supérieur à 0.', ['050']];
        yield 'paid_leave_periods_incomplete' => [BusinessError::PaidLeavePeriodsIncomplete, 'Toutes les périodes de congés payés doivent être renseignées.', ['050']];
        yield 'paid_leave_period_ends_before_start' => [BusinessError::PaidLeavePeriodEndsBeforeStart, 'Veuillez indiquer des dates de début de période de congés antérieures ou égales aux dates de fin.', ['050']];
        yield 'paid_leave_periods_overlap' => [BusinessError::PaidLeavePeriodsOverlap, 'Les périodes de congés payés déclarées se chevauchent.', ['050']];
        yield 'paid_leave_outside_employment' => [BusinessError::PaidLeaveOutsideEmployment, 'Les congés payés doivent être inclus dans la période d\'emploi.', ['050']];
        yield 'paid_leave_days_missing' => [BusinessError::PaidLeaveDaysMissing, ' Veuillez indiquer un nombre total de jours de congés pris supérieur à 0.', ['050']];
        yield 'employee_contribution_above_benefits_in_kind' => [BusinessError::ContributionAboveBenefitsInKind, 'Le montant de la participation financière du salarié doit être inférieur au cumul des avantages en nature.', ['050']];
        yield 'employee_contribution_above_remuneration' => [BusinessError::ContributionAboveRemuneration, 'Le montant de la participation financière du salarié doit être inférieur à la rémunération déclarée hors avantage en nature.', ['050']];
        yield 'hours_without_hourly_wage' => [BusinessError::HoursWithoutHourlyWage, 'Des heures ont été saisies, vous devez saisir un salaire horaire net.', ['050']];
        yield 'activity_nature_missing' => [BusinessError::ActivityNatureMissing, 'Veuillez sélectionner une nature d\'activité.', ['050']];
        yield 'foster_carer_unsupported' => [BusinessError::FosterCarerUnsupported, 'Il n\'est pas possible de réaliser de déclaration pour les accueillants familiaux via l\'API', ['050']];
        yield 'employer_bank_details_inactive' => [BusinessError::EmployerBankDetailsInactive, 'Pas de coordonnées bancaires actives sur le compte de l\'employeur. Veuillez contacter le particulier employeur concerné.', ['050']];
        yield 'employer_online_account_anomaly' => [BusinessError::EmployerOnlineAccountAnomaly, 'Anomalie compte en ligne employeur. Pour information la méthode de vérification d\'un compte employeur vous permet d\'anticiper ce type de blocage.', ['050']];
        yield 'employer_account_anomaly' => [BusinessError::EmployerAccountAnomaly, 'Anomalie compte employeur. Veuillez contacter le Centre National Cesu.', ['050']];
        yield 'cesu_plus_deactivated_by_employer' => [BusinessError::CesuPlusDeactivatedByEmployer, 'Service Cesu+ désactivé à la demande de l\'employeur. Pour information, la méthode de vérification de l\'existence d\'un contrat Cesu+ entre un particulier employeur et un salarié vous permet d\'anticiper ce type de blocage.', ['050']];
        yield 'unpaid_salary' => [BusinessError::UnpaidSalary, 'Présence d\'un impayé sur salaire sur le compte employeur. Il est impossible de réaliser de déclaration Cesu+ sur ce compte.', ['050']];
        yield 'unpaid_contributions' => [BusinessError::UnpaidContributions, 'Présence d\'un impayé sur les cotisations ou prélèvement à la source sur le compte employeur. Il est impossible de réaliser de déclaration Cesu+ sur ce compte.', ['050']];
        yield 'employee_bank_details_inactive' => [BusinessError::EmployeeBankDetailsInactive, 'Pas de coordonnées bancaires actives sur le compte du salarié. Veuillez contacter le particulier employeur concerné.', ['050']];
        yield 'employee_online_account_anomaly' => [BusinessError::EmployeeOnlineAccountAnomaly, 'Anomalie sur le compte en ligne salarié. Pour information la méthode de vérification d\'un compte salarié vous permet d\'anticiper ce type de blocage', ['050']];
        yield 'employee_account_anomaly' => [BusinessError::EmployeeAccountAnomaly, 'Anomalie compte salarié. Veuillez contacter le Centre National Cesu', ['050']];
        yield 'cesu_plus_deactivated' => [BusinessError::CesuPlusDeactivated, 'Service Cesu+ désactivé. Pour information la méthode de vérification de l\'existence d\'un contrat Cesu+ entre un particulier employeur et un salarié vous permet d\'anticiper ce type de blocage', ['050']];
        yield 'identical_bank_details' => [BusinessError::IdenticalBankDetails, 'Coordonnées bancaires employeur et salarié identiques. Veuillez contacter le particulier employeur concerné.', ['050']];
        yield 'identical_civil_status' => [BusinessError::IdenticalCivilStatus, 'Etat civil employeur et salarié identique. Veuillez contacter le particulier employeur concerné', ['050']];
        yield 'pair_anomaly' => [BusinessError::PairAnomaly, 'Anomalie sur le couple employeur / salarié.', ['050']];
        yield 'cesu_plus_deadline_exceeded' => [BusinessError::CesuPlusDeadlineExceeded, 'Délai de saisie de déclaration Cesu+ dépassé. La réception de la déclaration ne doit pas être après le 15 du mois qui suit le mois déclaré. Il est cependant toujours possible de réaliser une déclaration Cesu socle.', ['050']];
        yield 'cesu_plus_net_salary_ceiling_exceeded' => [BusinessError::CesuPlusNetSalaryCeilingExceeded, 'Plafond de salaire net déclaré dépassé : il n\'est pas possible de réaliser une déclaration cesu+ avec un salaire supérieur à 6000 € pour une période donnée.', ['050']];
        yield 'employer_cesu_number_unknown' => [BusinessError::EmployerCesuNumberUnknown, 'N° CESU du particulier employeur inconnu.', ['060']];
        yield 'pair_agreement_missing' => [BusinessError::PairAgreementMissing, 'Absence de l\'accord du couple particulier employeur / salarié.', ['060']];
        yield 'employee_cesu_number_unknown' => [BusinessError::EmployeeCesuNumberUnknown, 'N° CESU du salarié inconnu.', ['060']];
        yield 'no_contract' => [BusinessError::NoContract, 'Aucun contrat existant pour le couple particulier employeur / salarié.', ['060']];
        yield 'cesu_plus_already_active' => [BusinessError::CesuPlusAlreadyActive, 'CESU+ déjà activé pour le couple PE/salarié.', ['060']];
        yield 'cesu_plus_blocked_for_employer' => [BusinessError::CesuPlusBlockedForEmployer, 'Blocage Cesu+ pour le particulier employeur.', ['060']];
        yield 'cesu_plus_blocked_for_employee' => [BusinessError::CesuPlusBlockedForEmployee, 'Blocage Cesu+ pour le salarié.', ['060']];
        yield 'cesu_plus_blocked_for_pair' => [BusinessError::CesuPlusBlockedForPair, 'Blocage Cesu+ pour le couple particulier employeur/salarié.', ['060']];
        yield 'apa_pch_exemption' => [BusinessError::ApaPchExemption, 'Employeur ayant une exonération APA/PCH.', ['060']];
        yield 'employer_not_found' => [BusinessError::EmployerNotFound, 'Aucun employeur n\'a été trouvé avec ces informations.', ['070']];
        yield 'birth_municipality_code_missing' => [BusinessError::BirthMunicipalityCodeMissing, 'Le paramètre codeCommuneNaissance est manquant.', ['070']];
        yield 'not_recognised_by_tax_administration' => [BusinessError::NotRecognisedByTaxAdministration, 'Le particulier n\'a pas été reconnu par l\'API DGFIP R2P.', ['070']];
        yield 'not_eligible_for_aici' => [BusinessError::NotEligibleForAici, 'Le particulier n\'est pas éligible au service d\'AICI.', ['070']];
        yield 'cesu_plus_not_active' => [BusinessError::CesuPlusNotActive, 'Le particulier n\'a pas le service cesu+ activé.', ['070']];
    }

    /**
     * @param list<string> $methods
     */
    #[DataProvider('catalogue')]
    public function testTextAndOperationsOfEveryCase(BusinessError $error, string $text, array $methods): void
    {
        static::assertSame($text, BusinessErrorCatalogue::text($error));
        static::assertSame(
            $methods,
            array_map(static fn(Operation $operation): string => $operation->methodNumber(), BusinessErrorCatalogue::operations($error)),
        );
    }

    public function testTheProviderHoldsEveryCaseOnce(): void
    {
        $cases = [];
        foreach (self::catalogue() as [$error]) {
            $cases[] = $error;
        }

        static::assertCount(87, $cases);
        static::assertSame(BusinessError::cases(), $cases);
    }

    #[DataProvider('catalogue')]
    public function testEveryPatternIsAValidRegexMatchingItsOwnNormalisedText(BusinessError $error, string $text): void
    {
        static::assertMatchesRegularExpression(BusinessErrorCatalogue::pattern($error), ErrorClassifier::normalise($text), $error->value);
    }

    /**
     * @return iterable<string, array{BusinessError, string}>
     */
    public static function patterns(): iterable
    {
        yield 'no placeholder: quoted, anchored' => [BusinessError::IbanInvalid, '/^veuillez indiquer un iban valide\\.$/u'];
        yield 'apostrophes are normalised' => [BusinessError::EmploymentStartsAfterToday, "/^la date de début d'emploi doit être antérieure ou égale à la date du jour\\.\$/u"];
        yield 'placeholder with a space on each side' => [
            BusinessError::FullTimeDailyWageBelowMinimum,
            '/^le salaire journalier à temps complet ne peut pas être inférieur à ?.+? ?smic\\.$/u',
        ];
        yield 'placeholder followed by a currency' => [
            BusinessError::HourlyWageBelowMinimum,
            '/^le salaire horaire ne peut être inférieur à ?.+? ?€\\.$/u',
        ];
        yield 'two placeholders' => [
            BusinessError::ParticularConstraintSmicOutOfRange,
            '/^pour les sujétions particulières le nombre de smic net par jour ne peut être inférieur à ?.+? ?et supérieur à ?.+? ?$/u',
        ];
        yield 'plus sign and quote are escaped' => [BusinessError::CesuPlusAlreadyActive, '/^cesu\\+ déjà activé pour le couple pe\\/salarié\\.$/u'];
    }

    #[DataProvider('patterns')]
    public function testPatternShape(BusinessError $error, string $expected): void
    {
        static::assertSame($expected, BusinessErrorCatalogue::pattern($error));
    }
}
