<?php

namespace App\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CaseNature;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Enums\VerificationLevel;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Support\RecordsCaseEvents;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un dossier (ecosystem/alertes/02 §2 ; UX-0001 §20, §22).
 * Un dossier `community` naît `draft` puis se soumet explicitement. Un
 * dossier `sos` naît directement `created` — sans brouillon, l'urgence ne
 * se répète pas (AMD-0007 §2, Constitution article 14.2).
 */
class CaseSubmissionService
{
    use RecordsCaseEvents;

    /**
     * @param  array<string, mixed>|null  $exactLocation
     */
    public function proposeCommunityCase(
        ?PersonAccountLink $author,
        CaseCategory $category,
        string $sourceDescription,
        string $countryCode,
        ?string $territoryCode,
        ?array $exactLocation,
        ?string $recallPhone,
        string $locale,
        string $correlationId,
    ): AlertCase {
        if ($category->nature() !== CaseNature::Community) {
            throw new InvalidCaseTransitionException("catégorie {$category->value} n'appartient pas à la nature community");
        }

        return DB::transaction(function () use (
            $author, $category, $sourceDescription, $countryCode, $territoryCode,
            $exactLocation, $recallPhone, $locale, $correlationId,
        ): AlertCase {
            $case = AlertCase::create([
                'author_person_account_link_id' => $author?->id,
                'nature' => CaseNature::Community,
                'category' => $category,
                'verification_level' => VerificationLevel::Unverified,
                'state' => CommunityCaseState::Draft->value,
                'country_code' => $countryCode,
                'territory_code' => $territoryCode,
                'exact_location' => $exactLocation,
                'source_description' => $sourceDescription,
                'recall_phone' => $recallPhone,
                'locale' => $locale,
            ]);

            $this->recordCaseEvent($case, 'case_created', $correlationId, actor: $author);

            return $case;
        });
    }

    public function submitCommunityCase(AlertCase $case, PersonAccountLink $author, string $correlationId): AlertCase
    {
        $current = $case->communityState();

        if ($current === null || ! in_array(CommunityCaseState::Submitted, $current->allowedNextStates(), true)) {
            throw new InvalidCaseTransitionException("transition submitted refusée depuis l'état {$case->state}");
        }

        return $this->transitionCase(
            $case,
            CommunityCaseState::Submitted->value,
            'case_submitted',
            $correlationId,
            actor: $author,
        );
    }

    /**
     * SOS : peut être créé sans authentification (`$author` nullable),
     * toujours `unverified`, jamais de brouillon.
     *
     * @param  array<string, mixed>|null  $exactLocation
     */
    public function reportSos(
        ?PersonAccountLink $author,
        CaseCategory $category,
        string $sourceDescription,
        string $countryCode,
        ?string $territoryCode,
        ?array $exactLocation,
        ?string $recallPhone,
        string $locale,
        ?string $idempotencyKey,
        string $correlationId,
    ): AlertCase {
        if ($category->nature() !== CaseNature::Sos) {
            throw new InvalidCaseTransitionException("catégorie {$category->value} n'appartient pas à la nature sos");
        }

        return DB::transaction(function () use (
            $author, $category, $sourceDescription, $countryCode, $territoryCode,
            $exactLocation, $recallPhone, $locale, $idempotencyKey, $correlationId,
        ): AlertCase {
            if ($idempotencyKey !== null) {
                $existing = AlertCase::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $case = AlertCase::create([
                'author_person_account_link_id' => $author?->id,
                'nature' => CaseNature::Sos,
                'category' => $category,
                'verification_level' => VerificationLevel::Unverified,
                'state' => SosCaseState::Created->value,
                'country_code' => $countryCode,
                'territory_code' => $territoryCode,
                'exact_location' => $exactLocation,
                'source_description' => $sourceDescription,
                'recall_phone' => $recallPhone,
                'locale' => $locale,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->recordCaseEvent($case, 'sos_created', $correlationId, actor: $author, channel: 'app');

            return $case;
        });
    }
}
