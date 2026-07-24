<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CampaignVersion : liaison indivisible créations/audience/prix/événement
 * attendu/rémunération/destination/durée (ADR-0010 §3). Immuable dès l'état
 * `approved` — voir le déclencheur
 * `campaign_versions_prevent_semantic_mutation` et
 * {@see CampaignVersionService}, seul
 * point d'écriture attendu de ce cycle.
 *
 * @property string $id
 * @property string $campaign_id
 * @property int $version
 * @property CampaignVersionState $state
 * @property string $sector_classification_id
 * @property array<string, mixed> $creations
 * @property array<string, mixed> $expected_event
 * @property array<string, mixed> $destination
 * @property array<int, mixed> $territory
 * @property string|null $pricing_configuration_key
 * @property int|null $pricing_configuration_version
 * @property string|null $reward_configuration_key
 * @property int|null $reward_configuration_version
 * @property Carbon|CarbonImmutable $valid_from
 * @property Carbon|CarbonImmutable|null $valid_until
 * @property string $author_person_account_link_id
 * @property string|null $approver_person_account_link_id
 * @property Carbon|CarbonImmutable|null $approved_at
 * @property Carbon|CarbonImmutable|null $retired_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CampaignVersion extends Model
{
    protected $table = 'advertising.campaign_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id', 'version', 'state', 'sector_classification_id',
        'creations', 'expected_event', 'destination', 'territory',
        'pricing_configuration_key', 'pricing_configuration_version',
        'reward_configuration_key', 'reward_configuration_version',
        'valid_from', 'valid_until',
        'author_person_account_link_id', 'approver_person_account_link_id',
        'approved_at', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'state' => CampaignVersionState::class,
            'territory' => 'array',
            'pricing_configuration_version' => 'integer',
            'reward_configuration_version' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->id ??= (string) Str::uuid7();
            $version->territory ??= [];
            $version->valid_from ??= now();
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<SectorClassification, $this>
     */
    public function sectorClassification(): BelongsTo
    {
        return $this->belongsTo(SectorClassification::class, 'sector_classification_id');
    }

    /**
     * @return HasOne<AudienceSegment, $this>
     */
    public function audienceSegment(): HasOne
    {
        return $this->hasOne(AudienceSegment::class, 'campaign_version_id');
    }

    /**
     * @return HasMany<QualifiedEvent, $this>
     */
    public function qualifiedEvents(): HasMany
    {
        return $this->hasMany(QualifiedEvent::class, 'campaign_version_id');
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

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function creations(): Attribute
    {
        return $this->forceObjectAttribute();
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function expectedEvent(): Attribute
    {
        return $this->forceObjectAttribute();
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function destination(): Attribute
    {
        return $this->forceObjectAttribute();
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide,
     * afin de respecter les contraintes `jsonb_typeof(...) = 'object'`
     * (même choix que `Grant::scopePayload()`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    private function forceObjectAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
