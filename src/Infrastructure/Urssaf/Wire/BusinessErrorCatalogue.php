<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;

/**
 * The data of ADR 0005: the verbatim `description` of every `ERR_METIER` text of PDF v2.0.2 (§3.4), the operations
 * that document it, and the pattern that recognises it once normalised by {@see ErrorClassifier::normalise()}.
 *
 * The enum {@see BusinessError} holds no French text: it lives here, in the infrastructure layer.
 */
final class BusinessErrorCatalogue
{
    /**
     * Verbatim texts, keyed by the value of the {@see BusinessError} case. A placeholder (`XX,XX`, `XXX`, `X`, `XX`,
     * `YY`) stands for a value URSSAF fills in.
     */
    private const TEXTS = [
        'iban_invalid' => 'Veuillez indiquer un IBAN valide.',
        'iban_not_sepa' => 'Veuillez indiquer un IBAN valide – préfixe de l’IBAN non SEPA.',
        'bic_invalid' => 'Veuillez indiquer un BIC valide.',
        'bank_details_blacklisted' => 'CoordBancaires blacklist.',
        'civility_missing' => 'Veuillez indiquer votre civilité.',
        'last_name_missing' => 'Veuillez indiquer votre nom.',
        'last_name_invalid' => 'Veuillez indiquer un nom valide.',
        'first_name_missing' => 'Veuillez indiquer votre prénom.',
        'first_name_invalid' => 'Veuillez indiquer un prénom valide.',
        'forbidden_legal_form' => 'Vous ne pouvez pas créer de compte Cesu en tant que : SCP, SNC, SCM, SA, SARL, EURL, SCI, SELARL, UDAF, église, paroisse, association.',
        'birth_date_missing' => 'Veuillez indiquer votre date de naissance.',
        'employer_under_sixteen' => 'Vous devez avoir au moins 16 ans pour activer ce service.',
        'birth_date_invalid' => 'Veuillez indiquer une date de naissance valide.',
        'address_incomplete' => 'Veuillez indiquer votre adresse complète.',
        'street_number_missing' => 'Veuillez indiquer votre numéro de voie.',
        'street_name_missing' => 'Veuillez indiquer votre nom de voie.',
        'municipality_invalid' => 'Veuillez indiquer un code postal et une commune valides.',
        'phone_invalid' => 'Veuillez indiquer un numéro de téléphone valide.',
        'email_invalid' => 'Veuillez indiquer un identifiant valide, avec au moins 1 caractère, le séparateur @, l\'hébergeur et l\'extension.',
        'email_already_known' => 'Cet identifiant est déjà connu. Si vous disposez d\'un compte Cesu vous pouvez réinitialiser votre mot de passe à partir de la rubrique "Problème d\'identification".',
        'birth_date_mismatch' => ' La date de naissance ne correspond pas à celle de l\'\'employeur identifié par le numéro CESU renseigné.',
        'no_employer_employee_link' => ' Il n\'existe aucune lien entre le particulier employeur et le salarié (aucune prestation n\'a été réalisée par ce salarié pour l\'employeur)',
        'hourly_wage_below_minimum' => ' Le salaire horaire ne peut être inférieur à XX,XX €.',
        'overtime_requires_forty_normal_hours' => ' La déclaration d\'heures majorées est impossible si le nombre d\'heures normales est inférieur à 40.',
        'second_tier_overtime_requires_eight_hours' => 'Pour déclarer des heures majorées à 50%, le nombre d\'heures majorées à 25% doit être supérieur ou égal à 8.',
        'specific_hours_wage_below_minimum' => ' Le salaire horaire net pour une heure spécifique XXX ne peut être inférieur à XX,XX€.',
        'employee_contribution_without_benefits_in_kind' => 'La déclaration d\'une participation financière du salarié est impossible si aucun avantage en nature n\'est renseigné.',
        'employee_contribution_without_hours_and_wage' => 'La déclaration d\'une participation financière du salarié est impossible si le nombre d\'heures et le salaire horaire net ne sont pas renseignés.',
        'benefits_in_kind_contributions_above_net_salary' => 'Le montant des cotisations salariales calculé sur Avantage en nature est supérieur au salaire net déclaré.',
        'remuneration_missing' => ' Vous devez saisir au moins un élément de rémunération.',
        'reception_days_above_limit' => ' Le nombre total de journées d\'\'accueil ne peut être supérieur à 32.',
        'full_time_daily_wage_below_minimum' => ' Le salaire journalier à temps complet ne peut pas être inférieur à X SMIC.',
        'part_time_daily_wage_below_minimum' => ' Le salaire journalier à temps partiel ne peut pas être inférieur à X SMIC.',
        'particular_constraint_days_above_limit' => ' Le nombre de jours de sujétions particulières ne peut être supérieur à 32.',
        'particular_constraint_smic_out_of_range' => ' Pour les sujétions particulières le nombre de smic net par jour ne peut être inférieur à XX et supérieur à YY',
        'maintenance_allowance_days_above_limit' => ' Le nombre de jours d\'indemnités d\'entretien ne peut être supérieur à 32.',
        'guaranteed_minimum_out_of_range' => ' Pour l\'indemnité d\'entretien le nombre de minimum garanti par jour ne peut être inférieur à XX et supérieur à YY',
        'calculation_failed' => 'Une erreur s\'est produite durant la simulation des cotisations.',
        'paid_leave_declared_while_included' => 'La déclaration réalisée est soumise à la règle des congés inclus de 10% (congesPayes = true). Les informations heures de CP (heuresCp) et/ou périodes de congés payés (periodesCongesPayes) ne doivent donc pas être renseignées.',
        'duplicate_declaration' => 'Une déclaration est déjà enregistrée pour cette période d’emploi.',
        'employment_starts_after_today' => 'La date de début d\'emploi doit être antérieure ou égale à la date du jour.',
        'employment_starts_after_end' => 'La date de début d\'emploi doit être inférieure ou égale à la date de fin.',
        'employment_spans_several_months' => 'La période d\'emploi ne peut pas s\'étaler sur plusieurs mois.',
        'worked_hours_missing' => 'Veuillez indiquer un nombre d\'heures effectuées.',
        'net_total_inconsistent' => 'Le total net déclaré est incohérent.',
        'net_hourly_wage_inconsistent' => 'Le salaire horaire net est incohérent.',
        'net_hourly_wage_below_minimum' => 'Le salaire horaire net ne peut être inférieur à XX,XX €.',
        'paid_leave_hours_missing' => 'Le nombre d\'heures déclarées au titre des congés payés doit être supérieur à 0.',
        'paid_leave_periods_incomplete' => 'Toutes les périodes de congés payés doivent être renseignées.',
        'paid_leave_period_ends_before_start' => 'Veuillez indiquer des dates de début de période de congés antérieures ou égales aux dates de fin.',
        'paid_leave_periods_overlap' => 'Les périodes de congés payés déclarées se chevauchent.',
        'paid_leave_outside_employment' => 'Les congés payés doivent être inclus dans la période d\'emploi.',
        'paid_leave_days_missing' => ' Veuillez indiquer un nombre total de jours de congés pris supérieur à 0.',
        'employee_contribution_above_benefits_in_kind' => 'Le montant de la participation financière du salarié doit être inférieur au cumul des avantages en nature.',
        'employee_contribution_above_remuneration' => 'Le montant de la participation financière du salarié doit être inférieur à la rémunération déclarée hors avantage en nature.',
        'hours_without_hourly_wage' => 'Des heures ont été saisies, vous devez saisir un salaire horaire net.',
        'activity_nature_missing' => 'Veuillez sélectionner une nature d\'activité.',
        'foster_carer_unsupported' => 'Il n\'est pas possible de réaliser de déclaration pour les accueillants familiaux via l\'API',
        'employer_bank_details_inactive' => 'Pas de coordonnées bancaires actives sur le compte de l\'employeur. Veuillez contacter le particulier employeur concerné.',
        'employer_online_account_anomaly' => 'Anomalie compte en ligne employeur. Pour information la méthode de vérification d\'un compte employeur vous permet d\'anticiper ce type de blocage.',
        'employer_account_anomaly' => 'Anomalie compte employeur. Veuillez contacter le Centre National Cesu.',
        'cesu_plus_deactivated_by_employer' => 'Service Cesu+ désactivé à la demande de l\'employeur. Pour information, la méthode de vérification de l\'existence d\'un contrat Cesu+ entre un particulier employeur et un salarié vous permet d\'anticiper ce type de blocage.',
        'unpaid_salary' => 'Présence d\'un impayé sur salaire sur le compte employeur. Il est impossible de réaliser de déclaration Cesu+ sur ce compte.',
        'unpaid_contributions' => 'Présence d\'un impayé sur les cotisations ou prélèvement à la source sur le compte employeur. Il est impossible de réaliser de déclaration Cesu+ sur ce compte.',
        'employee_bank_details_inactive' => 'Pas de coordonnées bancaires actives sur le compte du salarié. Veuillez contacter le particulier employeur concerné.',
        'employee_online_account_anomaly' => 'Anomalie sur le compte en ligne salarié. Pour information la méthode de vérification d\'un compte salarié vous permet d\'anticiper ce type de blocage',
        'employee_account_anomaly' => 'Anomalie compte salarié. Veuillez contacter le Centre National Cesu',
        'cesu_plus_deactivated' => 'Service Cesu+ désactivé. Pour information la méthode de vérification de l\'existence d\'un contrat Cesu+ entre un particulier employeur et un salarié vous permet d\'anticiper ce type de blocage',
        'identical_bank_details' => 'Coordonnées bancaires employeur et salarié identiques. Veuillez contacter le particulier employeur concerné.',
        'identical_civil_status' => 'Etat civil employeur et salarié identique. Veuillez contacter le particulier employeur concerné',
        'pair_anomaly' => 'Anomalie sur le couple employeur / salarié.',
        'cesu_plus_deadline_exceeded' => 'Délai de saisie de déclaration Cesu+ dépassé. La réception de la déclaration ne doit pas être après le 15 du mois qui suit le mois déclaré. Il est cependant toujours possible de réaliser une déclaration Cesu socle.',
        'cesu_plus_net_salary_ceiling_exceeded' => 'Plafond de salaire net déclaré dépassé : il n\'est pas possible de réaliser une déclaration cesu+ avec un salaire supérieur à 6000 € pour une période donnée.',
        'employer_cesu_number_unknown' => 'N° CESU du particulier employeur inconnu.',
        'pair_agreement_missing' => 'Absence de l\'accord du couple particulier employeur / salarié.',
        'employee_cesu_number_unknown' => 'N° CESU du salarié inconnu.',
        'no_contract' => 'Aucun contrat existant pour le couple particulier employeur / salarié.',
        'cesu_plus_already_active' => 'CESU+ déjà activé pour le couple PE/salarié.',
        'cesu_plus_blocked_for_employer' => 'Blocage Cesu+ pour le particulier employeur.',
        'cesu_plus_blocked_for_employee' => 'Blocage Cesu+ pour le salarié.',
        'cesu_plus_blocked_for_pair' => 'Blocage Cesu+ pour le couple particulier employeur/salarié.',
        'apa_pch_exemption' => 'Employeur ayant une exonération APA/PCH.',
        'employer_not_found' => 'Aucun employeur n\'a été trouvé avec ces informations.',
        'birth_municipality_code_missing' => 'Le paramètre codeCommuneNaissance est manquant.',
        'not_recognised_by_tax_administration' => 'Le particulier n\'a pas été reconnu par l\'API DGFIP R2P.',
        'not_eligible_for_aici' => 'Le particulier n\'est pas éligible au service d\'AICI.',
        'cesu_plus_not_active' => 'Le particulier n\'a pas le service cesu+ activé.',
    ];

