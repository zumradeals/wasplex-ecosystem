<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\RoleTemplateState;
use App\Modules\Identity\Enums\OrganizationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Rôle modèle : ensemble versionné de capacités proposées (ADR-0004 §6).
 *
 * Invariant essentiel : un rôle modèle n'autorise rien par lui-même. Ni son
 * existence, ni son nom, ni son affectation ne produisent un droit tant
 * qu'aucun {@see Grant} explicite n'a été activé.
 *
 * @property string $id
 * @property string $stable_key
 * @property int $version
 * @property string $label
 * @property string $description
 * @property OrganizationCategory|null $organization_category
 * @property RoleTemplateState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RoleTemplate extends Model
{
    protected $table = 'governance.role_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stable_key', 'version', 'label', 'description',
        'organization_category', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'organization_category' => OrganizationCategory::class,
            'state' => RoleTemplateState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->id ??= (string) Str::uuid7();
            $template->effective_from ??= now();
        });
    }
}
