<?php

namespace App\Modules\Alerts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseDecisionRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['start_review', 'publish', 'restrict', 'reject'])],
            'title' => ['required_if:decision,publish', 'nullable', 'string', 'max:200'],
            'summary' => ['required_if:decision,publish', 'nullable', 'string', 'max:2000'],
            'approximate_zone' => ['nullable', 'string', 'max:255'],
            'reason' => ['required_if:decision,restrict,reject', 'nullable', 'string', 'max:500'],
        ];
    }
}
