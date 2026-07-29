<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CaseNature;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Enums\VerificationLevel;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dossier source, confidentiel (ecosystem/alertes/02 §1 ; mission P008-A
 * §7.2 — nommé `AlertCase` plutôt que `Case`, mot réservé du langage PHP).
 * `state` n'est volontairement pas casté vers un seul enum PHP : sa
 * signification dépend de `nature` (voir {@see stateEnumClass()}) — le
 * déclencheur PostgreSQL `alerts.enforce_case_state_machine` reste la
 * source de vérité sur les transitions permises, jamais ce modèle seul.
 *
 * @property string $id
 * @property string|null $author_person_account_link_id
 * @property CaseNature $nature
 * @property CaseCategory $category
 * @property VerificationLevel $verification_level
 * @property string $state
 * @property string $country_code
 * @property string|null $territory_code
 * @property array<string, mixed>|null $exact_location
 * @property string $source_description
 * @property string|null $recall_phone
 * @property string $locale
 * @property string|null $policy_reference
 * @property string|null $idempotency_key
 * @property Carbon|CarbonImmutable|null $expires_at
 * @property Carbon|CarbonImmutable|null $closed_at
 * @property string|null $closure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AlertCase extends Model
{
    protected $table = 'alerts.cases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'author_person_account_link_id', 'nature', 'category', 'verification_level', 'state',
        'country_code', 'territory_code', 'exact_location', 'source_description', 'recall_phone',
        'locale', 'policy_reference', 'idempotency_key', 'expires_at', 'closed_at', 'closure_reason',
    ];

    protected function casts(): array
    {
        return [
            'nature' => CaseNature::class,
            'category' => CaseCategory::class,
            'verification_level' => VerificationLevel::class,
            'exact_location' => 'array',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->id ??= (string) Str::uuid7();
        });
    }

    /**
     * Résout la classe d'énumération pertinente pour `state` selon
     * `nature` — jamais l'inverse (`nature` ne se déduit pas de `state`).
     *
     * @return class-string<CommunityCaseState>|class-string<SosCaseState>
     */
    public function stateEnumClass(): string
    {
        return match ($this->nature) {
            CaseNature::Community => CommunityCaseState::class,
            CaseNature::Sos => SosCaseState::class,
        };
    }

    public function communityState(): ?CommunityCaseState
    {
        return $this->nature === CaseNature::Community ? CommunityCaseState::from($this->state) : null;
    }

    public function sosState(): ?SosCaseState
    {
        return $this->nature === CaseNature::Sos ? SosCaseState::from($this->state) : null;
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'author_person_account_link_id');
    }

    /**
     * @return HasMany<CaseEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id');
    }

    /**
     * @return HasMany<Publication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'case_id');
    }

    /**
     * @return HasMany<InstitutionDispatch, $this>
     */
    public function dispatches(): HasMany
    {
        return $this->hasMany(InstitutionDispatch::class, 'case_id');
    }

    /**
     * @return HasMany<CorrespondenceReport, $this>
     */
    public function correspondenceReports(): HasMany
    {
        return $this->hasMany(CorrespondenceReport::class, 'case_id');
    }

    /**
     * @return HasMany<Restitution, $this>
     */
    public function restitutions(): HasMany
    {
        return $this->hasMany(Restitution::class, 'case_id');
    }
}
