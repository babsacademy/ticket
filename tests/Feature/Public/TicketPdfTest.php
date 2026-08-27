<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\Storage;

test('it downloads a PDF containing the order tickets', function () {
    Storage::fake('public');

    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->paid()->create(['buyer_name' => 'Fatou Sow']);

    Ticket::factory()->for($order)->for($ticketType)->create([
        'qr_image_path' => 'tickets/1.png',
    ]);
    Storage::disk('public')->put('tickets/1.png', 'fake-png-bytes');

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('it renders even when a ticket has no QR image yet', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->paid()->create();

    Ticket::factory()->for($order)->for($ticketType)->create(['qr_image_path' => null]);

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();
});

test('it returns a 404 when the order has no tickets yet', function () {
    $order = Order::factory()->for(Event::factory())->create();

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertNotFound();
});

test('an unknown confirmation token returns a 404', function () {
    $this->get('/checkout/not-a-real-token/ticket-pdf')->assertNotFound();
});
