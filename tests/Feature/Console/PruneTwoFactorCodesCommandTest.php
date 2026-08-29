<?php

use App\Models\TwoFactorCode;
use App\Models\User;

test('it deletes expired codes and leaves active ones untouched', function () {
    $user = User::factory()->admin()->create();
    $expired = TwoFactorCode::factory()->for($user)->expired()->create();
    $active = TwoFactorCode::factory()->for($user)->create();

    test()->artisan('two-factor:prune')->assertExitCode(0);

    expect(TwoFactorCode::query()->find($expired->id))->toBeNull()
        ->and(TwoFactorCode::query()->find($active->id))->not->toBeNull();
});
