<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Projections\SocialEngagementProjection;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Alerts\Projections\PublicAlertFeedProjection;
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
 * Instruction explicite du fondateur, 2026-07-31 : filtre désormais
 * réellement par correspondance d'audience individuelle
 * ({@see AudienceSegmentGuard::matchesPerson()}) — une campagne dont le
 * segment ne correspond pas au profil publicitaire consenti du sujet
 * n'apparaît plus dans son Feed, fermant l'écart documenté jusqu'ici (le
 * calcul de correspondance existait déjà côté écriture/estimation, jamais
 * réutilisé côté lecture). `reward_amount` reflète aussi le type
 * économique et le quota mensuel déjà consommé de la personne précise
 * (`CampaignBudgetService::previewUserShareForPerson()`), jamais un
 * montant générique identique pour tout le monde. La fréquence maximale
 * par utilisateur (ADR-0002 §3.3) reste hors périmètre de ce lot.
 *
 * P008-A (mission §15) : reçoit aussi une petite surface d'alertes
 * communautaires publiées (`alerts.publications`, lecture seule via
 * {@see PublicAlertFeedProjection}) — jamais mêlée aux `ads` elles-mêmes.
 * Une alerte n'est jamais comptée comme vue publicitaire, ne consomme
 * aucun quota et ne déclenche aucun événement qualifié : elle est rendue
 * par une surface compacte séparée côté React, jamais insérée dans le
 * flux de lecture vidéo. L'interleaving à cadence configurable (« toutes
 * les 5 ou 10 publicités ») décrit par la mission reste différé — voir la
 * dette technique du dossier final ; ce lot branche uniquement la lecture,
 * jamais une simulation.
 */
class FeedController extends Controller
{
    public function __construct(
        private readonly CampaignBudgetProjection $budgetProjection,
        private readonly QualifiedEventPricingResolver $pricingResolver,
        private readonly CampaignBudgetService $campaignBudgetService,
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly PersonBalanceProjection $balanceProjection,
        private readonly SocialEngagementProjection $socialEngagementProjection,
        private readonly PublicAlertFeedProjection $publicAlertFeed,
        private readonly AudienceSegmentGuard $audienceSegmentGuard,
    ) {}

    public function index(Request $request): Response
    {
        // Résolu une seule fois, réutilisé pour le solde Wallet, les
        // signaux sociaux et — depuis ce lot — la correspondance
        // d'audience et le gain réel : jamais deux résolutions du même
        // sujet.
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            $subject = null;
        }

        // Profil publicitaire consenti du sujet, chargé une seule fois
        // (pas par campagne) : une personne sans sujet résolvable ou sans
        // profil ne correspond alors qu'aux campagnes non ciblées
        // (critères vides), jamais à un profil deviné.
        $profile = $subject !== null
            ? PersonAdvertisingProfile::query()->where('person_id', $subject->personAccountLink->person_id)->first()
            : null;

        $campaigns = Campaign::query()
            ->where('state', CampaignState::Active)
            ->with(['advertiserProfile', 'versions' => function ($query): void {
                $query->where('state', CampaignVersionState::Approved)->with('audienceSegment');
            }])
            ->get()
            ->filter(fn (Campaign $campaign): bool => $campaign->versions->isNotEmpty());

        $ads = $campaigns
            ->map(function (Campaign $campaign) use ($subject, $profile): ?array {
                $version = $campaign->versions->first();

                return $this->eligibleAd($campaign, $version, $subject, $profile);
            })
            ->filter()
            ->values()
            ->all();

        $ads = $this->withSocialEngagement($ads, $subject);

        $communityAlerts = $this->publicAlertFeed->published(countryCode: null, limit: 5)
            ->map(fn ($publication): array => [
                'publication_id' => $publication->id,
                'title' => $publication->title,
                'summary' => $publication->summary,
                'approximate_zone' => $publication->approximate_zone,
            ])
            ->values()
            ->all();

        return Inertia::render('dashboard', [
            'ads' => $ads,
            'wallet_balance' => $this->walletBalanceFor($subject),
            'community_alerts' => $communityAlerts,
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
    private function eligibleAd(Campaign $campaign, CampaignVersion $version, ?AuthenticatedSubject $subject, ?PersonAdvertisingProfile $profile): ?array
    {
        $available = $this->budgetProjection->available($campaign);

        if ($available <= 0) {
            return null;
        }

        // Instruction explicite du fondateur, 2026-07-31 : correspondance
        // d'audience individuelle réelle, plus une simple estimation
        // agrégée — une campagne ciblée (critères non vides) que le profil
        // de cette personne ne satisfait pas n'apparaît jamais dans son
        // Feed. Une campagne sans segment persisté (jamais censé arriver,
        // `CampaignController::store()` en crée toujours un — voir
        // `AudienceSegmentGuard::createSegment()`) est traitée comme non
        // ciblée plutôt que de faire échouer tout le Feed.
        $segment = $version->audienceSegment;
        $criteria = $segment !== null ? $segment->criteria : [];
        if (! $this->audienceSegmentGuard->matchesPerson($criteria, $profile)) {
            return null;
        }

        try {
            // Coût réellement réservé sur le budget campagne à la
            // soumission de l'événement (`CampaignBudgetService::
            // submitQualifiedEvent()`) — jamais ce que l'utilisateur
            // reçoit lui-même, voir ci-dessous.
            $basePrice = $this->pricingResolver->resolveBasePrice($version);
        } catch (PricingConfigurationNotResolvableException) {
            return null;
        }

        if ($basePrice > $available) {
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
            // Gain réel de cette personne précise (instruction explicite du
            // fondateur, 2026-07-31) : tient compte de son type économique
            // et de son quota mensuel déjà consommé
            // (`CampaignBudgetService::previewUserShareForPerson()`),
            // jamais le plafond générique 50/50 qui ignorerait les deux
            // (docs/03 §10 « le montant affiché avant la participation doit
            // être le montant effectivement crédité »). Un sujet non
            // résolvable retombe sur le plafond générique — la seule
            // information disponible sans identité confirmée.
            'reward_amount' => $subject !== null
                ? $this->campaignBudgetService->previewUserShareForPerson($basePrice, $subject->personAccountLink->person_id, $subject->personAccountLink->id)
                : $this->campaignBudgetService->userShareOfAmount($basePrice),
            'currency' => $campaign->currency,
        ];
    }
}
