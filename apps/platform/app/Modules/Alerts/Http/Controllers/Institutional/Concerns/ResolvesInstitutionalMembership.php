<?php

namespace App\Modules\Alerts\Http\Controllers\Institutional\Concerns;

use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prologue partagé par le portail des institutions Wasplex
 * (ecosystem/institutions/01 §10) : résoudre le sujet, puis l'appartenance
 * active à une organisation `institution`. Une personne sans appartenance
 * active ne voit aucun dossier — jamais une organisation devinée.
 *
 * Même discipline que `App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility` :
 * une vérification de VISIBILITÉ par capacité, jamais l'autorisation
 * réelle de l'action, déjà entièrement portée par `AuthorizationGate` au
 * moment de chaque décision (`DispatchDecisionController`).
 */
trait ResolvesInstitutionalMembership
{
    /**
     * @return array{link: PersonAccountLink, organization: Organization}|Response
     */
    private function resolveInstitutionalSubject(Request $request, string $component, mixed $deniedProps): array|Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render($component, $deniedProps('subject_not_resolved'));
        }

        $link = $subject->personAccountLink;

        $membership = Membership::query()
            ->where('person_account_link_id', $link->id)
            ->where('status', MembershipStatus::Active)
            ->whereHas('organization', fn ($query) => $query->where('category', 'institution')->where('state', 'active'))
            ->with('organization')
            ->first();

        if ($membership === null) {
            return Inertia::render($component, $deniedProps('no_institutional_membership'));
        }

        return ['link' => $link, 'organization' => $membership->organization];
    }

    private function hasActiveInstitutionalGrant(PersonAccountLink $link, Organization $organization, string $capabilityKey): bool
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
            ->where('state', GrantState::Active->value)
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
            })
            ->whereRaw("scope_payload ->> 'organization_id' = ?", [$organization->id])
            ->whereHas('membership', fn ($query) => $query->where('person_account_link_id', $link->id))
            ->exists();
    }
}
