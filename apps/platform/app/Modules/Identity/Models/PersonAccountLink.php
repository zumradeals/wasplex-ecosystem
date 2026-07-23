<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Liaison historisée entre une personne et un compte (ADR-0006 §6).
 *
 * Un compte ne possède jamais simultanément deux liaisons actives contradictoires ;
 * cette garantie est appliquée par un index unique partiel PostgreSQL.
 *
 * @property string $id
 * @property string $person_id
 * @property int $user_id
 * @property LinkStatus $status
 * @property LinkOrigin $origin
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonAccountLink extends Model
{
    protected $table = 'identity.person_account_links';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => LinkStatus::class,
            'origin' => LinkOrigin::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->id ??= (string) Str::uuid7();
            $link->effective_from ??= now();
        });
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
