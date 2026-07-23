<?php

namespace App\Modules\Wallet\Ledger\Enums;

/**
 * Propriétaire économique ou finalité d'un compte (ADR-0003 §3, §4).
 * Liste fermée reprise directement des familles de fonds distinctes
 * énumérées par ADR-0003 §3 et architecture/05-ledger-wallet-partie-double.md,
 * jamais étendue par une configuration administrable : une nouvelle finalité
 * exige une décision architecturale, pas un paramètre versionné.
 */
enum AccountPurpose: string
{
    case Coverage = 'coverage';
    case AdvertiserCampaign = 'advertiser_campaign';
    case UserRights = 'user_rights';
    case WasplexOwnResources = 'wasplex_own_resources';
    case SocialFund = 'social_fund';
    case CardsPool = 'cards_pool';
    case TaxAndFees = 'tax_and_fees';
    case TransitPayment = 'transit_payment';
    case Clearing = 'clearing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
