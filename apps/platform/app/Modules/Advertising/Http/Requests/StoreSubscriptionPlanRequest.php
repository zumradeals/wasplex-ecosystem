<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une publication de version pour `subscription_plans`
 * (instruction explicite du fondateur, 2026-07-31) — mirroir de
 * {@see StoreEconomicTypeRequest}.
 */
class StoreSubscriptionPlanRequest extends FormRequest
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
            'price_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'economic_type_id' => ['required', 'uuid'],
        ];
    }
}
