<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\VideoAdDurationBounds;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bornes de durée vidéo (Lot 4, véto du dirigeant 2026-07-30) : une seule
 * ligne `active` à la fois — même discipline que
 * `AudienceSegmentSizeThreshold`, garantie par l'index unique partiel de la
 * migration.
 */
class VideoAdDurationBoundsTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_the_migration_seeds_the_real_active_bounds(): void
    {
        $bounds = VideoAdDurationBounds::query()->where('state', 'active')->first();

        $this->assertNotNull($bounds);
        $this->assertSame(30, $bounds->min_seconds);
        $this->assertSame(60, $bounds->max_seconds);
    }

    public function test_two_active_rows_are_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);

        DB::table('advertising.video_ad_duration_bounds')->insert([
            'id' => (string) Str::uuid7(),
            'min_seconds' => 20,
            'max_seconds' => 40,
            'version' => 2,
            'state' => 'active',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
