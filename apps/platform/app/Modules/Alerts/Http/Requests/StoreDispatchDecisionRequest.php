<?php

namespace App\Modules\Alerts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDispatchDecisionRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['acknowledge', 'accept', 'process', 'resolve', 'refuse'])],
            'reason' => ['required_if:decision,refuse', 'nullable', 'string', 'max:500'],
        ];
    }
}
