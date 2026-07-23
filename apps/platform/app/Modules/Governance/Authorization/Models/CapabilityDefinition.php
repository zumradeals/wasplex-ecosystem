<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Identity\Enums\SessionAssurance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Définition d'une capacité atomique (ADR-0004 §5). Un nom de capacité ne
 * vaut jamais autorisation sans ses autres dimensions.
 *
 * @property string $id
 * @property string $stable_key
 * @property int $version
 * @property string $domain
 * @property string $action
 * @property string $description
 * @property Operation $operation
 * @property RiskClass $risk_class
 * @property bool $purpose_required
 * @property bool $approval_required
 * @property SessionAssurance $minimum_session_assurance
 * @property CapabilityState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CapabilityDefinition extends Model
{
    protected $table = 'governance.capability_definitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stable_key', 'version', 'domain', 'action', 'description', 'operation',
        'risk_class', 'purpose_required', 'approval_required',
        'minimum_session_assurance', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'operation' => Operation::class,
            'risk_class' => RiskClass::class,
            'purpose_required' => 'boolean',
            'approval_required' => 'boolean',
            'minimum_session_assurance' => SessionAssurance::class,
            'state' => CapabilityState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $definition->id ??= (string) Str::uuid7();
            $definition->effective_from ??= now();
        });
    }
}
