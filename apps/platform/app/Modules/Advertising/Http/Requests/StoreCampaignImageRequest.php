<?php

namespace App\Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'un upload d'image publicitaire (Lot 6). `max:10240` (10
 * Mo) est une borne technique de sécurité disque, pas une règle
 * commerciale — l'orientation (portrait/carré requis) est vérifiée après
 * stockage sur le contenu réel décodé, jamais ici (voir
 * `CampaignImageUploadService`). Ne décide jamais d'une autorisation —
 * `AuthorizationGate` reste l'unique décideur.
 */
class StoreCampaignImageRequest extends FormRequest
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
            'image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ];
    }
}
