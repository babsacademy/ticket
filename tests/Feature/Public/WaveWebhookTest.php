<?php

use App\Enums\OrderStatus;
use App\Jobs\GenerateTicketsJob;
use App\Models\Order;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['services.wave.webhook_secret' => 'wave-webhook-secret']);
});

function signedWavePayload(array $data): array
{
    $payload = json_encode($data);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'wave-webhook-secret');

    return [$payload, "t={$timestamp},v1={$signature}"];
}

test('a validly signed completed checkout marks the order as paid and queues ticket generation', function () {
    Bus::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    [$payload, $signatureHeader] = signedWavePayload([
        'type' => 'checkout.session.completed',
        'data' => [
            'checkout_status' => 'complete',
            'client_reference' => (string) $order->id,
        ],
    ]);

    $response = $this->call('POST', route('webhooks.wave'), [], [], [], [
        'HTTP_Wave-Signature' => $signatureHeader,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    Bus::assertDispatched(GenerateTicketsJob::class, fn (GenerateTicketsJob $job) => $job->order->is($order));
});

test('a request with an invalid signature is rejected', function () {
    Bus::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['checkout_status' => 'complete', 'client_reference' => (string) $order->id],
    ]);

    $response = $this->call('POST', route('webhooks.wave'), [], [], [], [
        'HTTP_Wave-Signature' => 't='.time().',v1=not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(401);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    Bus::assertNotDispatched(GenerateTicketsJob::class);
});

test('an unknown order reference returns a 404', function () {
    [$payload, $signatureHeader] = signedWavePayload([
        'type' => 'checkout.session.completed',
        'data' => ['checkout_status' => 'complete', 'client_reference' => '999999'],
    ]);

    $response = $this->call('POST', route('webhooks.wave'), [], [], [], [
        'HTTP_Wave-Signature' => $signatureHeader,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(404);
});

test('an already paid order is not reprocessed', function () {
    Bus::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    [$payload, $signatureHeader] = signedWavePayload([
        'type' => 'checkout.session.completed',
        'data' => ['checkout_status' => 'complete', 'client_reference' => (string) $order->id],
    ]);

    $response = $this->call('POST', route('webhooks.wave'), [], [], [], [
        'HTTP_Wave-Signature' => $signatureHeader,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk();
    Bus::assertNotDispatched(GenerateTicketsJob::class);
});

test('a non-completed checkout status does not mark the order as paid', function () {
    Bus::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    [$payload, $signatureHeader] = signedWavePayload([
        'type' => 'checkout.session.completed',
        'data' => ['checkout_status' => 'expired', 'client_reference' => (string) $order->id],
    ]);

    $response = $this->call('POST', route('webhooks.wave'), [], [], [], [
        'HTTP_Wave-Signature' => $signatureHeader,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    Bus::assertNotDispatched(GenerateTicketsJob::class);
});
