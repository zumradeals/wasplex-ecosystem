<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Advertising\Services\Exceptions\UnknownInterestCodeException;

/**
 * Collecte consentie du profil publicitaire (véto du dirigeant, 2026-07-30 ;
 * AMD-0009). Seul point d'écriture de
 * {@see PersonAdvertisingProfile} — aucune mutation directe ailleurs.
 *
 * Ne calcule ni n'expose aucun ciblage : ce service persiste un
 * consentement et ses valeurs, jamais une audience ni une estimation
 * (hors périmètre de ce lot).
 */
class AdvertisingProfileService
{
    /**
     * Bumpée manuellement si le texte de finalité présenté à la personne
     * change matériellement (AMD-0009 §4 : « Un consentement général ne
     * remplace pas les choix séparés » — un consentement donné sous un
     * texte antérieur reste tracé sous sa propre version). Passée à 2
     * (Lot 3, véto du dirigeant) : ajout du pays, préalable au calcul réel
     * d'audience.
     */
    private const CURRENT_CONSENT_VERSION = 2;

    /**
     * @param  array<int, string>  $interests
     *
     * @throws UnknownInterestCodeException
     */
    public function grantConsentAndUpdate(
        string $personId,
        ?string $countryCode,
        ?string $city,
        ?string $neighborhood,
        ?string $ageBracket,
        ?string $gender,
        array $interests,
    ): PersonAdvertisingProfile {
        $this->assertInterestsAreActive($interests);

        $profile = PersonAdvertisingProfile::query()->firstOrNew(['person_id' => $personId]);

        $profile->forceFill([
            'country_code' => $countryCode,
            'city' => $city,
            'neighborhood' => $neighborhood,
            'age_bracket' => $ageBracket,
            'gender' => $gender,
            'interests' => array_values($interests),
            'consent_version' => self::CURRENT_CONSENT_VERSION,
            'consent_given_at' => now(),
            'consent_withdrawn_at' => null,
        ])->save();

        return $profile;
    }

    /**
     * Retrait du consentement (AMD-0009 §5) : efface les valeurs déjà
     * saisies plutôt que de les garder inertes — minimisation réelle
     * (AMD-0009 §2), pas un simple drapeau qui laisserait la donnée en
     * base sans usage déclaré.
     */
    public function withdrawConsent(string $personId): ?PersonAdvertisingProfile
    {
        $profile = PersonAdvertisingProfile::query()->where('person_id', $personId)->first();

        if ($profile === null) {
            return null;
        }

        $profile->forceFill([
            'country_code' => null,
            'city' => null,
            'neighborhood' => null,
            'age_bracket' => null,
            'gender' => null,
            'interests' => [],
            'consent_withdrawn_at' => now(),
        ])->save();

        return $profile;
    }

    /**
     * @param  array<int, string>  $interests
     *
     * @throws UnknownInterestCodeException
     */
    private function assertInterestsAreActive(array $interests): void
    {
        if ($interests === []) {
            return;
        }

        $activeCodes = InterestTaxonomyEntry::query()
            ->where('state', 'active')
            ->whereIn('code', $interests)
            ->pluck('code')
            ->all();

        $unknown = array_diff($interests, $activeCodes);

        if ($unknown !== []) {
            throw new UnknownInterestCodeException(
                'code(s) de centre d\'intérêt inconnu(s) ou inactif(s) : '.implode(', ', $unknown)
            );
        }
    }
}
