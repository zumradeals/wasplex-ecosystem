<?php

namespace App\Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Entrée de la référence des centres d'intérêt du profil publicitaire
 * (véto du dirigeant, 2026-07-30). Mirroir simple de
 * {@see SectorClassification} : pas d'écran admin dédié dans ce lot, gérée
 * via la commande `advertising:manage-interest-taxonomy`.
 *
 * @property string $id
 * @property string $code
 * @property string $label
 * @property string $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InterestTaxonomyEntry extends Model
{
    protected $table = 'advertising.interest_taxonomy_entries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'label', 'state'];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->id ??= (string) Str::uuid7();
            $entry->state ??= 'active';
        });
    }
}
