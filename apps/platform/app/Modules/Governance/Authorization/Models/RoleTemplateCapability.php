<?php

namespace App\Modules\Governance\Authorization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Capacité proposée par un rôle modèle. Ne confère elle-même aucun droit.
 *
 * @property string $id
 * @property string $role_template_id
 * @property string $capability_definition_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RoleTemplateCapability extends Model
{
    protected $table = 'governance.role_template_capabilities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['role_template_id', 'capability_definition_id'];

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<RoleTemplate, $this>
     */
    public function roleTemplate(): BelongsTo
    {
        return $this->belongsTo(RoleTemplate::class, 'role_template_id');
    }

    /**
     * @return BelongsTo<CapabilityDefinition, $this>
     */
    public function capabilityDefinition(): BelongsTo
    {
        return $this->belongsTo(CapabilityDefinition::class, 'capability_definition_id');
    }
}
