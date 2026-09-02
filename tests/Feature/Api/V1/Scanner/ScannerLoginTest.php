<?php

use App\Models\User;

test('a scanner can login and receive a bearer token', function () {
    $scanner = User::factory()->scanner()->create([
        'name' => 'Mamadou Diallo',
        'email' => 'scanner@evenement.sn',
    ]);

    $response = $this->postJson('/api/v1/scanner/login', [
        'email' => 'scanner@evenement.sn',
        'password' => 'password',
        'device_name' => 'Samsung Galaxy A54',
    ]);

    $response->assertOk()
        ->assertJson([
            'scanner' => [
                'id' => $scanner->id,
                'name' => 'Mamadou Diallo',
                'email' => 'scanner@evenement.sn',
            ],
        ])
        ->assertJsonStructure(['token', 'scanner' => ['id', 'name', 'email']]);

    expect($scanner->tokens()->first()->name)->toBe('Samsung Galaxy A54');
});

test('login without a device_name still succeeds and falls back to a default token name', function () {
    $scanner = User::factory()->scanner()->create();

    $response = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'password',
    ]);

    $response->assertOk();

    expect($scanner->tokens()->first()->name)->toBe('scanner-device');
});

test('an incorrect password returns the documented 422 shape', function () {
    $scanner = User::factory()->scanner()->create();

    $response = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)->assertJson([
        'message' => 'Les identifiants fournis sont incorrects.',
        'errors' => [
            'email' => ['Les identifiants fournis sont incorrects.'],
        ],
    ]);
});

test('an unknown email returns the same 422 shape as an incorrect password', function () {
    $response = $this->postJson('/api/v1/scanner/login', [
        'email' => 'unknown@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)->assertJson([
        'message' => 'Les identifiants fournis sont incorrects.',
    ]);
});

test('a non-scanner account is rejected with the same generic 422 shape as a wrong password', function () {
    // A distinct response here (e.g. 403) would let an attacker confirm a
    // guessed credential pair is genuinely valid — just for the wrong
    // role — rather than simply wrong.
    $organizer = User::factory()->organizer()->create();

    $response = $this->postJson('/api/v1/scanner/login', [
        'email' => $organizer->email,
        'password' => 'password',
    ]);

    $response->assertStatus(422)->assertJson([
        'message' => 'Les identifiants fournis sont incorrects.',
        'errors' => [
            'email' => ['Les identifiants fournis sont incorrects.'],
        ],
    ]);
});

test('scanner login is rate limited to 5 attempts per minute per email+IP', function () {
    $scanner = User::factory()->scanner()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/scanner/login', [
            'email' => $scanner->email,
            'password' => 'wrong-password',
        ]);
    }

    $sixthAttempt = $this->postJson('/api/v1/scanner/login', [
        'email' => $scanner->email,
        'password' => 'wrong-password',
    ]);

    $sixthAttempt->assertStatus(429);
});

test('missing credentials return validation errors', function () {
    $response = $this->postJson('/api/v1/scanner/login', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
});