    /**
     * Operations whose PDF table documents the text, keyed by the value of the {@see BusinessError} case, in
     * {@see Operation::cases()} order. A table, not a `match` over the cases: an enum `match` is a chain of
     * comparisons (up to 87 branches per call) and the classifier calls this method for every case of a description.
     */
    private const OPERATIONS = [
        'iban_invalid' => [Operation::RegisterEmployer],
        'iban_not_sepa' => [Operation::RegisterEmployer],
        'bic_invalid' => [Operation::RegisterEmployer],
        'bank_details_blacklisted' => [Operation::RegisterEmployer],
        'civility_missing' => [Operation::RegisterEmployer],
        'last_name_missing' => [Operation::RegisterEmployer],
        'last_name_invalid' => [Operation::RegisterEmployer],
        'first_name_missing' => [Operation::RegisterEmployer],
        'first_name_invalid' => [Operation::RegisterEmployer],
        'forbidden_legal_form' => [Operation::RegisterEmployer],
        'birth_date_missing' => [Operation::RegisterEmployer],
        'employer_under_sixteen' => [Operation::RegisterEmployer],
        'birth_date_invalid' => [Operation::RegisterEmployer],
        'address_incomplete' => [Operation::RegisterEmployer],
        'street_number_missing' => [Operation::RegisterEmployer],
        'street_name_missing' => [Operation::RegisterEmployer],
        'municipality_invalid' => [Operation::RegisterEmployer],
        'phone_invalid' => [Operation::RegisterEmployer],
        'email_invalid' => [Operation::RegisterEmployer],
        'email_already_known' => [Operation::RegisterEmployer],
        'birth_date_mismatch' => [Operation::VerifyEmployer],
        'no_employer_employee_link' => [Operation::VerifyEmployer],
        'hourly_wage_below_minimum' => [Operation::EstimateContributions],
        'overtime_requires_forty_normal_hours' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'second_tier_overtime_requires_eight_hours' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'specific_hours_wage_below_minimum' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'employee_contribution_without_benefits_in_kind' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'employee_contribution_without_hours_and_wage' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'benefits_in_kind_contributions_above_net_salary' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'remuneration_missing' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'reception_days_above_limit' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'full_time_daily_wage_below_minimum' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'part_time_daily_wage_below_minimum' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'particular_constraint_days_above_limit' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'particular_constraint_smic_out_of_range' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'maintenance_allowance_days_above_limit' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'guaranteed_minimum_out_of_range' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'calculation_failed' => [Operation::EstimateContributions, Operation::DeclareSalary],
        'paid_leave_declared_while_included' => [Operation::DeclareSalary],
        'duplicate_declaration' => [Operation::DeclareSalary],
        'employment_starts_after_today' => [Operation::DeclareSalary],
        'employment_starts_after_end' => [Operation::DeclareSalary],
        'employment_spans_several_months' => [Operation::DeclareSalary],
        'worked_hours_missing' => [Operation::DeclareSalary],
        'net_total_inconsistent' => [Operation::DeclareSalary],
        'net_hourly_wage_inconsistent' => [Operation::DeclareSalary],
        'net_hourly_wage_below_minimum' => [Operation::DeclareSalary],
        'paid_leave_hours_missing' => [Operation::DeclareSalary],
        'paid_leave_periods_incomplete' => [Operation::DeclareSalary],
        'paid_leave_period_ends_before_start' => [Operation::DeclareSalary],
        'paid_leave_periods_overlap' => [Operation::DeclareSalary],
        'paid_leave_outside_employment' => [Operation::DeclareSalary],
        'paid_leave_days_missing' => [Operation::DeclareSalary],
        'employee_contribution_above_benefits_in_kind' => [Operation::DeclareSalary],
        'employee_contribution_above_remuneration' => [Operation::DeclareSalary],
        'hours_without_hourly_wage' => [Operation::DeclareSalary],
        'activity_nature_missing' => [Operation::DeclareSalary],
        'foster_carer_unsupported' => [Operation::DeclareSalary],
        'employer_bank_details_inactive' => [Operation::DeclareSalary],
        'employer_online_account_anomaly' => [Operation::DeclareSalary],
        'employer_account_anomaly' => [Operation::DeclareSalary],
        'cesu_plus_deactivated_by_employer' => [Operation::DeclareSalary],
        'unpaid_salary' => [Operation::DeclareSalary],
        'unpaid_contributions' => [Operation::DeclareSalary],
        'employee_bank_details_inactive' => [Operation::DeclareSalary],
        'employee_online_account_anomaly' => [Operation::DeclareSalary],
        'employee_account_anomaly' => [Operation::DeclareSalary],
        'cesu_plus_deactivated' => [Operation::DeclareSalary],
        'identical_bank_details' => [Operation::DeclareSalary],
        'identical_civil_status' => [Operation::DeclareSalary],
        'pair_anomaly' => [Operation::DeclareSalary],
        'cesu_plus_deadline_exceeded' => [Operation::DeclareSalary],
        'cesu_plus_net_salary_ceiling_exceeded' => [Operation::DeclareSalary],
        'employer_cesu_number_unknown' => [Operation::ActivateCesuPlus],
        'pair_agreement_missing' => [Operation::ActivateCesuPlus],
        'employee_cesu_number_unknown' => [Operation::ActivateCesuPlus],
        'no_contract' => [Operation::ActivateCesuPlus],
        'cesu_plus_already_active' => [Operation::ActivateCesuPlus],
        'cesu_plus_blocked_for_employer' => [Operation::ActivateCesuPlus],
        'cesu_plus_blocked_for_employee' => [Operation::ActivateCesuPlus],
        'cesu_plus_blocked_for_pair' => [Operation::ActivateCesuPlus],
        'apa_pch_exemption' => [Operation::ActivateCesuPlus],
        'employer_not_found' => [Operation::ActivateAici],
        'birth_municipality_code_missing' => [Operation::ActivateAici],
        'not_recognised_by_tax_administration' => [Operation::ActivateAici],
        'not_eligible_for_aici' => [Operation::ActivateAici],
        'cesu_plus_not_active' => [Operation::ActivateAici],
    ];

