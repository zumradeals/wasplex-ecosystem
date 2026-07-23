<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * Le résolveur HTTP réutilisable (P003-B2 §D) produit exactement le même
 * sujet que l'appel direct au résolveur, sans jamais protéger de route par
 * lui-même.
 */
class AuthenticatedSubjectHttpResolverTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_authenticated_subject(): void
    {
        $user = $this->makeUser('http-resolver@example.com');

        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);

        $subject = app(AuthenticatedSubjectHttpResolver::class)->resolve($request);

        $this->assertSame($user->id, $subject->account->id);
    }

    public function test_it_refuses_an_unauthenticated_request(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);

        $this->expectException(SubjectResolutionFailedException::class);

        app(AuthenticatedSubjectHttpResolver::class)->resolve($request);
    }
}
