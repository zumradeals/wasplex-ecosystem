<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Enums\PrecautionaryMeasure;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'une décision de modération (P005-D, `03-...md` §2 ;
 * ADR-0010 §3). Ne décide jamais d'une autorisation —
 * {@see AuthorizationGate} reste l'unique décideur.
 *
 * `decision` est toujours requis ({@see ModerationDecision}) : ce lot ne
 * construit aucun état intermédiaire type « en instruction » (P005-D §3.B).
 * `precautionary_measure` reste optionnel — son absence laisse la mesure
 * déjà posée sur le dossier inchangée (`None` par défaut à l'ouverture).
 */
class StoreModerationDecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::in(ModerationDecision::values())],
            'precautionary_measure' => ['nullable', 'string', Rule::in(PrecautionaryMeasure::values())],
        ];
    }
}
