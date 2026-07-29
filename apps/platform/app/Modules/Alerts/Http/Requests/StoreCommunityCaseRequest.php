<?php

namespace App\Modules\Alerts\Http\Requests;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CaseNature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Forme validée d'une déclaration communautaire (UX-0001 §20). Valide
 * uniquement la forme ; `AuthorizationGate` reste l'unique décideur
 * (même discipline que `StoreCampaignRequest`).
 */
class StoreCommunityCaseRequest extends FormRequest
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
            'category' => [
                'required', 'string',
                new Enum(CaseCategory::class),
                Rule::in(array_map(
                    fn (CaseCategory $c): string => $c->value,
                    array_filter(CaseCategory::cases(), fn (CaseCategory $c): bool => $c->nature() === CaseNature::Community),
                )),
            ],
            'source_description' => ['required', 'string', 'max:2000'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'territory_code' => ['nullable', 'string', 'max:255'],
            'exact_location' => ['nullable', 'array'],
            'recall_phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['required', 'string', 'max:5'],
        ];
    }
}
