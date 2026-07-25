<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forme validée d'un refus de QualifiedEvent (P005-F, ADR-0010 §4 ligne 5 ;
 * ADR-0003 §11 : toute contre-écriture porte un motif). Ne décide jamais
 * d'une autorisation — {@see AuthorizationGate} reste l'unique décideur.
 */
class StoreQualifiedEventRejectionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:1'],
        ];
    }
}
