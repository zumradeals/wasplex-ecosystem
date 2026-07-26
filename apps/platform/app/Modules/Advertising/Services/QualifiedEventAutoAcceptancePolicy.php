<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Services\ConfigurationResolver;
use App\Modules\Governance\Configuration\Services\Exceptions\NoActiveConfigurationException;
use App\Modules\Identity\Models\PersonAccountLink;

/**
 * Contrôles serveur déterministes qui décident si une auto-soumission
 * propre est acceptée immédiatement dans la même requête (arbitrage
 * Koné/SIRR 2026-07-26) ou maintenue en attente d'examen humain — la
 * validation humaine par événement devient l'exception (cas suspects),
 * jamais le défaut.
 *
 * Fail-closed : sans configuration de règles active dans le registre
 * central (ADR-0002, jamais un seuil codé en dur), aucun événement n'est
 * accepté automatiquement — comportement historique (`Pending`) conservé.
 * Ce service ne calcule aucun score anti-fraude (ADR-0010 §8, hors
 * périmètre) : ses contrôles sont déterministes et rejouables, et un
 * dépassement de quota produit une anomalie graduée à examiner (AMD-0010
 * §10), jamais un refus ni une sanction (AMD-0010 §16).
 */
class QualifiedEventAutoAcceptancePolicy
{
    /**
     * Nombre maximal d'événements qualifiés rémunérables par bénéficiaire
     * et par jour au-delà duquel l'acceptation redevient humaine. La
     * valeur vit exclusivement dans le registre Configuration (versionnée,
     * approuvée, auditable) ; seule la clé est connue du code.
     */
    public const RULES_CONFIGURATION_KEY = 'advertising.self_submission.daily_auto_acceptance_quota';

    public const HOLD_RULES_NOT_CONFIGURED = 'auto_acceptance_not_configured';

    public const HOLD_COMPLETION_NOT_ATTESTED = 'completion_not_attested';

    public const HOLD_DAILY_QUOTA_REACHED = 'daily_quota_reached';

    public function __construct(
        private readonly ConfigurationResolver $configurationResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function assess(PersonAccountLink $beneficiary, array $evidence): AutoAcceptanceAssessment
    {
        try {
            $rules = $this->configurationResolver->resolve(self::RULES_CONFIGURATION_KEY);
        } catch (NoActiveConfigurationException) {
            return AutoAcceptanceAssessment::hold(self::HOLD_RULES_NOT_CONFIGURED);
        }

        if ($rules->definition->value_type !== ValueType::Integer) {
            // Une définition mal typée n'est pas une règle applicable :
            // même prudence que l'absence de configuration.
            return AutoAcceptanceAssessment::hold(self::HOLD_RULES_NOT_CONFIGURED);
        }

        if (($evidence['completed'] ?? null) !== true) {
            return AutoAcceptanceAssessment::hold(self::HOLD_COMPLETION_NOT_ATTESTED);
        }

        // Journée au sens du fuseau de l'application ; les rejets ne
        // consomment pas le quota (un événement refusé n'a créé aucun
        // droit), les pending si (sinon rejouer des soumissions douteuses
        // permettrait de contourner le plafond).
        $todayCount = QualifiedEvent::query()
            ->where('beneficiary_person_account_link_id', $beneficiary->id)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->where('billing_status', '!=', BillingStatus::Rejected)
            ->count();

        $quota = (int) $rules->value;

        if ($todayCount >= $quota) {
            return AutoAcceptanceAssessment::hold(self::HOLD_DAILY_QUOTA_REACHED, FraudDecision::Anomaly);
        }

        return AutoAcceptanceAssessment::accept(self::RULES_CONFIGURATION_KEY, $rules->version);
    }
}
