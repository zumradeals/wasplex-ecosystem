<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\MembershipStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Appartenance nominative reliant une personne, un compte et une organisation.
 *
 * N'accorde par elle-même aucune capacité d'autorisation (ADR-0004 §5, §22) :
 * le moteur complet de rôles, grants et permissions fait l'objet de P003-B.
 *
 * @property string $id
 * @property string $person_id
 * @property int $user_id
 * @property string $organization_id
 * @property MembershipStatus $status
 * @property string|null $title
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Membership extends Model
{
    protected $table = 'identity.memberships';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            $membership->id ??= (string) Str::uuid7();
            $membership->effective_from ??= now();
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

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Seul point d'accès aux appartenances d'une organisation fourni par le
     * module : garantit qu'une organisation ne voit jamais les appartenances
     * d'une autre organisation par un accès non filtré (ADR-0006 §4, ADR-0004 §7).
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
