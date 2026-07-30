<?php

namespace App\Http\Requests;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée de l'ajout d'une entrée de la référence des centres
 * d'intérêt (véto du dirigeant, 2026-07-30). Ne décide jamais d'une
 * autorisation — `AuthorizationGate` reste l'unique décideur.
 */
class StoreInterestTaxonomyEntryRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique(InterestTaxonomyEntry::class, 'code'),
            ],
            'label' => ['required', 'string', 'max:255'],
        ];
    }
}
