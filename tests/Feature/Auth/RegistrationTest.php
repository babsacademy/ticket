<?php

use App\Models\User;

test('GET /register redirects to the login page instead of showing a form', function () {
    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', "L'inscription n'est pas ouverte au public.");
});

test('POST /register redirects to the login page instead of creating an account', function () {
    $response = $this->post(route('register'), [
        'name' => 'Attacker',
        'email' => 'attacker@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(User::query()->where('email', 'attacker@example.com')->exists())->toBeFalse();
});
