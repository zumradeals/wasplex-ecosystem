<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une initiation de dépôt Wallet annonceur en libre-service
 * (instruction explicite du fondateur, 2026-07-31 ; mirroir exact de
 * {@see StoreCampaignFundingInitiationRequest}). Ne décide jamais d'une
 * autorisation — `AuthorizationGate` reste l'unique décideur.
 * `idempotency_key` n'est pas vérifiée unique ici :
 * `AdvertiserWalletDepositInitiationService` gère déjà le rejeu idempotent.
 */
class StoreAdvertiserWalletDepositInitiationRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:200'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
