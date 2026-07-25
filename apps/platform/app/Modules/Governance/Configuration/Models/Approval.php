<?php

namespace App\Modules\Governance\Configuration\Models;

use App\Modules\Governance\Configuration\Enums\ApprovalDecision;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Approval (ADR-0002 §8) : décision nominative d'un approbateur sur une
 * `ValueVersion`. Jamais modifiée ni supprimée après création — voir les
 * déclencheurs `approvals_prevent_update`/`approvals_prevent_deletion`.
 *
 * @property string $id
 * @property string $value_version_id
 * @property string $approver_person_account_link_id
 * @property ApprovalDecision $decision
 * @property string|null $motif
 * @property Carbon|CarbonImmutable $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Approval extends Model
{
    protected $table = 'configuration.approvals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'value_version_id', 'approver_person_account_link_id', 'decision', 'motif', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $approval): void {
            $approval->id ??= (string) Str::uuid7();
            $approval->decided_at ??= now();
        });
    }

    /**
     * @return BelongsTo<ValueVersion, $this>
     */
    public function valueVersion(): BelongsTo
    {
        return $this->belongsTo(ValueVersion::class, 'value_version_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'approver_person_account_link_id');
    }
}
