<?php

namespace App\Modules\Governance\Authorization\Contracts;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use Carbon\CarbonInterface;

/**
 * Résultat explicable d'une évaluation d'autorisation (P003-B1 §15).
 *
 * `policyKey`/`policyVersion` identifient toujours la {@see PolicyVersion}
 * réellement appliquée par la décision — jamais la capacité. La capacité
 * évaluée reste identifiable séparément via `capabilityKey`/`capabilityVersion`
 * (P003-B1.3 §7) : les deux dimensions ne doivent jamais être confondues,
 * notamment dans le journal d'audit.
 */
final readonly class AuthorizationResult
{
    /**
     * @param  list<AuthorizationObligation>  $obligations
     * @param  list<string>|null  $allowedFields
     */
    public function __construct(
        public AuthorizationDecision $decision,
        public AuthorizationReason $reason,
        public ?string $policyKey,
        public ?int $policyVersion,
        public array $obligations,
        public ?CarbonInterface $validUntil,
        public string $correlationId,
        public ?array $allowedFields = null,
        public ?string $capabilityKey = null,
        public ?int $capabilityVersion = null,
    ) {}

    /**
     * @param  list<AuthorizationObligation>  $obligations
     * @param  list<string>|null  $allowedFields
     */
    public static function make(
        AuthorizationDecision $decision,
        string $reasonCode,
        string $explanation,
        string $correlationId,
        ?string $policyKey = null,
        ?int $policyVersion = null,
        array $obligations = [],
        ?CarbonInterface $validUntil = null,
        ?array $allowedFields = null,
        ?string $capabilityKey = null,
        ?int $capabilityVersion = null,
    ): self {
        return new self(
            decision: $decision,
            reason: new AuthorizationReason($reasonCode, $explanation),
            policyKey: $policyKey,
            policyVersion: $policyVersion,
            obligations: $obligations,
            validUntil: $validUntil,
            correlationId: $correlationId,
            allowedFields: $allowedFields,
            capabilityKey: $capabilityKey,
            capabilityVersion: $capabilityVersion,
        );
    }

    public function isAllowed(): bool
    {
        return in_array($this->decision, [
            AuthorizationDecision::Allowed,
            AuthorizationDecision::AllowedMasked,
            AuthorizationDecision::AllowedReadOnly,
        ], true);
    }
}
