<?php

namespace App\Modules\Governance\Authorization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Finalité autorisée pour une capacité donnée.
 *
 * @property string $id
 * @property string $capability_definition_id
 * @property string $purpose_definition_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CapabilityPurpose extends Model
{
    protected $table = 'governance.capability_purposes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['capability_definition_id', 'purpose_definition_id'];

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->id ??= (string) Str::uuid7();
        });
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
}
