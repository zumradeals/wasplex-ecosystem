<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Enums\ReviewLevel;
use App\Modules\Advertising\Enums\SectorClass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Matrice de classification des secteurs, versionnée
 * (`01-classification-secteurs-et-contenus.md` §4). Régie par ADR-0002 :
 * jamais codée en dur, jamais réécrite (une nouvelle version remplace la
 * précédente sans effacer l'historique).
 *
 * @property string $id
 * @property string $country_code
 * @property string $sector
 * @property int $version
 * @property SectorClass $sector_class
 * @property int|null $minimum_age
 * @property array<int, mixed> $required_evidence
 * @property array<int, mixed> $warnings
 * @property array<int, mixed> $allowed_formats
 * @property array<int, mixed> $allowed_targeting
 * @property array<string, mixed> $frequency_rules
 * @property ReviewLevel $review_level
 * @property int $minimum_approvals
 * @property ConfigurationState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SectorClassification extends Model
{
    protected $table = 'advertising.sector_classifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'country_code', 'sector', 'version', 'sector_class', 'minimum_age',
        'required_evidence', 'warnings', 'allowed_formats', 'allowed_targeting', 'frequency_rules',
        'review_level', 'minimum_approvals', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'sector_class' => SectorClass::class,
            'minimum_age' => 'integer',
            'required_evidence' => 'array',
            'warnings' => 'array',
            'allowed_formats' => 'array',
            'allowed_targeting' => 'array',
            'review_level' => ReviewLevel::class,
            'minimum_approvals' => 'integer',
            'state' => ConfigurationState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $classification): void {
            $classification->id ??= (string) Str::uuid7();
            $classification->required_evidence ??= [];
            $classification->warnings ??= [];
            $classification->allowed_formats ??= [];
            $classification->allowed_targeting ??= [];
            $classification->frequency_rules ??= [];
            $classification->effective_from ??= now();
        });
    }

    public function requiresIndependentApprover(): bool
    {
        return $this->review_level === ReviewLevel::Enhanced || $this->minimum_approvals >= 2;
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide,
     * afin de respecter `jsonb_typeof(frequency_rules) = 'object'` (même
     * choix que `Grant::scopePayload()`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function frequencyRules(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
