<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Alerts\Enums\PublicationStatus;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Projection publique minimisée d'un dossier `community` (ecosystem/alertes/03
 * §2). Ne référence le dossier source que par identifiant : aucune colonne
 * sensible (position exacte, téléphone, document complet) n'existe ici.
 *
 * @property string $id
 * @property string $case_id
 * @property int $version
 * @property string $title
 * @property string $summary
 * @property string|null $approximate_zone
 * @property array<string, mixed> $allowed_fields
 * @property PublicationStatus $status
 * @property string|null $validated_by_person_account_link_id
 * @property Carbon|CarbonImmutable|null $published_at
 * @property Carbon|CarbonImmutable|null $expires_at
 * @property Carbon|CarbonImmutable|null $withdrawn_at
 * @property string|null $withdrawal_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Publication extends Model
{
    protected $table = 'alerts.publications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'case_id', 'version', 'title', 'summary', 'approximate_zone', 'allowed_fields',
        'status', 'validated_by_person_account_link_id', 'published_at', 'expires_at',
        'withdrawn_at', 'withdrawal_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $publication): void {
            $publication->id ??= (string) Str::uuid7();
            $publication->allowed_fields ??= [];
        });
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide
     * (`jsonb_typeof(allowed_fields) = 'object'`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function allowedFields(): Attribute
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
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'validated_by_person_account_link_id');
    }
}
