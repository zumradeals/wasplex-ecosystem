<?php

namespace App\Modules\Advertising\Http\Controllers\Concerns;

use App\Modules\Advertising\Http\Controllers\AdvertisingOverviewController;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubject;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prologue partagé par tous les écrans du portail annonceur (P007-W2) :
 * résoudre le sujet, autoriser `campaign.view` (portée `self` — parcourir
 * ses propres campagnes n'exige aucune capacité supplémentaire par écran,
 * même invariant que {@see AdvertisingOverviewController}
 * avant extraction), puis retrouver le dossier annonceur du représentant.
 *
 * Chaque contrôleur hôte doit déclarer par constructeur
 * `$subjectResolver`, `$authorizationRequestFactory`, `$authorizationGate`
 * (mêmes types que les contrôleurs Advertising existants) : ce trait ne
 * fait que composer ces briques déjà vérifiées, jamais une nouvelle
 * décision d'autorisation (CLAUDE.md §6).
 */
trait ResolvesAdvertiserWorkspace
{
    /**
     * @param  array<string, mixed>  $emptyProps  Props spécifiques à l'écran
     *                                            appelant à fusionner dans l'état « accès refusé » / « aucun dossier »
     *                                            (ex. `['campaigns' => []]`) — chaque écran garde son propre contrat
     *                                            de props (UX-0002 §5) même dans ces états.
     * @return array{subject: AuthenticatedSubject, profile: AdvertiserProfile}|Response
     */
    private function resolveAdvertiserWorkspace(Request $request, string $component, array $emptyProps = []): array|Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render($component, [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'advertiserProfile' => null,
                ...$emptyProps,
            ]);
        }

        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
                territoryCodes: [],
                environment: $this->currentAdvertisingEnvironment(),
            ),
            environment: $this->currentAdvertisingEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return Inertia::render($component, [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'advertiserProfile' => null,
                ...$emptyProps,
            ]);
        }

        $profile = AdvertiserProfile::query()
            ->where('representative_person_account_link_id', $subject->personAccountLink->id)
            ->first();

        if ($profile === null) {
            return Inertia::render($component, [
                'access' => ['allowed' => true, 'reason' => null],
                'advertiserProfile' => null,
                ...$emptyProps,
            ]);
        }

        return ['subject' => $subject, 'profile' => $profile];
    }

    /**
     * @return array{id: string, legal_name: string, status: string, country_code: string, territories: array<int, mixed>}
     */
    private function advertiserProfilePayload(AdvertiserProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'legal_name' => $profile->legal_name,
            'status' => $profile->status->value,
            'country_code' => $profile->country_code,
            'territories' => $profile->territories,
        ];
    }

    private function currentAdvertisingEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
