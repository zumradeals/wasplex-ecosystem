<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une initiation d'achat d'abonnement (instruction
 * explicite du fondateur, 2026-07-31 ; mirroir de
 * {@see StoreAdvertiserWalletDepositInitiationRequest}).
 */
class StoreSubscriptionPurchaseInitiationRequest extends FormRequest
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
            'subscription_plan_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
