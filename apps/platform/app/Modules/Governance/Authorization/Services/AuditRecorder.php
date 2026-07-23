<?php

namespace App\Modules\Governance\Authorization\Services;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\GrantEventType;
use App\Modules\Governance\Authorization\Models\AuthorizationDecisionRecord;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\GrantEvent;
use App\Modules\Identity\Models\PersonAccountLink;

/**
 * Écrit les traces append-only de gouvernance (P003-B1 §13, §17).
 */
class AuditRecorder
{
    public function recordGrantEvent(
        Grant $grant,
        PersonAccountLink $actor,
        GrantEventType $eventType,
        string $correlationId,
        ?string $reason = null,
    ): GrantEvent {
        return GrantEvent::create([
            'grant_id' => $grant->id,
            'actor_person_account_link_id' => $actor->id,
            'organization_id' => $grant->membership?->organization_id,
            'event_type' => $eventType,
            'reason' => $reason,
            'policy_version_id' => $grant->policy_version_id,
            'correlation_id' => $correlationId,
        ]);
    }

    public function recordAuthorizationDecision(
        AuthorizationRequest $request,
        AuthorizationResult $result,
        ?string $membershipId,
        ?string $organizationId,
        ?int $capabilityVersion,
        ?int $policyVersion,
    ): AuthorizationDecisionRecord {
        return AuthorizationDecisionRecord::create([
            'correlation_id' => $request->correlationId,
            'person_account_link_id' => $request->personAccountLinkId,
            'membership_id' => $membershipId,
            'organization_id' => $organizationId,
            'capability_key' => $request->capabilityKey,
            'capability_version' => $capabilityVersion,
            'purpose_key' => $request->purposeKey,
            'resource_type' => $request->resource->resourceType,
            'resource_id' => $request->resource->resourceId,
            'operation' => $request->operation,
            'decision' => $result->decision,
            'reason_code' => $result->reason->code,
            'policy_key' => $result->policyKey,
            'policy_version' => $policyVersion,
            'obligations' => $result->obligations === [] ? null : array_map(
                fn ($obligation): array => ['type' => $obligation->type, 'payload' => $obligation->payload],
                $result->obligations,
            ),
            'occurred_at' => $request->evaluatedAt,
        ]);
    }
}
