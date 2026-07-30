<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'un upload de vidéo publicitaire (Lot 4). `max:102400` (100
 * Mo) est une borne technique de sécurité disque, pas une règle commerciale
 * — distincte de la durée (30-60s), elle-même admin-réglable
 * (voir `VideoAdDurationBounds`, jamais ici). Ne décide jamais d'une
 * autorisation — `AuthorizationGate` reste l'unique décideur.
 */
class StoreCampaignVideoRequest extends FormRequest
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
            'video' => ['required', 'file', 'mimetypes:video/mp4', 'max:102400'],
        ];
    }
}
