<?php

namespace App\Modules\Advertising\Enums;

/**
 * Cycle de vie d'une CampaignVersion (ADR-0010 §3 : « draft, en revue,
 * approuvée, suspendue, retirée »). Immuable sémantiquement dès
 * `Approved` : seules les transitions d'état restent permises ensuite
 * (déclencheur `campaign_versions_prevent_semantic_mutation`).
 */
enum CampaignVersionState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Retired = 'retired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
