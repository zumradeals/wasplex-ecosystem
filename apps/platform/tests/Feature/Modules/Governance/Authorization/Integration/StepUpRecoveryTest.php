<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\StepUpRequiredException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * TD-0002-B : canal de récupération d'une élévation de session.
 *
 * Ce test ne construit aucune route ni écran de renforcement réel : il
 * démontre seulement (1) que la réponse HTTP d'un `step_up_required`
 * indique le palier requis et le type d'action attendue sans jamais
 * exposer de détail de politique, et (2) que rien — ni l'exception, ni
 * l'{@see AuthorizationResult}
 * qu'elle porte — ne peut jamais être "rejoué" pour obtenir une décision
 * sans repasser entièrement par {@see AuthenticatedSubjectHttpResolver} puis
 * {@see AuthorizationGate}.
 */
class StepUpRecoveryTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_step_up_required_response_for_a_strong_capability_indicates_password_confirmation(): void
    {
        $user = $this->makeUser('stepup-strong@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.step_up_strong', minimumSessionAssurance: SessionAssurance::Strong);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $request = $this->makeRequest(
            $user,
            'sample.step_up_strong',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        );

        $response = $this->respondToStepUp($request);

        $body = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('step_up_required', $body['decision']);
        $this->assertSame('strong', $body['required_session_assurance']);
        $this->assertSame('password_confirmation', $body['step_up_action']);

        foreach (['grant', 'policy', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
    }

    /**
     * La capacité exigeant `standard` n'est jamais réellement catalogable en
     * dehors des tests (TD-0002-A, mesure temporaire inchangée) : ce test
     * vérifie seulement que la réponse reste correcte si une telle capacité
     * de test existe, pas qu'un vrai parcours `standard` est atteignable
     * aujourd'hui.
     */
    public function test_step_up_required_response_for_a_standard_capability_indicates_two_factor_or_passkey_action(): void
    {
        $user = $this->makeUser('stepup-standard@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.step_up_standard', minimumSessionAssurance: SessionAssurance::Standard);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $request = $this->makeRequest(
            $user,
            'sample.step_up_standard',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        );

        $response = $this->respondToStepUp($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame('standard', $body['required_session_assurance']);
        $this->assertSame('two_factor_or_passkey_verification', $body['step_up_action']);
    }

    /**
     * Garantie par construction : le moteur d'autorisation n'a aucune
     * méthode acceptant autre chose qu'une {@see AuthorizationRequest}
     * fraîche. Un `AuthorizationResult` ou une exception d'issue déjà
     * obtenus ne peuvent donc structurellement jamais être présentés comme
     * preuve d'une décision.
     */
    public function test_authorization_gate_never_accepts_a_previous_result_as_input(): void
    {
        $authorizeParameters = (new ReflectionMethod(AuthorizationGate::class, 'authorize'))->getParameters();
        $evaluateParameters = (new ReflectionMethod(AuthorizationGate::class, 'evaluate'))->getParameters();

        $this->assertCount(1, $authorizeParameters);
        $this->assertSame(AuthorizationRequest::class, $authorizeParameters[0]->getType()?->getName());

        $this->assertCount(1, $evaluateParameters);
        $this->assertSame(AuthorizationRequest::class, $evaluateParameters[0]->getType()?->getName());
    }

    /**
     * Un `step_up_required` obtenu avec une session faible, suivi d'un
     * véritable renforcement (reconfirmation de mot de passe constatée par
     * Laravel lui-même), ne peut aboutir à `allowed` qu'en repassant
     * entièrement par une résolution fraîche du sujet puis une nouvelle
     * évaluation — jamais en réutilisant l'exception ou le résultat obtenus
     * lors de la première tentative.
     */
    public function test_a_genuine_session_reinforcement_only_succeeds_through_a_fresh_reevaluation(): void
    {
        $user = $this->makeUser('stepup-recovery-success@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.step_up_recovery_ok', minimumSessionAssurance: SessionAssurance::Strong);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $sessionStore = $this->app['session.store'];

        $weakRequest = Request::create('/');
        $weakRequest->setLaravelSession($sessionStore);
        $weakRequest->setUserResolver(fn () => $user);

        $subject = app(AuthenticatedSubjectHttpResolver::class)->resolve($weakRequest);
        $authorizationRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.step_up_recovery_ok',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        try {
            app(AuthorizationGate::class)->authorize($authorizationRequest);
            $this->fail('step_up_required attendu avec une session faible.');
        } catch (StepUpRequiredException $firstAttempt) {
            $this->assertSame('session_assurance_insufficient', $firstAttempt->result->reason->code);
        }

        // Renforcement réel de la session, tel que Laravel le constaterait
        // lui-même après une reconfirmation de mot de passe.
        $sessionStore->put('auth.password_confirmed_at', time());

        // Nouvelle requête HTTP simulée : résolution entièrement fraîche du
        // sujet, jamais la réutilisation de $firstAttempt.
        $strongRequest = Request::create('/');
        $strongRequest->setLaravelSession($sessionStore);
        $strongRequest->setUserResolver(fn () => $user);

        $freshSubject = app(AuthenticatedSubjectHttpResolver::class)->resolve($strongRequest);
        $this->assertSame(SessionAssurance::Strong, $freshSubject->assurance->sessionAssurance);

        $freshAuthorizationRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $freshSubject,
            capabilityKey: 'sample.step_up_recovery_ok',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        $result = app(AuthorizationGate::class)->evaluate($freshAuthorizationRequest);

        $this->assertSame('allowed', $result->decision->value);
    }

    /**
     * Même scénario, mais le droit est révoqué entre la première tentative
     * et le renforcement de session : si l'ancien résultat pouvait être
     * rejoué comme preuve, ce test verrait `allowed` malgré la révocation.
     * Seule une réévaluation fraîche et complète observe la révocation.
     */
    public function test_a_revoked_grant_is_never_masked_by_a_previous_step_up_attempt(): void
    {
        $user = $this->makeUser('stepup-recovery-revoked@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.step_up_recovery_revoked', minimumSessionAssurance: SessionAssurance::Strong);
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $sessionStore = $this->app['session.store'];

        $weakRequest = Request::create('/');
        $weakRequest->setLaravelSession($sessionStore);
        $weakRequest->setUserResolver(fn () => $user);

        $subject = app(AuthenticatedSubjectHttpResolver::class)->resolve($weakRequest);
        $authorizationRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.step_up_recovery_revoked',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        try {
            app(AuthorizationGate::class)->authorize($authorizationRequest);
            $this->fail('step_up_required attendu avec une session faible.');
        } catch (StepUpRequiredException) {
            // Résultat volontairement mis de côté : ce test démontre
            // précisément qu'il ne doit jamais être réutilisé.
        }

        // Le droit disparaît avant que la session ne soit renforcée.
        app(GrantManager::class)->revoke($grant, $link, 'test', (string) Str::uuid());

        $sessionStore->put('auth.password_confirmed_at', time());

        $strongRequest = Request::create('/');
        $strongRequest->setLaravelSession($sessionStore);
        $strongRequest->setUserResolver(fn () => $user);

        $freshSubject = app(AuthenticatedSubjectHttpResolver::class)->resolve($strongRequest);
        $freshAuthorizationRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $freshSubject,
            capabilityKey: 'sample.step_up_recovery_revoked',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        $result = app(AuthorizationGate::class)->evaluate($freshAuthorizationRequest);

        $this->assertSame('denied', $result->decision->value);
    }

    private function respondToStepUp(AuthorizationRequest $request): JsonResponse
    {
        try {
            app(AuthorizationGate::class)->authorize($request);
            $this->fail('step_up_required attendu.');
        } catch (StepUpRequiredException $exception) {
            return app(AuthorizationFailureResponder::class)->forOutcome($exception);
        }
    }
}
