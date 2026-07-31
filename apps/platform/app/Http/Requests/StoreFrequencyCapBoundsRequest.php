<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une nouvelle borne de revisionnage gratuit (instruction
 * explicite du fondateur, 2026-07-31). Ne décide jamais d'une
 * autorisation — `AuthorizationGate` reste l'unique décideur.
 */
class StoreFrequencyCapBoundsRequest extends FormRequest
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
            'daily_free_view_limit' => ['required', 'integer', 'min:1'],
            'lifetime_free_view_limit' => ['required', 'integer', 'gte:daily_free_view_limit'],
        ];
    }
}
