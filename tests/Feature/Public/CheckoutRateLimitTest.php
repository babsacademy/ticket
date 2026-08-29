<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;

test('the checkout form is rate limited to 5 attempts per minute', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create();

    // Empty bodies are enough: the `throttle` middleware counts every hit
    // before the request ever reaches StoreCheckoutRequest's validation, so
    // these fail validation (302 redirect back) rather than succeed — the
    // limiter doesn't care either way.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('checkout.store', $event), [])->assertStatus(302);
    }

    $response = $this->post(route('checkout.store', $event), []);

    $response->assertStatus(429);
});

test('exceeding the checkout rate limit returns a clear French message', function () {
    $event = Event::factory()->published()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('checkout.store', $event), []);
    }

    $response = $this->post(route('checkout.store', $event), []);

    $response->assertStatus(429);
    $response->assertSee('Trop de tentatives. Veuillez patienter quelques minutes avant de réessayer.', false);
});

test('the checkout rate limit is scoped per IP address', function () {
    $event = Event::factory()->published()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('checkout.store', $event), [], ['REMOTE_ADDR' => '10.0.0.1']);
    }

    // A different IP has its own, untouched bucket.
    $response = $this->post(route('checkout.store', $event), [], ['REMOTE_ADDR' => '10.0.0.2']);

    $response->assertStatus(302);
});

test('the ticket PDF download is rate limited to 10 attempts per minute', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->paid()->create();
    Ticket::factory()->for($order)->for($ticketType)->create();

    for ($i = 0; $i < 10; $i++) {
        $this->get(route('checkout.ticket-pdf', $order));
    }

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertStatus(429);
});
