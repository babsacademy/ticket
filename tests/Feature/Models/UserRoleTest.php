<?php

use App\Enums\UserRole;
use App\Models\User;

test('role is cast to the UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('users default to the organizer role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Organizer);
});

test('factory role states assign the expected role', function () {
    expect(User::factory()->admin()->create()->role)->toBe(UserRole::Admin)
        ->and(User::factory()->organizer()->create()->role)->toBe(UserRole::Organizer)
        ->and(User::factory()->scanner()->create()->role)->toBe(UserRole::Scanner);
});
