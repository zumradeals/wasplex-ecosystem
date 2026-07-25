<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une auto-soumission de preuve d'attention qualifiée (W2,
 * `event.self_submit`). Ne décide jamais d'une autorisation — voir
 * {@see AuthorizationGate}.
 *
 * Volontairement dépourvue de `applied_price_amount` (contrairement à
 * {@see StoreQualifiedEventRequest}, réservée au personnel Wasplex) : le
 * prix est toujours résolu serveur via
 * {@see QualifiedEventPricingResolver},
 * jamais transmis par l'appelant — fermerait sinon exactement la faille
 * documentée sur `event.submit` (« déclarer lui-même son propre montant »,
 * migration `2026_07_25_100009`). Également dépourvue de
 * `beneficiary_person_account_link_id` : le bénéficiaire est toujours le
 * sujet authentifié lui-même (portée `self`), jamais une prétention du
 * corps de requête.
 */
class StoreSelfQualifiedEventRequest extends FormRequest
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
            'format' => ['required', 'string', 'max:255'],
            'evidence' => ['required', 'array', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
