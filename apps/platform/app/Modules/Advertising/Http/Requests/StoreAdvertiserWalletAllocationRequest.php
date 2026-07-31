<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une allocation du solde Wallet annonceur vers une campagne
 * (instruction explicite du fondateur, 2026-07-31). Ne décide jamais d'une
 * autorisation ni de la propriété réelle de la campagne visée —
 * `AuthorizationGate` (portée `self`, `AdvertiserWalletAllocationController`)
 * reste l'unique décideur ; ce formulaire ne vérifie que la forme.
 */
class StoreAdvertiserWalletAllocationRequest extends FormRequest
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
            'campaign_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
