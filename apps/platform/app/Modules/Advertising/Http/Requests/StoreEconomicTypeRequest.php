<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une publication de version pour `economic_types`
 * (instruction explicite du fondateur, 2026-07-31) — mirroir de
 * `StoreSectorClassificationRequest`. `stable_key` reste interne, jamais
 * affiché (docs/02 §3) ; `name` est le seul nom public.
 */
class StoreEconomicTypeRequest extends FormRequest
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
            'stable_key' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'user_share_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'monthly_quota' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
