<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'un signalement de campagne (P005-D, `03-...md` §1).
 *
 * Valide uniquement la forme ; ne décide jamais d'une autorisation —
 * {@see AuthorizationGate} reste l'unique décideur (P003-B2 §C, même
 * discipline que `StoreCampaignRequest`, P005-B).
 *
 * `severity` : ensemble fermé documenté ici plutôt que deviné à la volée —
 * aucune source normative ne fixe ce vocabulaire, mais
 * `AdvertisingTestCase`/`CampaignSuspensionTest` (P005-A) emploient déjà
 * `low`/`medium`/`high` au niveau service ; ce lot reprend le même
 * vocabulaire au niveau HTTP plutôt que d'en inventer un distinct.
 */
class StoreModerationReportRequest extends FormRequest
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
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'observed_destination' => ['nullable', 'string'],
            'campaign_version_id' => [
                'nullable', 'uuid',
                Rule::exists('campaign_versions', 'id')->where('campaign_id', $this->route()?->originalParameter('campaign')),
            ],
        ];
    }
}
