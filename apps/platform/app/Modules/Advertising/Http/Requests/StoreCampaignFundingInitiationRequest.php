<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Services\CampaignFundingInitiationService;
use App\Modules\Wallet\Deposit\Http\Requests\StoreDepositRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une initiation de financement de campagne en libre-service
 * (véto du dirigeant, 2026-07-30 ; mirroir exact de
 * {@see StoreDepositRequest}). Ne
 * décide jamais d'une autorisation — `AuthorizationGate` reste l'unique
 * décideur. `idempotency_key` n'est pas vérifiée unique ici :
 * {@see CampaignFundingInitiationService}
 * gère déjà le rejeu idempotent.
 */
class StoreCampaignFundingInitiationRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
