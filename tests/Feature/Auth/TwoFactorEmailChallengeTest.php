<?php

use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Logs in as the given user and returns the plaintext OTP code sent by
 * email — capturing it is the only way to know it, since it's never
 * exposed anywhere else (only its hash is persisted).
 */
function loginAndCaptureEmailCode(User $user): string
{
    Mail::fake();

    test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = null;
    Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('an admin login is redirected to the email two-factor challenge instead of being logged in', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();

    $response = test()->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.show'));
    $response->assertSessionHas('login.id', $admin->id);
    test()->assertGuest();

    Mail::assertSent(
        TwoFactorCodeMail::class,
        fn (TwoFactorCodeMail $mail): bool => $mail->hasTo($admin->email),
    );
});

test('scanner and organizer logins are not challenged and are logged in immediately', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $response = test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    test()->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
})->with(['organizer', 'scanner']);

test('the challenge page redirects to login when no login is in progress', function () {
    $response = test()->get(route('two-factor.show'));

    $response->assertRedirect(route('login'));
});

test('the challenge page renders once an admin login is in progress', function () {
    loginAndCaptureEmailCode(User::factory()->admin()->create());

    test()->get(route('two-factor.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/two-factor'));
});

test('a correct, unexpired code logs the admin in', function () {
    $admin = User::factory()->admin()->create();
    $code = loginAndCaptureEmailCode($admin);

    $response = test()->post(route('two-factor.verify'), ['code' => $code]);

    test()->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('an incorrect code shows an error and does not log the admin in', function () {
    $admin = User::factory()->admin()->create();
    loginAndCaptureEmailCode($admin);

    $response = test()->post(route('two-factor.verify'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    test()->assertGuest();
});

test('an expired code shows the expiry message and does not log the admin in', function () {
    $admin = User::factory()->admin()->create();
    $code = loginAndCaptureEmailCode($admin);

    TwoFactorCode::query()->where('user_id', $admin->id)->update(['expires_at' => now()->subMinute()]);

    $response = test()->post(route('two-factor.verify'), ['code' => $code]);

    $response->assertSessionHasErrors([
        'code' => 'Code expiré, cliquez sur « Renvoyer le code ».',
    ]);
    test()->assertGuest();
});

test('resending replaces the pending code with a fresh one', function () {
    $admin = User::factory()->admin()->create();
    $firstCode = loginAndCaptureEmailCode($admin);

    Mail::fake();
    test()->post(route('two-factor.resend'));

    $secondCode = null;
    Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$secondCode): bool {
        $secondCode = $mail->code;

        return true;
    });

    expect($secondCode)->not->toBe($firstCode);

    // The old code no longer works...
    test()->post(route('two-factor.verify'), ['code' => $firstCode])
        ->assertSessionHasErrors('code');
    test()->assertGuest();

    // ...but the new one does.
    test()->post(route('two-factor.verify'), ['code' => $secondCode]);
    test()->assertAuthenticatedAs($admin);
});

test('a missing code is rejected by validation', function () {
    $admin = User::factory()->admin()->create();
    loginAndCaptureEmailCode($admin);

    $response = test()->post(route('two-factor.verify'), []);

    $response->assertSessionHasErrors('code');
});
