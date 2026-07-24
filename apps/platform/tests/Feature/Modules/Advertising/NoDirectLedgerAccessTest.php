<?php

namespace Tests\Feature\Modules\Advertising;

use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * ADR-0010 §2, §7 : aucun accès direct aux tables `ledger.*` depuis
 * `App\Modules\Advertising` — seul `LedgerPoster` écrit. Analyse
 * statique du code source du module (comme une règle d'architecture,
 * architecture/12 « la CI analyse les dépendances, migrations et accès
 * SQL »), pas seulement une observation du comportement à l'exécution :
 * une régression future qui réintroduirait une écriture directe est
 * détectée même si aucun test comportemental ne l'exerce.
 *
 * `Account` (comptes) est explicitement exclu : P005-A §3.D autorise
 * Publicité à provisionner des comptes directement (`Account::create()`),
 * seuls les postings et transactions comptables sont réservés à
 * `LedgerPoster`.
 */
class NoDirectLedgerAccessTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/\bPosting::create\(/',
        '/\bPosting::insert\(/',
        '/\bPosting::query\(\)->insert\(/',
        '/\bLedgerTransaction::create\(/',
        '/\bLedgerTransaction::insert\(/',
        "/DB::table\\('ledger\\.postings'\\)->(insert|update|delete)\\(/",
        "/DB::table\\('ledger\\.ledger_transactions'\\)->(insert|update|delete)\\(/",
    ];

    public function test_advertising_module_never_writes_directly_to_ledger_postings_or_transactions(): void
    {
        $root = app_path('Modules/Advertising');
        $this->assertDirectoryExists($root);

        $finder = (new Finder)->files()->in($root)->name('*.php');

        $violations = [];

        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $contents = $file->getContents();

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = $file->getRelativePathname().' matches '.$pattern;
                }
            }
        }

        $this->assertSame([], $violations, "Publicité ne poste jamais directement dans ledger.* (ADR-0010 §2, §7) :\n".implode("\n", $violations));
    }
}
