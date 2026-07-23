<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Support\AssuranceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Axes d'assurance d'un compte (ecosystem/identite/01-niveaux-et-preuves.md).
 *
 * Ne constitue jamais un score global : chaque axe reste séparé et lisible
 * indépendamment (Constitution article 8 révisé par AMD-0009, §18).
 *
 * `session_assurance` n'est pas représenté ici : il appartient au contexte de
 * la session courante, pas à un état permanent (cf. SessionAssurance).
 *
 * @property string $id
 * @property int $user_id
 * @property AccountState $account_state
 * @property ContactAssurance $contact_assurance
 * @property IdentityAssurance $identity_assurance
 * @property UniquenessAssurance $uniqueness_assurance
 * @property OrganizationStatus $organization_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AssuranceState extends Model
{
    protected $table = 'identity.assurance_states';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_state' => AccountState::class,
            'contact_assurance' => ContactAssurance::class,
            'identity_assurance' => IdentityAssurance::class,
            'uniqueness_assurance' => UniquenessAssurance::class,
            'organization_status' => OrganizationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $state): void {
            $state->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Représente les axes d'assurance dans un contexte donné, en y ajoutant la
     * force de la session courante sans jamais la persister comme un niveau
     * permanent (cf. P003-A §6).
     */
    public function toContext(SessionAssurance $sessionAssurance = SessionAssurance::Weak): AssuranceContext
    {
        return new AssuranceContext(
            accountState: $this->account_state,
            contactAssurance: $this->contact_assurance,
            identityAssurance: $this->identity_assurance,
            uniquenessAssurance: $this->uniqueness_assurance,
            organizationStatus: $this->organization_status,
            sessionAssurance: $sessionAssurance,
        );
    }
}
