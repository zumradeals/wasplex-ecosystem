<?php

namespace App\Modules\Advertising\Http\Requests;

use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use Illuminate\Contracts\Validation\Validator;
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

    /**
     * Cohérence média/format/condition (chantier « espace annonceur
     * cohérent avec le modèle économique », véto du dirigeant) : ferme
     * l'incohérence observée en capture (média vidéo d'environ 29
     * secondes, format « Affichage », condition « vue complète ») —
     * jusqu'ici possible car `expected_event`/`creations` n'étaient
     * validés que comme des tableaux non vides, sans lien entre eux.
     *
     * Règles, volontairement minimales (aucune ne décide un prix, un
     * pourcentage ou un quota — CLAUDE.md §2) :
     * - une création vidéo (`creations.video_path`) impose
     *   `expected_event.format = 'video'` ;
     * - une création image (`creations.image_path`) interdit
     *   `expected_event.format = 'video'` ;
     * - le format déclaré doit figurer parmi les formats autorisés par le
     *   secteur choisi, quand ce secteur en restreint la liste ;
     * - `expected_event.condition` reste `'completion'`, seule condition
     *   dont le moteur (`CampaignBudgetService`) sait aujourd'hui tenir
     *   compte (voir le texte déjà affiché à l'annonceur dans
     *   `campaign-create.tsx`).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                // Les règles de forme de base n'ont pas passé : une
                // vérification croisée sur des champs potentiellement
                // absents produirait un message trompeur.
                return;
            }

            $creations = (array) $this->input('creations', []);
            $expectedEvent = (array) $this->input('expected_event', []);
            $format = $expectedEvent['format'] ?? null;
            $condition = $expectedEvent['condition'] ?? null;
            $hasVideo = ! empty($creations['video_path']);
            $hasImage = ! empty($creations['image_path']);

            if ($hasVideo && $format !== 'video') {
                $validator->errors()->add(
                    'expected_event.format',
                    "une création vidéo exige expected_event.format = 'video' (média et format déclarés incohérents)."
                );
            }

            if ($hasImage && $format === 'video') {
                $validator->errors()->add(
                    'expected_event.format',
                    "une création image ne peut pas déclarer expected_event.format = 'video' (média et format déclarés incohérents)."
                );
            }

            if ($condition !== null && $condition !== 'completion') {
                $validator->errors()->add(
                    'expected_event.condition',
                    "'completion' est la seule condition de crédit prise en charge aujourd'hui."
                );
            }

            if ($format !== null) {
                $sector = SectorClassification::query()
                    ->where('id', $this->input('sector_classification_id'))
                    ->first();
                $allowedFormats = $sector === null ? [] : $sector->allowed_formats;

                if ($allowedFormats !== [] && ! in_array($format, $allowedFormats, true)) {
                    $validator->errors()->add(
                        'expected_event.format',
                        "le format « {$format} » n'est pas autorisé par le secteur choisi."
                    );
                }
            }
        });
    }
}
