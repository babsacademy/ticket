<?php

use App\Models\User;

test('a scanner is redirected to login, with an error message, from the dashboard', function () {
    $scanner = User::factory()->scanner()->create();

    $response = $this->actingAs($scanner)->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('a scanner is actually logged out, not just redirected, avoiding a redirect loop', function () {
    // If the scanner stayed authenticated, the guest-only /login route
    // would immediately bounce them straight back to /dashboard — an
    // infinite loop. Logging them out first is what breaks it.
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)->get(route('dashboard'));

    $this->assertGuest();
    $this->get(route('login'))->assertOk();
});

test('a scanner is redirected away from settings routes too', function () {
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('admin and organizer accounts are unaffected', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $this->assertAuthenticatedAs($user);
})->with(['admin', 'organizer']);
