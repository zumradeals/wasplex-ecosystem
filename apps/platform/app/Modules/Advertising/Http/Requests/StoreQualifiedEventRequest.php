<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'une soumission de QualifiedEvent (P005-F, ADR-0010 §4
 * ligne 3 ; `01-cycle-creation-valeur.md` §3). Ne décide jamais d'une
 * autorisation — {@see AuthorizationGate} reste l'unique décideur.
 *
 * `applied_price_amount` est fourni tel quel par l'appelant — jamais
 * recalculé ici (même discipline que `CampaignBudgetService::submitQualifiedEvent()`,
 * P005-A) : `event.submit` est réservé au personnel qui a déjà vérifié ce
 * montant hors système (voir le raisonnement documenté sur la capacité,
 * migration `2026_07_25_100009`).
 */
class StoreQualifiedEventRequest extends FormRequest
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
            'beneficiary_person_account_link_id' => [
                'required', 'uuid',
                Rule::exists('person_account_links', 'id')->where('status', 'active'),
            ],
            'format' => ['required', 'string', 'max:255'],
            'evidence' => ['required', 'array', 'min:1'],
            'applied_price_amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:255'],
            'fraud_decision' => ['nullable', 'string', Rule::in(FraudDecision::values())],
            'pricing_configuration_key' => ['nullable', 'string'],
            'pricing_configuration_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
