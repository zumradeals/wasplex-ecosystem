<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\AdvertiserProfileStatus;
use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclaration par une personne de son propre dossier annonceur (P007,
 * ADR-0010 §3) — jamais un dossier pour un tiers : `$representative` est
 * toujours la liaison personne-compte du sujet authentifié appelant,
 * jamais un identifiant transmis par le client (même invariant que
 * {@see CampaignController}).
 *
 * Dette assumée, cohérente avec TD-0004-D (dossier annonceur minimal,
 * migration `2026_07_25_100012`) : le dossier est créé directement à
 * l'état `active`, sans vérification KYC ni revue humaine —
 * `02-preuves-moderation-et-destinations.md` §1 exige ces preuves avant
 * *diffusion*, pas avant la simple création d'un dossier.
 *
 * Émet aussi, dans la même transaction, les grants du rôle modèle
 * `advertiser.representative` ({@see GrantAutoIssuer}, migration
 * `2026_07_25_200002`) : « toute personne devenant représentante d'un
 * dossier annonceur » désigne exactement ce moment — sans cela, le dossier
 * créé serait inutilisable (`campaign.create` resterait sans grant actif).
 */
class AdvertiserOnboardingService
{
    public function __construct(
        private readonly GrantAutoIssuer $grantAutoIssuer,
    ) {}

    /**
     * @param  list<string>  $territories
     */
    public function registerAdvertiser(
        PersonAccountLink $representative,
        string $legalName,
        ?string $legalIdentifier,
        string $countryCode,
        array $territories,
    ): AdvertiserProfile {
        return DB::transaction(function () use ($representative, $legalName, $legalIdentifier, $countryCode, $territories): AdvertiserProfile {
            $profile = AdvertiserProfile::create([
                'legal_name' => $legalName,
                'legal_identifier' => $legalIdentifier,
                'country_code' => $countryCode,
                'representative_person_account_link_id' => $representative->id,
                'licenses' => [],
                'territories' => $territories,
                'status' => AdvertiserProfileStatus::Active,
            ]);

            $this->grantAutoIssuer->issueRoleTemplateGrants($representative, 'advertiser.representative', (string) Str::uuid());

            return $profile;
        });
    }
}
