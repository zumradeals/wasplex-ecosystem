<?php

namespace App\Modules\Advertising\Enums;

/**
 * Mesure conservatoire d'un ModerationCase
 * (`03-signalements-sanctions-et-remuneration.md` §2 : « limiter la
 * diffusion, suspendre la campagne, bloquer une destination ou
 * l'annonceur »).
 */
enum PrecautionaryMeasure: string
{
    case None = 'none';
    case LimitedDiffusion = 'limited_diffusion';
    case CampaignSuspended = 'campaign_suspended';
    case DestinationBlocked = 'destination_blocked';
    case AdvertiserBlocked = 'advertiser_blocked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
