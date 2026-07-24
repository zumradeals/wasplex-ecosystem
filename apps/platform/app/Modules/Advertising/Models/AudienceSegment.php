<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Services\AudienceCriteria;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * AudienceSegment : critères autorisés + estimation agrégée (ADR-0010 §3).
 * Ne stocke jamais d'identité individuelle ; `criteria` est validé par
 * {@see AudienceCriteria} avant toute
 * écriture (AMD-0009 §14).
 *
 * @property string $id
 * @property string $campaign_version_id
 * @property array<string, mixed> $criteria
 * @property int $estimated_size
 * @property string $size_threshold_id
 * @property bool $below_threshold_at_creation
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AudienceSegment extends Model
{
    protected $table = 'advertising.audience_segments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['campaign_version_id', 'criteria', 'estimated_size', 'size_threshold_id', 'below_threshold_at_creation'];

    protected function casts(): array
    {
        return [
            'estimated_size' => 'integer',
            'below_threshold_at_creation' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $segment): void {
            $segment->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<CampaignVersion, $this>
     */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'campaign_version_id');
    }

    /**
     * @return BelongsTo<AudienceSegmentSizeThreshold, $this>
     */
    public function sizeThreshold(): BelongsTo
    {
        return $this->belongsTo(AudienceSegmentSizeThreshold::class, 'size_threshold_id');
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function criteria(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
