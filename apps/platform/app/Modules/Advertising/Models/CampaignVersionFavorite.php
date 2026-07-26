<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Favori d'une personne sur une `CampaignVersion` (Lot 3 Phase A, décision
 * de Koné 2026-07-26). Même mécanique que {@see CampaignVersionLike} :
 * une ligne = un favori actif, retirer supprime la ligne.
 *
 * @property string $id
 * @property string $campaign_version_id
 * @property string $person_account_link_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CampaignVersionFavorite extends Model
{
    protected $table = 'advertising.campaign_version_favorites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['campaign_version_id', 'person_account_link_id'];

    protected static function booted(): void
    {
        static::creating(function (self $favorite): void {
            $favorite->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<CampaignVersion, $this>
     */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'campaign_version_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function personAccountLink(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'person_account_link_id');
    }
}
