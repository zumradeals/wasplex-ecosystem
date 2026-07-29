<?php

namespace App\Modules\Alerts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une correspondance proposée (ecosystem/alertes/02 §7).
 */
class StoreCorrespondenceReportRequest extends FormRequest
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
            'non_public_description' => ['required', 'string', 'max:2000'],
            'verification_response' => ['required', 'array', 'min:1'],
        ];
    }
}
