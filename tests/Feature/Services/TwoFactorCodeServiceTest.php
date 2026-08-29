<?php

use App\Models\TwoFactorCode;
use App\Models\User;
use App\Services\TwoFactorCodeService;
use Database\Factories\TwoFactorCodeFactory;
use Illuminate\Support\Facades\Hash;

test('it generates a 6-digit code, stores only its hash, and returns the plaintext', function () {
    $user = User::factory()->admin()->create();

    $code = (new TwoFactorCodeService)->generateFor($user);

    expect($code)->toMatch('/^\d{6}$/');

    $stored = TwoFactorCode::query()->where('user_id', $user->id)->sole();

    expect($stored->code)->not->toBe($code)
        ->and(Hash::check($code, $stored->code))->toBeTrue()
        ->and($stored->expires_at->diffInMinutes(now(), absolute: false))->toBeLessThanOrEqual(10)
        ->and($stored->expires_at->isFuture())->toBeTrue();
});

test('generating a new code invalidates any code already pending for that user', function () {
    $user = User::factory()->admin()->create();
    $service = new TwoFactorCodeService;

    $service->generateFor($user);
    $service->generateFor($user);

    expect(TwoFactorCode::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('a correct, unexpired code verifies successfully and is consumed', function () {
    $user = User::factory()->admin()->create();
    $service = new TwoFactorCodeService;
    $code = $service->generateFor($user);

    $result = $service->verify($user, $code);

    expect($result)->toBe(['valid' => true, 'reason' => null])
        ->and(TwoFactorCode::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('a wrong code is reported as invalid, even though a code is pending', function () {
    $user = User::factory()->admin()->create();
    (new TwoFactorCodeService)->generateFor($user);

    $result = (new TwoFactorCodeService)->verify($user, '000000');

    expect($result)->toBe(['valid' => false, 'reason' => 'invalid']);
});

test('a correct but expired code is reported as expired', function () {
    $user = User::factory()->admin()->create();
    TwoFactorCode::factory()->for($user)->expired()->create();

    $result = (new TwoFactorCodeService)->verify($user, TwoFactorCodeFactory::$plainCode);

    expect($result)->toBe(['valid' => false, 'reason' => 'expired']);
});

test('verifying when no code is pending at all is reported as invalid', function () {
    $user = User::factory()->admin()->create();

    $result = (new TwoFactorCodeService)->verify($user, '123456');

    expect($result)->toBe(['valid' => false, 'reason' => 'invalid']);
});
