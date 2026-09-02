<?php

use App\Providers\AppServiceProvider;

/**
 * validateTicketSecretInProduction() is protected (matching the rest of
 * this provider's boot-configuration methods, none of which are public) —
 * invoked here via reflection rather than changing its visibility just for
 * testability.
 */
function invokeTicketSecretValidation(): void
{
    $provider = new AppServiceProvider(app());
    $method = new ReflectionMethod($provider, 'validateTicketSecretInProduction');
    $method->invoke($provider);
}

test('it does nothing outside production, even with an empty secret', function () {
    app()->instance('env', 'local');
    config(['tickets.secret' => '']);

    invokeTicketSecretValidation();
})->throwsNoExceptions();

test('it throws in production when APP_TICKET_SECRET is empty', function () {
    app()->instance('env', 'production');
    config(['tickets.secret' => '']);

    invokeTicketSecretValidation();
})->throws(RuntimeException::class, 'APP_TICKET_SECRET');

test('it throws in production when APP_TICKET_SECRET is shorter than 32 characters', function () {
    app()->instance('env', 'production');
    config(['tickets.secret' => str_repeat('a', 31)]);

    invokeTicketSecretValidation();
})->throws(RuntimeException::class);

test('it does nothing in production when APP_TICKET_SECRET is at least 32 characters', function () {
    app()->instance('env', 'production');
    config(['tickets.secret' => str_repeat('a', 32)]);

    invokeTicketSecretValidation();
})->throwsNoExceptions();
