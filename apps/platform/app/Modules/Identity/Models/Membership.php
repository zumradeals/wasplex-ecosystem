<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\MembershipStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Appartenance nominative reliant une organisation à une liaison
 * personne-compte existante.
 *
 * Référence `person_account_link_id` plutôt que `person_id` et `user_id`
 * séparément, afin qu'une appartenance ne puisse jamais associer le compte
 * d'une personne à l'identité d'une autre (revue SIRR P003-A.2 §1).
 *
 * N'accorde par elle-même aucune capacité d'autorisation (ADR-0004 §5, §22) :
 * le moteur complet de rôles, grants et permissions fait l'objet de P003-B.
 *
 * @property string $id
 * @property string $person_account_link_id
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
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function personAccountLink(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'person_account_link_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Filtre de requête pratique restreignant aux appartenances d'une
     * organisation donnée.
     *
     * Ce n'est PAS une frontière d'autorisation : `Membership::query()` sans
     * ce scope reste une lecture non filtrée, techniquement possible depuis
     * ce module. L'isolation effective côté serveur entre organisations sera
     * imposée par le moteur d'autorisations de P003-B (ADR-0004), pas par ce
     * scope Eloquent.
     *
     * @param  Builder<Membership>  $query
     * @return Builder<Membership>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
