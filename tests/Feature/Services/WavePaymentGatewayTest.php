<?php

use App\Models\Event;
use App\Models\Order;
use App\Services\WavePaymentGateway;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.wave.secret_key' => 'wave-secret-key']);
    config(['services.wave.webhook_secret' => 'wave-webhook-secret-at-least-32-characters-long']);

    $this->gateway = new WavePaymentGateway;
});

test('initiate creates a Wave checkout session and returns its launch URL', function () {
    Http::fake([
        'api.wave.com/v1/checkout/sessions' => Http::response([
            'id' => 'cos-123456',
            'wave_launch_url' => 'https://pay.wave.com/c/cos-123456',
        ], 200),
    ]);

    $order = Order::factory()->for(Event::factory())->create();

    $result = $this->gateway->initiate($order, 'https://app.test/success', 'https://app.test/error');

    expect($result)->toBe([
        'session_id' => 'cos-123456',
        'launch_url' => 'https://pay.wave.com/c/cos-123456',
    ]);

    Http::assertSent(function ($request) use ($order) {
        return $request->url() === 'https://api.wave.com/v1/checkout/sessions'
            && $request->hasHeader('Authorization', 'Bearer wave-secret-key')
            && $request['client_reference'] === (string) $order->id
            && $request['currency'] === 'XOF'
            && $request['success_url'] === 'https://app.test/success'
            && $request['error_url'] === 'https://app.test/error';
    });
});

test('initiate throws when the Wave API responds with an error', function () {
    Http::fake([
        'api.wave.com/v1/checkout/sessions' => Http::response(['message' => 'invalid request'], 422),
    ]);

    $order = Order::factory()->for(Event::factory())->create();

    $this->gateway->initiate($order, 'https://app.test/success', 'https://app.test/error');
})->throws(RuntimeException::class);

test('verifyWebhookSignature accepts a correctly signed, fresh payload', function () {
    $payload = '{"type":"checkout.session.completed"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", 'wave-webhook-secret-at-least-32-characters-long');

    $result = $this->gateway->verifyWebhookSignature($payload, "t={$timestamp},v1={$expected}");

    expect($result)->toBeTrue();
});

test('verifyWebhookSignature rejects a tampered payload', function () {
    $payload = '{"type":"checkout.session.completed"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', "{$timestamp}.".'{"type":"something-else"}', 'wave-webhook-secret-at-least-32-characters-long');

    $result = $this->gateway->verifyWebhookSignature($payload, "t={$timestamp},v1={$expected}");

    expect($result)->toBeFalse();
});

test('verifyWebhookSignature rejects a missing signature header', function () {
    expect($this->gateway->verifyWebhookSignature('{}', null))->toBeFalse();
});

test('verifyWebhookSignature rejects a stale timestamp', function () {
    $payload = '{"type":"checkout.session.completed"}';
    $timestamp = time() - 3600;
    $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", 'wave-webhook-secret-at-least-32-characters-long');

    $result = $this->gateway->verifyWebhookSignature($payload, "t={$timestamp},v1={$expected}");

    expect($result)->toBeFalse();
});
