<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_home_page_renders_the_welcome_component_with_essential_structure(): void
    {
        $response = $this->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null)
        );

        $response->assertSee('<html lang="fr"', false);
        $response->assertSee('<title>Wasplex</title>', false);
        $response->assertSee('name="viewport"', false);
    }
}
