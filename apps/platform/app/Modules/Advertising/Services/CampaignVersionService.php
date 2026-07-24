<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Advertising\Services\Exceptions\CampaignVersionNotApprovableException;
use App\Modules\Advertising\Services\Exceptions\IndependentApproverRequiredException;
use App\Modules\Advertising\Services\Exceptions\SelfApprovalRefusedException;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'une CampaignVersion (ADR-0010 §2, §3, §5). Une approbation ne
 * modifie jamais une ancienne version : chaque décision matérielle
 * compose une nouvelle ligne, sur le modèle exact de
 * `GrantManager` (P003-B1 §12).
 */
class CampaignVersionService
{
    /**
     * @param  array<string, mixed>  $creations
     * @param  array<string, mixed>  $expectedEvent
     * @param  array<string, mixed>  $destination
     * @param  list<string>  $territory
     */
    public function propose(
        Campaign $campaign,
        SectorClassification $sector,
        array $creations,
        array $expectedEvent,
        array $destination,
        array $territory,
        PersonAccountLink $author,
        ?string $pricingConfigurationKey = null,
        ?int $pricingConfigurationVersion = null,
        ?string $rewardConfigurationKey = null,
        ?int $rewardConfigurationVersion = null,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validUntil = null,
    ): CampaignVersion {
        $nextVersion = 1 + (int) CampaignVersion::query()
            ->where('campaign_id', $campaign->id)
            ->max('version');

        return CampaignVersion::create([
            'campaign_id' => $campaign->id,
            'version' => $nextVersion,
            'state' => CampaignVersionState::Draft,
            'sector_classification_id' => $sector->id,
            'creations' => $creations,
            'expected_event' => $expectedEvent,
            'destination' => $destination,
            'territory' => $territory,
            'pricing_configuration_key' => $pricingConfigurationKey,
            'pricing_configuration_version' => $pricingConfigurationVersion,
            'reward_configuration_key' => $rewardConfigurationKey,
            'reward_configuration_version' => $rewardConfigurationVersion,
            'valid_from' => $validFrom ?? now(),
            'valid_until' => $validUntil,
            'author_person_account_link_id' => $author->id,
        ]);
    }

    public function submitForReview(CampaignVersion $version): CampaignVersion
    {
        if ($version->state !== CampaignVersionState::Draft) {
            throw new CampaignVersionNotApprovableException(
                "seule une version à l'état draft peut être soumise en revue ; état actuel : {$version->state->value}"
            );
        }

        $version->forceFill(['state' => CampaignVersionState::InReview])->save();

        return $version->fresh();
    }

    /**
     * @throws CampaignVersionNotApprovableException La version n'est plus à l'état draft/in_review.
     * @throws IndependentApproverRequiredException Le secteur exige un approbateur indépendant (ADR-0010 §5).
     * @throws SelfApprovalRefusedException L'approbateur transmis est l'auteur de la version.
     */
    public function approve(CampaignVersion $version, ?PersonAccountLink $approver = null): CampaignVersion
    {
        if (! in_array($version->state, [CampaignVersionState::Draft, CampaignVersionState::InReview], true)) {
            throw new CampaignVersionNotApprovableException(
                "seule une version à l'état draft ou in_review peut être approuvée ; état actuel : {$version->state->value}"
            );
        }

        $sector = $version->sectorClassification;

        if ($approver !== null && $approver->id === $version->author_person_account_link_id) {
            throw new SelfApprovalRefusedException(
                "l'auteur d'une campagne ne peut être son propre approbateur (ADR-0010 §5)"
            );
        }

        if ($sector->requiresIndependentApprover() && $approver === null) {
            throw new IndependentApproverRequiredException(
                "le secteur {$sector->sector} ({$sector->review_level->value}) exige une validation humaine indépendante de son créateur (ADR-0010 §5)"
            );
        }

        return DB::transaction(function () use ($version, $approver): CampaignVersion {
            // Au plus une version approuvée à la fois par campagne : celle
            // déjà approuvée, s'il en existe une, est retirée (transition
            // d'état, jamais une mutation de son contenu) avant que la
            // nouvelle ne prenne sa place.
            CampaignVersion::query()
                ->where('campaign_id', $version->campaign_id)
                ->where('state', CampaignVersionState::Approved)
                ->update([
                    'state' => CampaignVersionState::Retired,
                    'retired_at' => now(),
                ]);

            $version->forceFill([
                'state' => CampaignVersionState::Approved,
                'approved_at' => now(),
                'approver_person_account_link_id' => $approver?->id,
            ])->save();

            return $version->fresh();
        });
    }

    public function suspend(CampaignVersion $version): CampaignVersion
    {
        $version->forceFill(['state' => CampaignVersionState::Suspended])->save();

        return $version->fresh();
    }

    public function retire(CampaignVersion $version): CampaignVersion
    {
        $version->forceFill(['state' => CampaignVersionState::Retired, 'retired_at' => now()])->save();

        return $version->fresh();
    }
}
