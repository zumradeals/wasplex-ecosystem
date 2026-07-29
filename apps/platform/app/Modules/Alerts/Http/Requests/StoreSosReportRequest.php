<?php

namespace App\Modules\Alerts\Http\Requests;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CaseNature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Forme validée d'un SOS (ecosystem/alertes/02 §2, §22 ; AMD-0007 §2).
 * `authorize()` retourne toujours vrai : un SOS doit rester atteignable
 * sans authentification complète — la limite de fréquence et la
 * validation de forme sont les seules protections à ce stade.
 */
class StoreSosReportRequest extends FormRequest
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
                    array_filter(CaseCategory::cases(), fn (CaseCategory $c): bool => $c->nature() === CaseNature::Sos),
                )),
            ],
            'source_description' => ['required', 'string', 'max:1000'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'territory_code' => ['nullable', 'string', 'max:255'],
            'exact_location' => ['nullable', 'array'],
            'recall_phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['required', 'string', 'max:5'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
