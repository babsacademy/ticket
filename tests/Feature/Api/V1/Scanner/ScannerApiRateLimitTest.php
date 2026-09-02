<?php

use App\Models\User;

/**
 * A real login, not Sanctum::actingAs() — the scanner-api limiter is keyed
 * by the raw bearer token string (request()->bearerToken()), which
 * Sanctum::actingAs() never actually sends as a header (auth is faked at
 * the application level, bypassing the Authorization header entirely).
 */
function realScannerToken(): string
{
    $scanner = User::factory()->scanner()->create();

    $response = test()->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'password',
    ]);

    return $response->json('token');
}

test('the scanner API is rate limited to 60 requests per minute per token', function () {
    $token = realScannerToken();

    for ($i = 0; $i < 60; $i++) {
        $this->withToken($token)->getJson('/api/v1/scanner/events');
    }

    $response = $this->withToken($token)->getJson('/api/v1/scanner/events');

    $response->assertStatus(429);
});

test('two different scanner tokens each get their own 60-per-minute bucket', function () {
    $first = realScannerToken();

    for ($i = 0; $i < 60; $i++) {
        $this->withToken($first)->getJson('/api/v1/scanner/events');
    }
    $this->withToken($first)->getJson('/api/v1/scanner/events')->assertStatus(429);

    $second = realScannerToken();

    $response = $this->withToken($second)->getJson('/api/v1/scanner/events');

    $response->assertOk();
});
