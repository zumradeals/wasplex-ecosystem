<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\PrecautionaryMeasure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ModerationCase (ADR-0010 §3 ; `03-signalements-sanctions-et-remuneration.md`
 * §1-2). N'entraîne aucune écriture Ledger directe : seule la mesure
 * conservatoire `campaign_suspended` bloque, au niveau applicatif, toute
 * nouvelle réservation de budget (ADR-0010 §4).
 *
 * @property string $id
 * @property string $campaign_id
 * @property string|null $campaign_version_id
 * @property string $reason
 * @property string|null $observed_destination
 * @property string $severity
 * @property string $status
 * @property PrecautionaryMeasure $precautionary_measure
 * @property string|null $decision
 * @property string|null $recourse_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ModerationCase extends Model
{
    protected $table = 'advertising.moderation_cases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id', 'campaign_version_id', 'reason', 'observed_destination', 'severity',
        'status', 'precautionary_measure', 'decision', 'recourse_status',
    ];

    protected function casts(): array
    {
        return [
            'precautionary_measure' => PrecautionaryMeasure::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->id ??= (string) Str::uuid7();
            $case->status ??= 'open';
            $case->precautionary_measure ??= PrecautionaryMeasure::None;
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
     * @return BelongsTo<CampaignVersion, $this>
     */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'campaign_version_id');
    }
}
