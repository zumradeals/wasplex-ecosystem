<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Alerts\Enums\CorrespondenceReviewState;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Prétention de reconnaissance d'un bien/personne publié
 * (ecosystem/alertes/02 §7). Un candidat, jamais une décision finale — les
 * caractéristiques secrètes du dossier source ne sont jamais exposées ici,
 * seule la réponse du déclarant est enregistrée pour rapprochement humain.
 *
 * @property string $id
 * @property string $case_id
 * @property string $reporter_person_account_link_id
 * @property string $non_public_description
 * @property array<string, mixed> $verification_response
 * @property CorrespondenceReviewState $review_state
 * @property string|null $reviewed_by_person_account_link_id
 * @property Carbon|CarbonImmutable|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CorrespondenceReport extends Model
{
    protected $table = 'alerts.correspondence_reports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'case_id', 'reporter_person_account_link_id', 'non_public_description',
        'verification_response', 'review_state', 'reviewed_by_person_account_link_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'review_state' => CorrespondenceReviewState::class,
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            $report->id ??= (string) Str::uuid7();
            $report->verification_response ??= [];
            $report->review_state ??= CorrespondenceReviewState::Pending;
        });
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide
     * (`jsonb_typeof(verification_response) = 'object'`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function verificationResponse(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<AlertCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AlertCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'reporter_person_account_link_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'reviewed_by_person_account_link_id');
    }
}
