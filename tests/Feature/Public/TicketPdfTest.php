<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;

test('it downloads a PDF containing the order tickets', function () {
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->paid()->create(['buyer_name' => 'Fatou Sow']);

    Ticket::factory()->for($order)->for($ticketType)->create();

    $response = $this->get(route('checkout.ticket-pdf', $order));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('it renders the QR code from qr_payload without needing shared storage', function () {
    // Regression test: the web and worker containers don't share a
    // filesystem in production, so the PDF's QR must be regenerated from
    // qr_payload rather than read from qr_image_path/Storage — this must
    // keep working even when no QR image was ever stored on disk.
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
