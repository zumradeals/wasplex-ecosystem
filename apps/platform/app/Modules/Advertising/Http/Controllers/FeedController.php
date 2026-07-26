<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Projections\SocialEngagementProjection;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubject;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feed (W4, L03 UX-0003) : point d'entrée quotidien de l'utilisateur —
 * remplace le placeholder générique de `/dashboard`. Débloque enfin
 * l'affichage réel des campagnes déjà construites par P005 A-F.
 *
 * Aucune capacité `Governance/Authorization` nouvelle : parcourir les
 * campagnes diffusées est un contenu public de diffusion, jamais une
 * ressource personnelle (à la différence de `wallet.view`/`campaign.view`,
 * qui protègent des données propres à une personne ou un dossier
 * annonceur). Seule la soumission de preuve (`event.self_submit`, déjà
 * gouvernée) exige une autorisation réelle.
 *
 * Volontairement minimal (noyau, TD-0006 lignée) : une seule campagne
 * approuvée à la fois par définition existante (ADR-0010 §5), aucune
 * correspondance d'audience réelle par personne (`AudienceSegment` reste
 * un agrégat, jamais un ciblage individuel — AMD-0009 §13) : ce noyau
 * montre toute campagne active, approuvée, financée et dont le prix est
 * résolvable, sans encore filtrer par pertinence d'audience ni limiter la
 * fréquence par utilisateur (ADR-0002 §3.3, quotas différés).
 */
class FeedController extends Controller
{
    public function __construct(
        private readonly CampaignBudgetProjection $budgetProjection,
        private readonly QualifiedEventPricingResolver $pricingResolver,
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly PersonBalanceProjection $balanceProjection,
        private readonly SocialEngagementProjection $socialEngagementProjection,
    ) {}

    public function index(Request $request): Response
    {
        $campaigns = Campaign::query()
            ->where('state', CampaignState::Active)
            ->with(['advertiserProfile', 'versions' => function ($query): void {
                $query->where('state', CampaignVersionState::Approved);
            }])
            ->get()
            ->filter(fn (Campaign $campaign): bool => $campaign->versions->isNotEmpty());

        $ads = $campaigns
            ->map(function (Campaign $campaign): ?array {
                $version = $campaign->versions->first();

                return $this->eligibleAd($campaign, $version);
            })
            ->filter()
            ->values()
            ->all();

        // Résolu une seule fois, réutilisé pour le solde Wallet et les
        // signaux sociaux — jamais deux résolutions du même sujet.
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            $subject = null;
        }

        $ads = $this->withSocialEngagement($ads, $subject);

        return Inertia::render('dashboard', [
            'ads' => $ads,
            'wallet_balance' => $this->walletBalanceFor($subject),
        ]);
    }

    /**
     * Compteurs réels agrégés (jamais mockés) et état du sujet (a-t-il
     * aimé / mis en favori ?) pour chaque publicité affichée — Lot 3 Phase
     * A (décision de Koné 2026-07-26). Sans sujet résolvable, l'état
     * viewer reste `false` partout (jamais deviné), les compteurs restent
     * publics : voir le comportement par défaut, cohérent avec le reste du
     * Feed qui reste un contenu de diffusion public.
     *
     * @param  array<int, array<string, mixed>>  $ads
     * @return array<int, array<string, mixed>>
     */
    private function withSocialEngagement(array $ads, ?AuthenticatedSubject $subject): array
    {
        if ($ads === []) {
            return $ads;
        }

        $versionIds = array_map(static fn (array $ad): string => $ad['campaign_version_id'], $ads);
        $counts = $this->socialEngagementProjection->countsForMany($versionIds);
        $viewerState = $subject !== null
            ? $this->socialEngagementProjection->viewerStateForMany($versionIds, $subject->personAccountLink)
            : [];

        return array_map(function (array $ad) use ($counts, $viewerState): array {
            $id = $ad['campaign_version_id'];

            return [
                ...$ad,
                'likes_count' => $counts[$id]['likes'] ?? 0,
                'favorites_count' => $counts[$id]['favorites'] ?? 0,
                'shares_count' => $counts[$id]['shares'] ?? 0,
                'liked' => $viewerState[$id]['liked'] ?? false,
                'favorited' => $viewerState[$id]['favorited'] ?? false,
            ];
        }, $ads);
    }

    /**
     * Solde WP propre de la personne pour le compteur du Feed — même
     * gouvernance que `GET /wallet/balance` (`wallet.view`, portée self,
     * inclus dans `user.base` à l'inscription) : un sujet non résolvable
     * ou non habilité n'obtient simplement pas de compteur (null), jamais
     * un solde inventé ni une erreur qui casse le Feed.
     *
     * @return array{available: int, currency: string|null}|null
     */
    private function walletBalanceFor(?AuthenticatedSubject $subject): ?array
    {
        if ($subject === null) {
            return null;
        }

        $personId = $subject->personAccountLink->person_id;
        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'wallet.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'wallet.balance',
                resourceId: $personId,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
                territoryCodes: [],
                environment: $environment,
            ),
            environment: $environment,
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException) {
            return null;
        }

        $balances = $this->balanceProjection->forPerson($personId);
        $first = $balances[0] ?? null;

        // Aucune rémunération encore reçue : un vrai zéro, pas une devise
        // inventée — la devise s'affichera dès le premier crédit réel.
        return [
            'available' => $first['available'] ?? 0,
            'currency' => $first['currency'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eligibleAd(Campaign $campaign, CampaignVersion $version): ?array
    {
        $available = $this->budgetProjection->available($campaign);

        if ($available <= 0) {
            return null;
        }

        try {
            $reward = $this->pricingResolver->resolveBasePrice($version);
        } catch (PricingConfigurationNotResolvableException) {
            return null;
        }

        if ($reward > $available) {
            return null;
        }

        return [
            'campaign_version_id' => $version->id,
            // UX-0001 §9 : « Avant une publicité, l'utilisateur voit :
            // annonceur ; format ; durée ou condition ; gain potentiel… ».
            // Le nom légal est la seule identité annonceur constitutionnelle —
            // jamais un pseudonyme commercial servant de clé (CLAUDE.md §2).
            'advertiser' => $campaign->advertiserProfile->legal_name,
            'headline' => $version->creations['headline'] ?? $campaign->code,
            'format' => $version->expected_event['format'] ?? 'display',
            'condition' => $version->expected_event['condition'] ?? 'completion',
            'reward_amount' => $reward,
            'currency' => $campaign->currency,
        ];
    }
}
