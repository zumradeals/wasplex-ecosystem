<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'un enregistrement de financement de campagne (P005-E,
 * ADR-0010 §4 ; ADR-0003 §7-8). Ne décide jamais d'une autorisation —
 * {@see AuthorizationGate} reste l'unique décideur.
 *
 * `funding_reference` n'est PAS vérifiée unique ici : {@see LedgerPoster}
 * gère déjà le rejeu idempotent (même référence, même contenu → transaction
 * existante renvoyée ; même référence, contenu différent → conflit) —
 * dupliquer cette garantie côté validation HTTP inventerait un second
 * mécanisme pour la même invariante (ADR-0003 §10).
 */
class StoreCampaignFundingRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'funding_reference' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
