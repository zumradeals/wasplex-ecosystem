<?php

namespace App\Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Profil publicitaire facultatif et consenti d'une personne (véto du
 * dirigeant, 2026-07-30 ; AMD-0009). Séparé d'`identity.people` par
 * construction (P003-A §6, AMD-0009 §6) — voir la migration de création
 * pour le raisonnement complet sur le consentement versionné/révocable.
 *
 * @property string $id
 * @property string $person_id
 * @property string|null $age_bracket
 * @property string|null $gender
 * @property array<int, string> $interests
 * @property int|null $consent_version
 * @property Carbon|null $consent_given_at
 * @property Carbon|null $consent_withdrawn_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonAdvertisingProfile extends Model
{
    protected $table = 'advertising.person_advertising_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'person_id', 'age_bracket', 'gender', 'interests',
        'consent_version', 'consent_given_at', 'consent_withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'consent_version' => 'integer',
            'consent_given_at' => 'datetime',
            'consent_withdrawn_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            $profile->id ??= (string) Str::uuid7();
            $profile->interests ??= [];
        });
    }
}
