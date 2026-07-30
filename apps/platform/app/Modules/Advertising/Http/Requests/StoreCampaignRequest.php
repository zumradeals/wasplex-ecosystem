<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Forme validée d'une soumission de campagne en draft (P005-B).
 *
 * Valide uniquement la forme et l'existence des références citées ; ne
 * décide jamais d'une autorisation — {@see AuthorizationGate}
 * reste l'unique décideur (P003-B2 §C). En particulier, la présence d'un
 * `advertiser_profile_id` référençant un dossier existant ne prouve jamais
 * que l'appelant en est le représentant : cette vérification appartient au
 * moteur d'autorisation, via la portée du grant comparée au représentant
 * réellement persisté (jamais à une donnée transmise par ce formulaire).
 *
 * Aucun champ métier n'a de valeur par défaut devinée : une donnée absente
 * est toujours un rejet (422), jamais une valeur substituée.
 */
class StoreCampaignRequest extends FormRequest
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
        // Noms de table non qualifiés par schéma : le connecteur pgsql
        // configuré (config/database.php) place déjà `advertising` dans
        // `search_path`. `Rule::exists('schema.table', ...)`/`unique:schema.table,...`
        // interprètent le segment avant le point comme un nom de connexion,
        // jamais un schéma Postgres — piège vérifié empiriquement ici,
        // jamais deviné.
        return [
            'advertiser_profile_id' => [
                'required', 'uuid',
                Rule::exists('advertiser_profiles', 'id')->where('status', 'active'),
            ],
            'code' => ['required', 'string', 'max:255', 'unique:campaigns,code'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],

            'sector_classification_id' => [
                'required', 'uuid',
                Rule::exists('sector_classifications', 'id')->where('state', 'active'),
            ],

            'creations' => ['required', 'array', 'min:1'],
            'expected_event' => ['required', 'array', 'min:1'],
            'destination' => ['required', 'array', 'min:1'],

            'territory' => ['required', 'array', 'min:1'],
            'territory.*' => ['string', 'regex:/^[A-Z]{2}$/'],

            'pricing_configuration_key' => ['required', 'string'],
            'pricing_configuration_version' => ['required', 'integer', 'min:1'],

            // Lot 3 (véto du dirigeant) : la taille d'audience n'est plus
            // saisie par l'annonceur — `CampaignController::store()` la
            // calcule côté serveur depuis `audience.criteria`
            // (`AudienceSegmentGuard::computeSize()`), jamais une valeur
            // déclarée.
            'audience' => ['required', 'array'],
            'audience.criteria' => ['required', 'array'],
        ];
    }
}
