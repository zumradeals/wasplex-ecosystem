<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Grant nominatif (ADR-0004 §5, §22). Un grant n'est jamais physiquement
 * supprimé et un grant révoqué ou expiré ne redevient jamais actif par
 * simple mise à jour — ces deux garanties sont d'abord imposées par
 * {@see GrantManager}, et
 * appliquées en défense en profondeur par des déclencheurs PostgreSQL.
 *
 * @property string $id
 * @property string|null $person_account_link_id
 * @property string|null $membership_id
 * @property string $capability_definition_id
 * @property string|null $purpose_definition_id
 * @property string $policy_version_id
 * @property string|null $role_template_id
 * @property int $scope_schema_version
 * @property array<string, mixed> $scope_payload
 * @property int $conditions_schema_version
 * @property array<string, mixed> $conditions_payload
 * @property GrantEffect $effect
 * @property GrantState $state
 * @property GrantSource $source
 * @property string|null $source_reference
 * @property Carbon|CarbonImmutable $valid_from
 * @property Carbon|CarbonImmutable|null $valid_until
 * @property string $author_person_account_link_id
 * @property string|null $approver_person_account_link_id
 * @property Carbon|CarbonImmutable|null $activated_at
 * @property Carbon|CarbonImmutable|null $revoked_at
 * @property string|null $revocation_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Grant extends Model
{
    protected $table = 'governance.grants';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'person_account_link_id', 'membership_id', 'capability_definition_id',
        'purpose_definition_id', 'policy_version_id', 'role_template_id',
        'scope_schema_version', 'scope_payload', 'conditions_schema_version', 'conditions_payload',
        'effect', 'state', 'source', 'source_reference',
        'valid_from', 'valid_until',
        'author_person_account_link_id', 'approver_person_account_link_id',
        'activated_at', 'revoked_at', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'scope_schema_version' => 'integer',
            'conditions_schema_version' => 'integer',
            'effect' => GrantEffect::class,
            'state' => GrantState::class,
            'source' => GrantSource::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $grant): void {
            $grant->id ??= (string) Str::uuid7();
            $grant->valid_from ??= now();
        });
    }

    /**
     * Encode toujours en objet JSON, y compris lorsque le tableau PHP est
     * vide : un tableau PHP vide s'encoderait sinon en `[]` plutôt qu'en
     * `{}`, violant la contrainte PostgreSQL `jsonb_typeof(...) = 'object'`.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function scopePayload(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function conditionsPayload(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function personAccountLink(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'person_account_link_id');
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    /**
     * @return BelongsTo<CapabilityDefinition, $this>
     */
    public function capabilityDefinition(): BelongsTo
    {
        return $this->belongsTo(CapabilityDefinition::class, 'capability_definition_id');
    }

    /**
     * @return BelongsTo<PurposeDefinition, $this>
     */
    public function purposeDefinition(): BelongsTo
    {
        return $this->belongsTo(PurposeDefinition::class, 'purpose_definition_id');
    }

    /**
     * @return BelongsTo<PolicyVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function roleTemplate(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'role_template_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'author_person_account_link_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'approver_person_account_link_id');
    }

    public function scope(): ScopePayload
    {
        return ScopePayload::fromStored($this->scope_schema_version, $this->scope_payload);
    }

    public function conditions(): ConditionsPayload
    {
        return ConditionsPayload::fromStored($this->conditions_schema_version, $this->conditions_payload);
    }

    /**
     * Un grant expiré est refusé même si son état n'a pas encore été
     * matériellement changé par une tâche planifiée (P003-B1 §12).
     */
    public function isExpiredByTime(Carbon|CarbonImmutable $now): bool
    {
        return $this->valid_until !== null && $now->greaterThanOrEqualTo($this->valid_until);
    }
}
