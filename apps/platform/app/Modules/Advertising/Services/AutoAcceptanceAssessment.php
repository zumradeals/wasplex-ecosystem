<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\FraudDecision;

/**
 * Verdict de {@see QualifiedEventAutoAcceptancePolicy} sur une
 * auto-soumission : soit l'acceptation automatique avec la version exacte
 * des règles appliquées (à épingler sur l'événement, jamais un log), soit
 * un maintien en attente d'examen humain avec sa raison et la décision
 * anti-fraude graduée à poser (AMD-0010 §10 — un dépassement de quota est
 * une anomalie à examiner, jamais une sanction automatique, AMD-0010 §16).
 */
final readonly class AutoAcceptanceAssessment
{
    private function __construct(
        public bool $accepts,
        public ?string $holdReason,
        public FraudDecision $fraudDecision,
        public ?string $rulesConfigurationKey,
        public ?int $rulesConfigurationVersion,
    ) {}

    public static function accept(string $rulesConfigurationKey, int $rulesConfigurationVersion): self
    {
        return new self(
            accepts: true,
            holdReason: null,
            fraudDecision: FraudDecision::None,
            rulesConfigurationKey: $rulesConfigurationKey,
            rulesConfigurationVersion: $rulesConfigurationVersion,
        );
    }

    public static function hold(string $reason, FraudDecision $fraudDecision = FraudDecision::None): self
    {
        return new self(
            accepts: false,
            holdReason: $reason,
            fraudDecision: $fraudDecision,
            rulesConfigurationKey: null,
            rulesConfigurationVersion: null,
        );
    }
}
