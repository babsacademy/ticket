<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->scanner = User::factory()->scanner()->create();
});

test('an unauthenticated request is rejected', function () {
    $event = Event::factory()->create();

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('a non-scanner token is rejected', function () {
    Sanctum::actingAs(User::factory()->organizer()->create(), ['*']);

    $event = Event::factory()->create();

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertForbidden();
});

test('an unknown event returns a 404', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $response = $this->getJson('/api/v1/scanner/events/999999/tickets');

    $response->assertNotFound();
});

test('it lists only the tickets belonging to paid orders for that event', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);

    $paidOrder = Order::factory()->for($event)->paid()->create();
    $paidTicket = Ticket::factory()->for($paidOrder)->for($ticketType)->create([
        'holder_name' => 'Fatou Sow',
    ]);

    $pendingOrder = Order::factory()->for($event)->create(['status' => OrderStatus::Pending]);
    Ticket::factory()->for($pendingOrder)->for($ticketType)->create();

    $refundedOrder = Order::factory()->for($event)->refunded()->create();
    Ticket::factory()->for($refundedOrder)->for($ticketType)->create();

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                [
                    'id' => $paidTicket->id,
                    'token' => $paidTicket->qr_payload,
                    'holder_name' => 'Fatou Sow',
                    'ticket_type' => 'VIP',
                ],
            ],
        ]);
});

test('it excludes tickets belonging to other events', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();

    $otherTicketType = TicketType::factory()->for($otherEvent)->create();
    $otherOrder = Order::factory()->for($otherEvent)->paid()->create();
    Ticket::factory()->for($otherOrder)->for($otherTicketType)->create();

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertOk()->assertJsonCount(0, 'data');
});