    /**
     * Placeholders of the normalised texts (lower case), as whole tokens, with the single space around them.
     */
    private const PLACEHOLDER = '/ ?(?<![\p{L}\p{N}])(?:x{1,3}(?:,x{2})?|yy)(?![\p{L}\p{N}]) ?/u';

    /**
     * Verbatim PDF v2.0.2 text.
     */
    public static function text(BusinessError $error): string
    {
        return self::TEXTS[$error->value];
    }

    /**
     * Operations whose PDF table documents the text, in {@see Operation::cases()} order.
     *
     * @return list<Operation>
     */
    public static function operations(BusinessError $error): array
    {
        return self::OPERATIONS[$error->value];
    }

    /**
     * Anchored pattern of the normalised text: a full-string match only, with a wildcard at the placeholders alone, so
     * that a text never matches a longer or a shorter one. The single space around a placeholder is optional
     * (`11,65 €` and `11,65€`).
     *
     * The placeholders are replaced by a single regex that also absorbs the spaces around them.
     */
    public static function pattern(BusinessError $error): string
    {
        $quoted = preg_quote(ErrorClassifier::normalise(self::text($error)), '/');

        return '/^' . (preg_replace(self::PLACEHOLDER, ' ?.+? ?', $quoted) ?? $quoted) . '$/u';
    }
}
