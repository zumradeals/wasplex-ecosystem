<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Une personne physique, distincte de tout compte d'accès.
 *
 * Ne duplique volontairement ni e-mail, ni mot de passe, ni document KYC,
 * ni donnée de profil publicitaire (cf. P003-A §6, AMD-0009).
 *
 * @property string $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Person extends Model
{
    protected $table = 'identity.people';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $person): void {
            $person->id ??= (string) Str::uuid7();
        });
    }
}
