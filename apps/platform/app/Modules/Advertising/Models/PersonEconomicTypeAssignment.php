<?php

namespace App\Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Affectation courante d'une personne à un type économique (instruction
 * explicite du fondateur, 2026-07-31). Une seule ligne par personne, sans
 * historique dans ce lot — voir la migration de création pour le
 * raisonnement complet.
 *
 * @property string $id
 * @property string $person_id
 * @property string $economic_type_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonEconomicTypeAssignment extends Model
{
    protected $table = 'advertising.person_economic_type_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['person_id', 'economic_type_id'];

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignment->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<EconomicType, $this>
     */
    public function economicType(): BelongsTo
    {
        return $this->belongsTo(EconomicType::class, 'economic_type_id');
    }
}
