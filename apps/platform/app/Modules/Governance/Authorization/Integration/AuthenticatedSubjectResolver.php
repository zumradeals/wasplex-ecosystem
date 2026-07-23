<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Models\User;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;

/**
 * Résout un {@see AuthenticatedSubject} entièrement vérifié côté serveur
 * (P003-B2 §A).
 *
 * Aucun `user_id`, `person_id` ou `organization_id` transmis par le client
 * n'est jamais considéré comme fiable : un identifiant d'appartenance
 * éventuellement fourni n'est qu'une revendication, revérifiée ici contre
 * les données persistées avant de devenir une composante du sujet. Absence,
 * contradiction ou inactivité produisent toujours un refus fermé — jamais
 * une identité système universelle ni un concept d'« Agent » ou de rôle
 * global (CLAUDE.md §2).
 */
final class AuthenticatedSubjectResolver
{
    /**
     * @throws SubjectResolutionFailedException
     */
    public function resolve(?User $account, SessionAssurance $sessionAssurance, ?string $claimedMembershipId = null): AuthenticatedSubject
    {
        if ($account === null) {
            throw new SubjectResolutionFailedException('unauthenticated', 'Aucun compte authentifié.');
        }

        $link = PersonAccountLink::query()
            ->where('user_id', $account->id)
            ->where('status', LinkStatus::Active->value)
            ->first();

        if ($link === null) {
            throw new SubjectResolutionFailedException('no_active_link', "Ce compte n'a pas de liaison personne-compte active.");
        }

        $assuranceState = AssuranceState::query()->where('user_id', $account->id)->first();

        if ($assuranceState === null) {
            throw new SubjectResolutionFailedException('no_assurance_state', "Aucun état d'assurance n'est enregistré pour ce compte.");
        }

        $membership = null;

        if ($claimedMembershipId !== null) {
            $membership = Membership::query()->with('organization')->find($claimedMembershipId);

            if ($membership === null
                || $membership->status !== MembershipStatus::Active
                || $membership->person_account_link_id !== $link->id) {
                throw new SubjectResolutionFailedException(
                    'membership_not_active',
                    "L'appartenance revendiquée n'est pas active ou ne correspond pas à ce compte."
                );
            }

            $organization = $membership->organization;

            if ($organization === null || $organization->state !== OrganizationState::Active) {
                throw new SubjectResolutionFailedException('organization_not_active', "L'organisation de cette appartenance n'est pas active.");
            }
        }

        return new AuthenticatedSubject(
            account: $account,
            personAccountLink: $link,
            assurance: $assuranceState->toContext($sessionAssurance),
            membership: $membership,
        );
    }
}
