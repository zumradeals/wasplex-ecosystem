<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Journal append-only de la machine d'états d'un dossier (AMD-0007 §5).
 * Jamais modifié ni supprimé après création — voir les déclencheurs
 * `case_events_prevent_update`/`case_events_prevent_deletion`.
 *
 * @property string $id
 * @property string $case_id
 * @property string $event_type
 * @property string|null $from_state
 * @property string|null $to_state
 * @property string|null $actor_person_account_link_id
 * @property string|null $actor_organization_id
 * @property string|null $channel
 * @property string $correlation_id
 * @property string|null $idempotency_key
 * @property array<string, mixed> $metadata
 * @property Carbon|CarbonImmutable $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CaseEvent extends Model
{
    protected $table = 'alerts.case_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'case_id', 'event_type', 'from_state', 'to_state',
        'actor_person_account_link_id', 'actor_organization_id', 'channel',
        'correlation_id', 'idempotency_key', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
            $event->metadata ??= [];
            $event->occurred_at ??= now();
        });
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide
     * (`jsonb_typeof(metadata) = 'object'`) — même garde que
     * `Definition::constraints()`/`Grant::scopePayload()`.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function metadata(): Attribute
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'actor_person_account_link_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function actorOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'actor_organization_id');
    }
}
