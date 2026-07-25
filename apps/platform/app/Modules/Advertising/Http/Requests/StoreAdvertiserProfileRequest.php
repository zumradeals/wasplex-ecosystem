<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'une déclaration de dossier annonceur par soi-même
 * (P007). Valide uniquement la forme ; ne décide jamais d'une
 * autorisation — {@see AuthorizationGate} reste l'unique décideur.
 *
 * Aucun champ métier n'a de valeur par défaut devinée : une donnée absente
 * est toujours un rejet (422), jamais une valeur substituée (même
 * discipline que `StoreCampaignRequest`).
 */
class StoreAdvertiserProfileRequest extends FormRequest
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
            'legal_name' => ['required', 'string', 'max:255'],
            'legal_identifier' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],

            'territories' => ['required', 'array', 'min:1'],
            'territories.*' => ['string', 'regex:/^[A-Z]{2}$/'],
        ];
    }
}
