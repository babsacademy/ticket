<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Laravel's sanctum guard is a RequestGuard, which caches the resolved user
// on first use (Illuminate\Auth\RequestGuard::$user) and never re-checks the
// token on a later call to guard()->user() within the same request/guard
// instance. A real production request is always a fresh PHP process/
// container, so this caching never leaks across requests there — but Pest's
// test client reuses the same booted app across every simulated call inside
// one test method, so a second request in the same test would silently
// reuse the FIRST request's resolved user regardless of which token it sent.
// Auth::forgetGuards() forces a fresh RequestGuard (and therefore a real
// token check) before every subsequent authenticated call below.

test('an unauthenticated request is rejected', function () {
    $response = $this->postJson('/api/v1/scanner/logout');

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('logout revokes the current token', function () {
    $scanner = User::factory()->scanner()->create();

    $token = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'password',
    ])->json('token');

    expect($scanner->tokens()->count())->toBe(1);

    $response = $this->withToken($token)->postJson('/api/v1/scanner/logout');

    $response->assertOk()->assertJson(['message' => 'Déconnecté.']);
    expect($scanner->tokens()->count())->toBe(0);

    // The revoked token no longer authenticates anything.
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/v1/scanner/events')->assertUnauthorized();
});

test('logout only revokes the current device, not every token on the account', function () {
    $scanner = User::factory()->scanner()->create();

    $tokenA = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'password',
        'device_name' => 'Device A',
    ])->json('token');

    $tokenB = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'password',
        'device_name' => 'Device B',
    ])->json('token');

    $this->withToken($tokenA)->postJson('/api/v1/scanner/logout')->assertOk();

    Auth::forgetGuards();
    $this->withToken($tokenA)->getJson('/api/v1/scanner/events')->assertUnauthorized();

    Auth::forgetGuards();
    $this->withToken($tokenB)->getJson('/api/v1/scanner/events')->assertOk();
});
