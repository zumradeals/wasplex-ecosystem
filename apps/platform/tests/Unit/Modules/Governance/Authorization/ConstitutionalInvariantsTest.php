<?php

namespace Tests\Unit\Modules\Governance\Authorization;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Tests d'architecture simples empêchant la réapparition d'un super-
 * administrateur, d'un accès global implicite ou d'un niveau commercial
 * utilisé comme clé d'autorisation (P003-B1 §19, CLAUDE.md §2).
 */
class ConstitutionalInvariantsTest extends TestCase
{
    private const FORBIDDEN_GENERIC_POWER_LABELS = ['admin', 'super_admin', 'god', 'root', 'all', 'any'];

    private const FORBIDDEN_COMMERCIAL_LABELS = ['premium', 'elite', 'master'];

    public function test_no_is_admin_field_exists_in_the_governance_module(): void
    {
        foreach ($this->governanceModuleFiles() as $file) {
            $this->assertStringNotContainsStringIgnoringCase('is_admin', $file->getContents(), "Fichier interdit : {$file->getPathname()} contient is_admin.");
            $this->assertStringNotContainsStringIgnoringCase('is_super_admin', $file->getContents(), "Fichier interdit : {$file->getPathname()} contient is_super_admin.");
        }
    }

    public function test_no_wildcard_character_is_used_as_a_capability_or_scope_value(): void
    {
        // ScopePayload.php définit la liste des valeurs interdites (dont '*')
        // utilisée pour REFUSER un joker : sa présence littérale y est le
        // mécanisme de protection lui-même, pas une violation.
        $excluded = ['ConstitutionalInvariantsTest', 'ScopePayload.php'];

        foreach ($this->governanceModuleFiles() as $file) {
            if (Str::contains($file->getPathname(), $excluded)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                "/['\"]\\*['\"]/",
                $file->getContents(),
                "Fichier interdit : {$file->getPathname()} contient une chaîne joker '*'."
            );
        }
    }

    public function test_no_generic_power_or_commercial_label_is_hardcoded_as_a_stable_key(): void
    {
        foreach ([...self::FORBIDDEN_GENERIC_POWER_LABELS, ...self::FORBIDDEN_COMMERCIAL_LABELS] as $label) {
            foreach ($this->governanceModuleFiles() as $file) {
                if (str_contains($file->getPathname(), 'ConstitutionalInvariantsTest')) {
                    continue;
                }

                $pattern = "/stable_key['\"]?\\s*(=>|=)\\s*['\"]{$label}\\b/i";

                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $file->getContents(),
                    "Fichier interdit : {$file->getPathname()} code en dur une clé stable \"{$label}\"."
                );
            }
        }
    }

    /**
     * @return list<\SplFileInfo>
     */
    private function governanceModuleFiles(): array
    {
        $root = dirname(__DIR__, 5).'/app/Modules/Governance';

        $finder = Finder::create()->files()->name('*.php')->in($root);

        return iterator_to_array($finder, false);
    }
}
