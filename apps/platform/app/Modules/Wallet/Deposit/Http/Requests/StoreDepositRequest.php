<?php

namespace App\Modules\Wallet\Deposit\Http\Requests;

use App\Modules\Wallet\Deposit\Services\DepositInitiationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une initiation de dépôt (AMD-0017). Ne décide jamais
 * d'une autorisation — `AuthorizationGate` reste l'unique décideur.
 * `idempotency_key` n'est pas vérifiée unique ici :
 * {@see DepositInitiationService}
 * gère déjà le rejeu idempotent (même clé → dépôt déjà créé renvoyé tel
 * quel).
 */
class StoreDepositRequest extends FormRequest
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
