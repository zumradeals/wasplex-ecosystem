<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdvertisingProfileRequest;
use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Advertising\Services\AdvertisingProfileService;
use App\Modules\Advertising\Services\Exceptions\UnknownInterestCodeException;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Intérêts publicitaires », section de « Mon espace » distincte du profil
 * de base (véto du dirigeant, 2026-07-30 ; UX-0001 §11). Édition de son
 * propre consentement — même discipline que
 * {@see AccountOverviewController} : `auth` middleware seul, jamais
 * `AuthorizationGate` (ce n'est pas une capacité métier séparée, seulement
 * un réglage de son propre compte).
 */
class AdvertisingProfileController extends Controller
{
    public function __construct(
        private readonly AdvertisingProfileService $profileService,
    ) {}

    public function index(Request $request): Response
    {
        $personId = $this->personId($request);

        $profile = PersonAdvertisingProfile::query()->where('person_id', $personId)->first();

        return Inertia::render('account/advertising-profile', [
            'interestTaxonomy' => InterestTaxonomyEntry::query()
                ->where('state', 'active')
                ->orderBy('label')
                ->get(['code', 'label'])
                ->toArray(),
            'profile' => $this->presentProfile($profile),
        ]);
    }

    public function update(StoreAdvertisingProfileRequest $request): JsonResponse
    {
        $personId = $this->personId($request);

        try {
            $profile = $this->profileService->grantConsentAndUpdate(
                personId: $personId,
                ageBracket: $request->validated('age_bracket'),
                gender: $request->validated('gender'),
                interests: $request->validated('interests'),
            );
        } catch (UnknownInterestCodeException $exception) {
            return response()->json([
                'reason' => 'unknown_interest_code',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($this->presentProfile($profile));
    }

    public function destroy(Request $request): JsonResponse
    {
        $profile = $this->profileService->withdrawConsent($this->personId($request));

        return response()->json(['profile' => $this->presentProfile($profile)]);
    }

    private function personId(Request $request): string
    {
        return PersonAccountLink::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail()
            ->person_id;
    }

    /**
     * @return array{consented: bool, age_bracket: string|null, gender: string|null, interests: array<int, string>, consent_given_at: string|null}|null
     */
    private function presentProfile(?PersonAdvertisingProfile $profile): ?array
    {
        if ($profile === null || $profile->consent_withdrawn_at !== null) {
            return null;
        }

        return [
            'consented' => true,
            'age_bracket' => $profile->age_bracket,
            'gender' => $profile->gender,
            'interests' => $profile->interests,
            'consent_given_at' => $profile->consent_given_at?->toIso8601String(),
        ];
    }
}
