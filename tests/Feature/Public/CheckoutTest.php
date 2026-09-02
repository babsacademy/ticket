<?php

use App\Enums\OrderStatus;
use App\Jobs\GenerateTicketsJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.wave.secret_key' => 'wave-secret-key',
        'tickets.payment_enabled' => true,
    ]);
});

test('a guest can place an order and is redirected to Wave to pay', function () {
    Http::fake([
        'api.wave.com/v1/checkout/sessions' => Http::response([
            'id' => 'cos-abc123',
            'wave_launch_url' => 'https://pay.wave.com/c/cos-abc123',
        ], 200),
    ]);

    $event = Event::factory()->published()->create();
    $standard = TicketType::factory()->for($event)->create(['name' => 'Standard', 'price' => 5000, 'quantity' => 100, 'sold_count' => 0]);
    $vip = TicketType::factory()->for($event)->create(['name' => 'VIP', 'price' => 15000, 'quantity' => 20, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $standard->id, 'quantity' => 2],
            ['ticket_type_id' => $vip->id, 'quantity' => 1],
        ],
    ]);

    $order = Order::firstOrFail();

    $response->assertRedirect('https://pay.wave.com/c/cos-abc123');

    expect($order->buyer_name)->toBe('Fatou Sow')
        ->and($order->buyer_phone)->toBe('+221771234567')
        ->and($order->event_id)->toBe($event->id)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and((float) $order->total_amount)->toBe(25000.0)
        ->and((float) $order->commission_amount)->toBe(2500.0)
        ->and((float) $order->net_amount)->toBe(22500.0)
        ->and($order->payment_reference)->toBe('cos-abc123')
        ->and($order->items)->toHaveCount(2);
});

test('an order cannot exceed the remaining capacity of a ticket type', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 10, 'sold_count' => 8]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 3],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    expect(Order::count())->toBe(0);
});

test('a ticket type from another event is rejected', function () {
    $event = Event::factory()->published()->create();
    $otherEventTicketType = TicketType::factory()->create();

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $otherEventTicketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.ticket_type_id');
    expect(Order::count())->toBe(0);
});

test('buyer name and phone are required', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $response = $this->post(route('checkout.store', $event), [
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionHasErrors(['buyer_name', 'buyer_phone']);
});

test('at least one ticket item is required', function () {
    $event = Event::factory()->published()->create();

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [],
    ]);

    $response->assertSessionHasErrors('items');
});

test('checkout is not available for an unpublished event', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertNotFound();
});

test('a Wave API failure marks the order as failed and returns an error', function () {
    Http::fake([
        'api.wave.com/v1/checkout/sessions' => Http::response(['message' => 'unavailable'], 500),
    ]);

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionHasErrors('payment');
    expect(Order::firstOrFail()->status)->toBe(OrderStatus::Failed);
});

test('a free order (total of 0) bypasses Wave entirely and goes straight to confirmation', function () {
    Bus::fake();

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ],
    ]);

    $order = Order::firstOrFail();

    $response->assertRedirect(route('checkout.confirmation', $order));
    expect($order->status)->toBe(OrderStatus::Paid);

    Http::assertNothingSent();
    Bus::assertDispatched(GenerateTicketsJob::class, fn (GenerateTicketsJob $job) => $job->order->is($order));
});

test('when PAYMENT_ENABLED is false, a paid order is refused instead of bypassing Wave', function () {
    // Regression test for a real vulnerability: PAYMENT_ENABLED=false used
    // to bypass Wave for ANY order, not just free ones — meaning a
    // misconfigured production deploy would confirm real paid orders
    // without ever charging the buyer.
    Bus::fake();
    config(['tickets.payment_enabled' => false]);

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 5000, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['payment' => 'La vente de billets est temporairement indisponible.']);

    $order = Order::firstOrFail();
    expect($order->status)->toBe(OrderStatus::Failed);

    Http::assertNothingSent();
    Bus::assertNotDispatched(GenerateTicketsJob::class);
});

test('when PAYMENT_ENABLED is false, a genuinely free order still bypasses Wave', function () {
    Bus::fake();
    config(['tickets.payment_enabled' => false]);

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $order = Order::firstOrFail();

    $response->assertRedirect(route('checkout.confirmation', $order));
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and((float) $order->total_amount)->toBe(0.0);

    Http::assertNothingSent();
    Bus::assertDispatched(GenerateTicketsJob::class);
});

test('an order cannot request more than 10 tickets of a single type', function () {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 11],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    expect(Order::count())->toBe(0);
});

test('an order of exactly 10 tickets of a single type is accepted', function () {
    Bus::fake();

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 10],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

test('the 10-per-type limit applies independently to each ticket type', function () {
    Bus::fake();

    $event = Event::factory()->published()->create();
    $standard = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);
    $vip = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'items' => [
            ['ticket_type_id' => $standard->id, 'quantity' => 10],
            ['ticket_type_id' => $vip->id, 'quantity' => 10],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);
});

test('valid Senegalese phone number formats are accepted', function (string $phone) {
    Bus::fake();

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['price' => 0, 'quantity' => 100, 'sold_count' => 0]);

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => $phone,
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('buyer_phone');
})->with([
    'international with +' => '+221773698046',
    'international with 00' => '00221773698046',
    'local format starting with 7' => '773698046',
    'local format starting with 3' => '331234567',
]);

test('invalid phone number formats are rejected', function (string $phone) {
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $response = $this->post(route('checkout.store', $event), [
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => $phone,
        'items' => [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
        ],
    ]);

    $response->assertSessionHasErrors('buyer_phone');
    expect(Order::count())->toBe(0);
})->with([
    'missing country code, local number does not start with 3 or 7' => '0771234567',
    'local number does not start with 3 or 7' => '912345678',
    'local number too short (8 digits — the old, buggy 7\d{7} format)' => '77369804',
    'local number too short (7 digits)' => '7123456',
    'contains letters' => '+221abcdefghi',
    'wrong country code' => '+33612345678',
    'contains spaces' => '+221 77 123 45 67',
]);
