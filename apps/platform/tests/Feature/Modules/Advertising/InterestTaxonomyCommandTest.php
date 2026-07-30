<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `advertising:manage-interest-taxonomy` (véto du dirigeant, 2026-07-30) —
 * seul moyen de peupler `advertising.interest_taxonomy_entries` tant
 * qu'aucun écran admin n'existe (même état que `SectorClassification`).
 */
class InterestTaxonomyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_creates_an_active_entry(): void
    {
        $this->artisan('advertising:manage-interest-taxonomy', ['action' => 'add', 'code' => 'sport', 'label' => 'Sport'])
            ->assertExitCode(0);

        $entry = InterestTaxonomyEntry::query()->where('code', 'sport')->sole();
        $this->assertSame('Sport', $entry->label);
        $this->assertSame('active', $entry->state);
    }

    public function test_add_without_a_label_fails(): void
    {
        $this->artisan('advertising:manage-interest-taxonomy', ['action' => 'add', 'code' => 'sport'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('advertising.interest_taxonomy_entries', 0);
    }

    public function test_retire_marks_an_existing_entry_retired(): void
    {
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);

        $this->artisan('advertising:manage-interest-taxonomy', ['action' => 'retire', 'code' => 'sport'])
            ->assertExitCode(0);

        $entry = InterestTaxonomyEntry::query()->where('code', 'sport')->sole();
        $this->assertSame('retired', $entry->state);
    }

    public function test_retire_of_an_unknown_code_fails(): void
    {
        $this->artisan('advertising:manage-interest-taxonomy', ['action' => 'retire', 'code' => 'inconnu'])
            ->assertExitCode(1);
    }

    public function test_add_reactivates_and_relabels_an_existing_entry(): void
    {
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Ancien libellé', 'state' => 'retired']);

        $this->artisan('advertising:manage-interest-taxonomy', ['action' => 'add', 'code' => 'sport', 'label' => 'Sport et fitness'])
            ->assertExitCode(0);

        $entry = InterestTaxonomyEntry::query()->where('code', 'sport')->sole();
        $this->assertSame('Sport et fitness', $entry->label);
        $this->assertSame('active', $entry->state);
    }
}
