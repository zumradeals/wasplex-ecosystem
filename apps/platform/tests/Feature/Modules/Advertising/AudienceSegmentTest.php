<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\AudienceSegment;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Advertising\Services\Exceptions\ForbiddenTargetingCriterionException;
use App\Modules\Advertising\Services\Exceptions\SegmentBelowMinimumThresholdException;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;

/**
 * ADR-0010 §3, §7 ; AMD-0009 §13-16 : aucune identité individuelle
 * retournée par une requête de correspondance d'audience ; un segment
 * sous le seuil minimal configuré est refusé, jamais retourné tel quel.
 */
class AudienceSegmentTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_segment_below_the_minimum_threshold_is_refused_not_returned_as_is(): void
    {
        $this->makeActiveSizeThreshold(500);
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        $segment = app(AudienceSegmentGuard::class)->createSegment(
            $version,
            ['country' => 'CI', 'age_range' => '18-25'],
            120,
        );

        $this->assertTrue($segment->below_threshold_at_creation);

        $this->expectException(SegmentBelowMinimumThresholdException::class);

        app(AudienceSegmentGuard::class)->retrievableSize($segment);
    }

    public function test_a_segment_at_or_above_the_minimum_threshold_returns_its_exact_size(): void
    {
        $this->makeActiveSizeThreshold(500);
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        $segment = app(AudienceSegmentGuard::class)->createSegment(
            $version,
            ['country' => 'CI', 'age_range' => '18-25'],
            5_000,
        );

        $this->assertFalse($segment->below_threshold_at_creation);
        $this->assertSame(5_000, app(AudienceSegmentGuard::class)->retrievableSize($segment));
    }

    public function test_a_forbidden_sensitive_criterion_is_refused_before_any_write(): void
    {
        $this->makeActiveSizeThreshold(500);
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);

        $this->expectException(ForbiddenTargetingCriterionException::class);

        try {
            app(AudienceSegmentGuard::class)->createSegment(
                $version,
                ['country' => 'CI', 'religion' => 'any'],
                10_000,
            );
        } finally {
            $this->assertDatabaseCount('advertising.audience_segments', 0);
        }
    }

    /**
     * AMD-0009 §11, §13 : l'annonceur ne reçoit ni identité ni
     * coordonnées ; AudienceSegment ne référence structurellement aucun
     * modèle d'identité individuelle (aucune relation, aucune colonne).
     */
    public function test_audience_segment_never_references_an_individual_identity_model(): void
    {
        $reflection = new ReflectionClass(AudienceSegment::class);

        foreach ($reflection->getMethods() as $method) {
            $returnType = $method->getReturnType();
            $this->assertFalse(
                $returnType !== null && str_contains((string) $returnType, PersonAccountLink::class),
                "AudienceSegment ne doit référencer aucune identité individuelle (méthode {$method->getName()})"
            );
        }

        $columns = (new AudienceSegment)->getConnection()->getSchemaBuilder()->getColumns('advertising.audience_segments');
        $columnNames = array_column($columns, 'name');

        foreach (['person_id', 'user_id', 'person_account_link_id', 'email', 'phone'] as $forbiddenColumn) {
            $this->assertNotContains($forbiddenColumn, $columnNames);
        }
    }
}
