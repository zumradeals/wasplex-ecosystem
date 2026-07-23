<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Models\User;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubjectResolver;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\DeferredAuthorizationContext;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * Un contrôleur HTTP et un appel hors HTTP (commande, worker) utilisent
 * exactement le même point d'application (P003-B2 §C-E, tests 12 et 15).
 *
 * La route de démonstration n'existe que dans ce test : elle n'est jamais
 * déclarée dans `routes/web.php` ni dans un contrôleur applicatif, et
 * aucune capacité métier sensible réelle n'y est protégée.
 */
class HttpAndWorkerParityTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_no_business_route_was_added_to_the_application(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $settingsRoutes = file_get_contents(base_path('routes/settings.php'));

        $this->assertStringNotContainsString('Governance\\Authorization\\Integration', $webRoutes);
        $this->assertStringNotContainsString('Governance\\Authorization\\Integration', $settingsRoutes);
        $this->assertFalse(Route::has('__test.demo-authorize'));
    }

    public function test_http_controller_and_direct_call_reach_the_same_decision(): void
    {
        $user = $this->makeUser('parity-http@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        // Route de démonstration strictement locale à ce test.
        Route::middleware('web')->post('/__test/demo-authorize', function (Request $request) {
            try {
                $subject = app(AuthenticatedSubjectHttpResolver::class)->resolve($request);

                $authorizationRequest = app(AuthorizationRequestFactory::class)->make(
                    subject: $subject,
                    capabilityKey: $request->string('capability')->toString(),
                    operation: Operation::Read,
                    resource: new ResourceContext(
                        resourceType: null,
                        resourceId: null,
                        organizationId: null,
                        ownerPersonId: $subject->personAccountLink->person_id,
                        countryCode: null,
                        territoryCodes: [],
                        environment: null,
                    ),
                    environment: Environment::Testing,
                );

                $result = app(AuthorizationGate::class)->authorize($authorizationRequest);

                return response()->json(['decision' => $result->decision->value]);
            } catch (AuthorizationOutcomeException $exception) {
                return app(AuthorizationFailureResponder::class)->forOutcome($exception);
            } catch (SubjectResolutionFailedException $exception) {
                return app(AuthorizationFailureResponder::class)->forUnresolvedSubject($exception);
            }
        })->name('__test.demo-authorize');

        $response = $this->actingAs($user)->postJson('/__test/demo-authorize', ['capability' => 'sample.read']);
        $response->assertOk();
        $response->assertJson(['decision' => 'allowed']);

        // Appel hors HTTP, strictement équivalent, via le même point
        // d'application.
        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $directRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );
        $directResult = app(AuthorizationGate::class)->authorize($directRequest);

        $this->assertSame('allowed', $directResult->decision->value);
    }

    public function test_http_failure_response_is_safe_and_structured(): void
    {
        Route::middleware('web')->post('/__test/demo-authorize-denied', function (Request $request) {
            try {
                $subject = app(AuthenticatedSubjectHttpResolver::class)->resolve($request);

                $authorizationRequest = app(AuthorizationRequestFactory::class)->make(
                    subject: $subject,
                    capabilityKey: 'nonexistent.capability',
                    operation: Operation::Read,
                    resource: $this->makeResourceContext(),
                    environment: Environment::Testing,
                );

                app(AuthorizationGate::class)->authorize($authorizationRequest);

                return response()->json(['decision' => 'allowed']);
            } catch (AuthorizationOutcomeException $exception) {
                return app(AuthorizationFailureResponder::class)->forOutcome($exception);
            }
        })->name('__test.demo-authorize-denied');

        $user = $this->makeUser('parity-denied@example.com');

        $response = $this->actingAs($user)->postJson('/__test/demo-authorize-denied');

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        $body = $response->json();

        $this->assertSame('denied', $body['decision']);

        foreach (['grant', 'policy', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
    }

    /**
     * Un worker ne réutilise jamais une identité système universelle : il
     * transporte l'initiateur, la capacité et la corrélation explicitement,
     * puis réévalue entièrement l'autorisation juste avant l'effet — une
     * révocation survenue entre le déclenchement et l'exécution doit donc
     * être observée (P003-B2 §E).
     */
    public function test_deferred_worker_context_is_fully_reevaluated_before_the_effect(): void
    {
        $user = $this->makeUser('parity-worker@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $context = new DeferredAuthorizationContext(
            initiatorAccountUserId: $user->id,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            claimedMembershipId: null,
            purposeKey: null,
            correlationId: (string) Str::uuid(),
            sessionAssuranceAtDispatch: SessionAssurance::Standard,
        );

        // Le déclenchement a eu lieu ; le grant est révoqué avant que le
        // worker n'exécute réellement l'effet.
        app(GrantManager::class)->revoke($grant, $link, 'test', (string) Str::uuid());

        $reresolvedAccount = User::query()->findOrFail($context->initiatorAccountUserId);
        $subject = app(AuthenticatedSubjectResolver::class)->resolve(
            $reresolvedAccount,
            SessionAssurance::Weak,
            $context->claimedMembershipId,
        );

        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: $context->capabilityKey,
            operation: $context->operation,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
            purposeKey: $context->purposeKey,
            correlationId: $context->correlationId,
        );

        $result = app(AuthorizationGate::class)->evaluate($request);

        $this->assertSame('denied', $result->decision->value);
        $this->assertSame($context->correlationId, $result->correlationId);
    }
}
