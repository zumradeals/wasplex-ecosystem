<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une nouvelle borne de durée vidéo (Lot 4). Ne décide
 * jamais d'une autorisation — `AuthorizationGate` reste l'unique décideur.
 */
class StoreVideoAdDurationBoundsRequest extends FormRequest
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
            'min_seconds' => ['required', 'integer', 'min:1'],
            'max_seconds' => ['required', 'integer', 'gte:min_seconds'],
        ];
    }
}
