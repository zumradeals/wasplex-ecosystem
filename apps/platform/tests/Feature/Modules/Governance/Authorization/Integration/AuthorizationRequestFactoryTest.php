<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubjectResolver;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * Fabrique de requêtes d'autorisation (P003-B2 §B). Un contexte serveur
 * valide se transforme fidèlement en {@see AuthorizationRequest},
 * sans jamais deviner une donnée métier absente.
 */
class AuthorizationRequestFactoryTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_a_valid_server_context_is_transformed_correctly(): void
    {
        $user = $this->makeUser('integration-fabrique@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization('Organisation Intégration Fabrique');
        $membership = $this->makeActiveMembership($user, $organization);

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Standard, $membership->id);

        $resource = $this->makeResourceContext(
            resourceType: 'invoice',
            resourceId: 'invoice-42',
            organizationId: $organization->id,
        );

        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $resource,
            environment: Environment::Testing,
            purposeKey: 'test_purpose',
            countryCode: 'CI',
            territoryCodes: ['CI-AB'],
            correlationId: 'fixed-correlation-id',
        );

        $this->assertSame($user->id, $request->accountUserId);
        $this->assertSame($link->id, $request->personAccountLinkId);
        $this->assertSame($membership->id, $request->membershipId);
        $this->assertSame('sample.read', $request->capabilityKey);
        $this->assertSame(Operation::Read, $request->operation);
        $this->assertSame($resource, $request->resource);
        $this->assertSame(Environment::Testing, $request->environment);
        $this->assertSame('test_purpose', $request->purposeKey);
        $this->assertSame('CI', $request->countryCode);
        $this->assertSame(['CI-AB'], $request->territoryCodes);
        $this->assertSame('fixed-correlation-id', $request->correlationId);
        $this->assertSame(SessionAssurance::Standard, $request->assurance->sessionAssurance);
    }

    public function test_an_absent_correlation_id_is_generated_never_left_empty(): void
    {
        $user = $this->makeUser('integration-correlation@example.com');
        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);

        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(),
            environment: Environment::Testing,
        );

        $this->assertNotSame('', $request->correlationId);
    }

    public function test_a_subject_without_membership_yields_a_null_membership_id(): void
    {
        $user = $this->makeUser('integration-sans-membre-fabrique@example.com');
        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);

        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(),
            environment: Environment::Testing,
        );

        $this->assertNull($request->membershipId);
    }
}
