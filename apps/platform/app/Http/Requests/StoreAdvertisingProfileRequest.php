<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'un consentement de profil publicitaire (véto du
 * dirigeant, 2026-07-30 ; AMD-0009). Chaque champ est facultatif — aucun
 * n'est requis pour donner un consentement partiel (AMD-0009 §4 : « Un
 * consentement général ne remplace pas les choix séparés »).
 */
class StoreAdvertisingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'age_bracket' => ['nullable', 'string', 'in:18-24,25-34,35-44,45-54,55-64,65+'],
            'gender' => ['nullable', 'string', 'in:woman,man,other,prefer_not_to_say'],
            'interests' => ['present', 'array'],
            'interests.*' => ['string', 'max:100'],
        ];
    }
}
