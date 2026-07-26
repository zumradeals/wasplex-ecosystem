<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Enums\ModerationCaseStatus;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\ModerationCase;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord personnel Wasplex (staff) : les trois files qui ferment
 * la boucle de gain (P0, demande Koné 2026-07-26) — approbation de version
 * de campagne (`campaign.approve`), financement (`campaign.fund`),
 * acceptation/refus d'événement qualifié (`event.accept`/`event.reject`).
 * Chaque section reste indépendamment gouvernée par sa propre capacité
 * (ADR-0004) : aucun rôle « admin » générique n'existe ni n'est créé ici.
 *
 * `hasActiveGrant()` ne fait qu'une vérification de VISIBILITÉ de section
 * (« cette personne détient-elle un grant actif pour cette capacité, quelle
 * qu'en soit la portée ») — elle ne remplace jamais la vérification réelle,
 * déjà entièrement gouvernée par `AuthorizationGate`, effectuée par chacune
 * des routes d'action déjà existantes et testées
 * (CampaignVersionApprovalController, CampaignFundingController,
 * QualifiedEventAcceptanceController, QualifiedEventRejectionController).
 * Si une section s'affiche mais que l'action réelle échoue (portée du
 * grant plus étroite que ce que ce contrôleur suppose), l'utilisateur
 * reçoit le refus sûr habituel de ces routes — rien de silencieux ni
 * d'incorrect ne peut en résulter, seulement une action refusée.
 */
class ModerationOverviewController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            $denied = ['allowed' => false, 'reason' => 'subject_not_resolved'];

            return Inertia::render('admin/moderation', [
                'campaignApproval' => ['access' => $denied, 'items' => []],
                'campaignFunding' => ['access' => $denied, 'items' => []],
                'qualifiedEvents' => ['access' => $denied, 'items' => []],
            ]);
        }

        $link = $subject->personAccountLink;

        $canApprove = $this->hasActiveGrant($link, 'campaign.approve');
        $canFund = $this->hasActiveGrant($link, 'campaign.fund');
        $canDecideEvents = $this->hasActiveGrant($link, 'event.accept')
            || $this->hasActiveGrant($link, 'event.reject');
        $canModerate = $this->hasActiveGrant($link, 'campaign.moderate');

        return Inertia::render('admin/moderation', [
            'campaignApproval' => [
                'access' => $this->accessFor($canApprove),
                'items' => $canApprove ? $this->pendingApprovals() : [],
            ],
            'campaignFunding' => [
                'access' => $this->accessFor($canFund),
                'items' => $canFund ? $this->fundableCampaigns() : [],
            ],
            'qualifiedEvents' => [
                'access' => $this->accessFor($canDecideEvents),
                'items' => $canDecideEvents ? $this->pendingQualifiedEvents() : [],
            ],
            'moderationCases' => [
                'access' => $this->accessFor($canModerate),
                'items' => $canModerate ? $this->openModerationCases() : [],
            ],
        ]);
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    private function accessFor(bool $allowed): array
    {
        return ['allowed' => $allowed, 'reason' => $allowed ? null : 'no_active_grant'];
    }

    /**
     * Vérification de visibilité seule (voir docblock de la classe) : même
     * base de requête que les étapes 5-6 de
     * {@see AuthorizationEngine::evaluate()},
     * sans l'appariement de portée qui suit — celui-ci reste entièrement
     * porté par `AuthorizationGate` au moment de l'action réelle.
     */
    private function hasActiveGrant(PersonAccountLink $link, string $capabilityKey): bool
    {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $capabilityKey)
            ->where('state', 'active')
            ->first();

        if ($capability === null) {
            return false;
        }

        $now = Carbon::now();

        return Grant::query()
            ->where('capability_definition_id', $capability->id)
            ->where('person_account_link_id', $link->id)
            ->where('state', GrantState::Active->value)
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
            })
            ->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingApprovals(): array
    {
        return CampaignVersion::query()
            ->where('state', CampaignVersionState::InReview)
            ->with(['campaign.advertiserProfile'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (CampaignVersion $version): array => [
                'campaign_version_id' => $version->id,
                'campaign_id' => $version->campaign->id,
                'campaign_code' => $version->campaign->code,
                'advertiser_legal_name' => $version->campaign->advertiserProfile->legal_name,
                'headline' => $version->creations['headline'] ?? $version->campaign->code,
                'submitted_at' => $version->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fundableCampaigns(): array
    {
        return Campaign::query()
            ->where('state', '!=', 'closed')
            ->with('advertiserProfile')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'campaign_id' => $campaign->id,
                'code' => $campaign->code,
                'currency' => $campaign->currency,
                'advertiser_legal_name' => $campaign->advertiserProfile->legal_name,
                'available' => $this->budgetProjection->available($campaign),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingQualifiedEvents(): array
    {
        return QualifiedEvent::query()
            ->where('billing_status', BillingStatus::Pending)
            ->with(['campaign', 'campaignVersion'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (QualifiedEvent $event): array => [
                'qualified_event_id' => $event->id,
                'campaign_code' => $event->campaign->code,
                'headline' => $event->campaignVersion->creations['headline'] ?? $event->campaign->code,
                'format' => $event->format,
                'reward_amount' => $event->applied_price_amount,
                'currency' => $event->applied_price_currency,
                'submitted_at' => $event->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openModerationCases(): array
    {
        return ModerationCase::query()
            ->where('status', ModerationCaseStatus::Open)
            ->with(['campaign.advertiserProfile'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ModerationCase $case): array => [
                'moderation_case_id' => $case->id,
                'campaign_id' => $case->campaign->id,
                'campaign_code' => $case->campaign->code,
                'advertiser_legal_name' => $case->campaign->advertiserProfile->legal_name,
                'reason' => $case->reason,
                'severity' => $case->severity,
                'observed_destination' => $case->observed_destination,
                'opened_at' => $case->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
