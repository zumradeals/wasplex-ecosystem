<?php

namespace App\Http\Requests;

use App\Modules\Advertising\Models\SectorClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'une publication de version pour `sector_classifications`
 * (Lot 9). `sector_class` exclut délibérément `forbidden` : un écran
 * admin n'est jamais le mécanisme pour déclarer une interdiction
 * constitutionnelle (ce serait un amendement) — voir
 * {@see SectorClassification}.
 */
class StoreSectorClassificationRequest extends FormRequest
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
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'sector' => ['required', 'string', 'max:255'],
            'sector_class' => ['required', Rule::in([
                'enhanced_authorization', 'standard_authorization', 'institutional_information',
            ])],
            'minimum_age' => ['nullable', 'integer', 'min:0'],
            'required_evidence' => ['array'],
            'required_evidence.*' => ['string'],
            'warnings' => ['array'],
            'warnings.*' => ['string'],
            'allowed_formats' => ['array'],
            'allowed_formats.*' => ['string'],
            'allowed_targeting' => ['array'],
            'allowed_targeting.*' => ['string'],
            'review_level' => ['required', Rule::in(['standard', 'enhanced'])],
            'minimum_approvals' => ['required', 'integer', 'min:1'],
        ];
    }
}
