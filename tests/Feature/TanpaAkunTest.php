<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TanpaAkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_and_old_routes_are_gone(): void
    {
        $this->get('/')->assertOk();
        $this->assertDatabaseCount('users', 0);
        $this->get('/dashboard')->assertRedirect('/');
        foreach (['login', 'register', 'profile', 'kamus', 'game', 'scan-benda', 'onboarding'] as $path) {
            $this->get('/'.$path)->assertNotFound();
        }
    }
}
