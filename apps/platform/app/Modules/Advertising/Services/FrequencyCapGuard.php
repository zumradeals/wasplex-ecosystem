<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Models\FrequencyCapBounds;
use App\Modules\Advertising\Models\QualifiedEvent;
use RuntimeException;

/**
 * Frontière du plafond de revisionnage gratuit (instruction explicite du
 * fondateur, 2026-07-31) : au-delà de la récompense unique par personne
 * et par `CampaignVersion` ({@see QualifiedEvent::$already_rewarded}),
 * une personne ne peut revoir la même publicité qu'un nombre borné de
 * fois — quotidien et total — avant que celle-ci ne disparaisse
 * entièrement de son Feed et de toute nouvelle soumission. Compte
 * toujours les `QualifiedEvent` déjà `accepted` (jamais un compteur
 * stocké séparément, ADR-0003 §19).
 */
class FrequencyCapGuard
{
    /**
     * @throws RuntimeException Aucune borne active (ADR-0002 §7.3).
     */
    public function activeBounds(): FrequencyCapBounds
    {
        $bounds = FrequencyCapBounds::query()->where('state', 'active')->first();

        if ($bounds === null) {
            throw new RuntimeException('aucune borne de fréquence de revisionnage active (ADR-0002 §7.3)');
        }

        return $bounds;
    }

    public function hasReachedCap(string $beneficiaryPersonAccountLinkId, string $campaignVersionId): bool
    {
        $bounds = $this->activeBounds();

        $baseQuery = fn () => QualifiedEvent::query()
            ->where('beneficiary_person_account_link_id', $beneficiaryPersonAccountLinkId)
            ->where('campaign_version_id', $campaignVersionId)
            ->where('billing_status', BillingStatus::Accepted);

        if ($baseQuery()->count() >= $bounds->lifetime_free_view_limit) {
            return true;
        }

        return $baseQuery()->where('occurred_at', '>=', now()->startOfDay())->count() >= $bounds->daily_free_view_limit;
    }
}
