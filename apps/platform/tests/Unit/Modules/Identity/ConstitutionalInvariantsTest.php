<?php

namespace Tests\Unit\Modules\Identity;

use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Tests d'architecture simples empêchant la réapparition d'un cinquième
 * acteur, d'un accès global implicite ou d'un niveau commercial utilisé
 * comme clé d'autorisation (P003-A §10, CLAUDE.md §2).
 */
class ConstitutionalInvariantsTest extends TestCase
{
    private const ENUM_CLASSES = [
        AccountState::class,
        ContactAssurance::class,
        IdentityAssurance::class,
        UniquenessAssurance::class,
        SessionAssurance::class,
        OrganizationStatus::class,
        LinkStatus::class,
        LinkOrigin::class,
        OrganizationCategory::class,
        OrganizationState::class,
        MembershipStatus::class,
    ];

    private const FORBIDDEN_COMMERCIAL_LABELS = ['premium', 'elite', 'master'];

    public function test_no_is_admin_field_exists_in_the_identity_foundation(): void
    {
        foreach ($this->identityModuleFiles() as $file) {
            $this->assertStringNotContainsStringIgnoringCase(
                'is_admin',
                $file->getContents(),
                "Fichier interdit : {$file->getPathname()} contient is_admin."
            );

            $this->assertStringNotContainsStringIgnoringCase(
                'is_super_admin',
                $file->getContents(),
                "Fichier interdit : {$file->getPathname()} contient is_super_admin."
            );
        }
    }

    public function test_no_commercial_tier_label_is_used_as_an_enum_value(): void
    {
        foreach (self::ENUM_CLASSES as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                foreach (self::FORBIDDEN_COMMERCIAL_LABELS as $label) {
                    $this->assertStringNotContainsString(
                        $label,
                        strtolower($case->value),
                        "{$enumClass}::{$case->name} ne doit pas porter de nom commercial."
                    );
                }
            }
        }
    }

    public function test_no_agent_actor_category_exists(): void
    {
        $values = OrganizationCategory::values();

        $this->assertNotContains('agent', $values);
        $this->assertSame(['wasplex', 'advertiser', 'institution'], $values);

        foreach ($this->identityModuleFiles() as $file) {
            if (str_contains($file->getPathname(), 'ConstitutionalInvariantsTest')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\bclass\s+\w*Agent\w*/i',
                $file->getContents(),
                "Fichier interdit : {$file->getPathname()} définit une classe Agent."
            );
        }
    }

    /**
     * @return list<\SplFileInfo>
     */
    private function identityModuleFiles(): array
    {
        $root = dirname(__DIR__, 4).'/app/Modules/Identity';

        $finder = Finder::create()->files()->name('*.php')->in($root);

        return iterator_to_array($finder, false);
    }
}
