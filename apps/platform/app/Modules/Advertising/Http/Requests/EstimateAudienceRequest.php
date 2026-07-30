<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'un aperçu de taille d'audience avant création de
 * campagne (Lot 3, véto du dirigeant). Ne décide jamais d'une
 * autorisation — `AuthorizationGate` reste l'unique décideur, même forme
 * que {@see StoreCampaignRequest}.
 */
class EstimateAudienceRequest extends FormRequest
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
            'advertiser_profile_id' => [
                'required', 'uuid',
                Rule::exists('advertiser_profiles', 'id')->where('status', 'active'),
            ],
            'criteria' => ['required', 'array'],
        ];
    }
}
