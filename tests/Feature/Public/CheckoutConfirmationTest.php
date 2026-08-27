<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Inertia\Testing\AssertableInertia as Assert;

test('it shows the order and event summary', function () {
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival', 'venue' => 'Sorano']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Standard', 'price' => 5000]);
    $order = Order::factory()->for($event)->create([
        'buyer_name' => 'Fatou Sow',
        'buyer_phone' => '+221771234567',
        'total_amount' => 10000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'unit_price' => 5000,
    ]);

    $response = $this->get(route('checkout.confirmation', $order));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/checkout/confirmation')
        ->where('order.buyer_name', 'Fatou Sow')
        ->where('order.total_amount', 10000)
        ->where('event.title', 'Dakar Jazz Festival')
        ->has('items', 1)
        ->where('items.0.ticket_type', 'Standard')
        ->where('items.0.quantity', 2)
    );
});

test('it reports no tickets yet when generation has not completed', function () {
    $event = Event::factory()->create();
    $order = Order::factory()->for($event)->create();

    $response = $this->get(route('checkout.confirmation', $order));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('tickets', 0)
    );
});

test('it lists the generated tickets once they exist', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->create();
    Ticket::factory()->for($order)->for($ticketType)->count(2)->create(['holder_name' => 'Fatou Sow']);

    $response = $this->get(route('checkout.confirmation', $order));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('tickets', 2)
        ->where('tickets.0.holder_name', 'Fatou Sow')
        ->where('tickets.0.ticket_type', 'VIP')
    );
});

test('an unknown confirmation token returns a 404', function () {
    $this->get('/checkout/not-a-real-token/confirmation')->assertNotFound();
});
