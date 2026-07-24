<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisionne une Campaign et ses trois comptes Ledger dédiés
 * (disponible/réservé/consommé, compartiment « campagne annonceur »,
 * ADR-0003 §4) — voir P005-A §3.D. Utilise `Account` tel quel : aucune
 * nouvelle logique de compte.
 *
 * Ces trois comptes ne sont provisionnés qu'ici, jamais recréés ni
 * dupliqués : leur identifiant est dérivé de l'identifiant de la campagne,
 * lui-même généré avant l'écriture pour rester déterministe.
 */
class CampaignService
{
    public function createCampaign(AdvertiserProfile $advertiser, string $code, string $currency): Campaign
    {
        return DB::transaction(function () use ($advertiser, $code, $currency): Campaign {
            $campaignId = (string) Str::uuid7();
            $accountSuffix = str_replace('-', '', $campaignId);

            $available = Account::create([
                'code' => "advertiser_campaign.{$accountSuffix}.available",
                'nature' => AccountNature::Liability,
                'purpose' => AccountPurpose::AdvertiserCampaign,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'advertising',
                'compartment' => $code,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);

            $reserved = Account::create([
                'code' => "advertiser_campaign.{$accountSuffix}.reserved",
                'nature' => AccountNature::Liability,
                'purpose' => AccountPurpose::AdvertiserCampaign,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'advertising',
                'compartment' => $code,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);

            $consumed = Account::create([
                'code' => "advertiser_campaign.{$accountSuffix}.consumed",
                'nature' => AccountNature::Liability,
                'purpose' => AccountPurpose::AdvertiserCampaign,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'advertising',
                'compartment' => $code,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);

            return Campaign::create([
                'id' => $campaignId,
                'advertiser_profile_id' => $advertiser->id,
                'code' => $code,
                'currency' => $currency,
                'available_account_id' => $available->id,
                'reserved_account_id' => $reserved->id,
                'consumed_account_id' => $consumed->id,
            ]);
        });
    }
}
