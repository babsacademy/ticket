<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketSignatureService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->service = new TicketSignatureService;
    $this->scanner = User::factory()->scanner()->create();
});

function makeSignedTicket(array $ticketState = [], array $eventState = []): array
{
    $event = Event::factory()->published()->create($eventState);
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create($ticketState);

    return [$ticket, $event, $ticketType];
}

test('an unauthenticated request is rejected', function () {
    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => 'anything']);

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('a non-scanner token is rejected', function () {
    $organizer = User::factory()->organizer()->create();
    Sanctum::actingAs($organizer, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => 'anything']);

    $response->assertForbidden();
});

test('a missing qr_payload returns the documented validation error', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', []);

    $response->assertStatus(422)->assertJson([
        'message' => 'Le champ qr_payload est obligatoire.',
        'errors' => ['qr_payload' => ['Le champ qr_payload est obligatoire.']],
    ]);
});

test('a tampered signature is reported as invalid', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    [$ticket] = makeSignedTicket();

    $qrString = $this->service->generatePayload($ticket);
    [$payload] = explode('.', $qrString, 2);
    $tampered = $payload.'.'.str_repeat('0', 64);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $tampered]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'invalid_signature',
    ]);
});

test('a well-signed but deleted ticket is reported as not found', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    [$ticket] = makeSignedTicket();

    $qrString = $this->service->generatePayload($ticket);
    $ticket->delete();

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'ticket_not_found',
    ]);
});

test('a ticket for an ended event is reported as event_ended', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    $event = Event::factory()->ended()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create();

    $qrString = $this->service->generatePayload($ticket);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'event_ended',
    ]);
});

test('a ticket for a cancelled event is reported as event_ended', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    $event = Event::factory()->cancelled()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create();

    $qrString = $this->service->generatePayload($ticket);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'event_ended',
    ]);
});

test('an already-scanned ticket on an active event is reported as already_scanned', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    [$ticket] = makeSignedTicket(ticketState: ['scanned_at' => now(), 'scanned_by' => User::factory()->scanner()]);

    $qrString = $this->service->generatePayload($ticket);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);

    $response->assertOk()
        ->assertJson(['valid' => false, 'reason' => 'already_scanned'])
        ->assertJsonStructure(['valid', 'reason', 'scanned_at']);
});

test('a fresh ticket on a published event is reported as valid with the documented shape', function () {
    Sanctum::actingAs($this->scanner, ['*']);
    [$ticket, $event, $ticketType] = makeSignedTicket();

    $qrString = $this->service->generatePayload($ticket);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);

    $response->assertOk()->assertJson([
        'valid' => true,
        'ticket' => [
            'id' => $ticket->id,
            'holder_name' => $ticket->holder_name,
            'holder_email' => $ticket->holder_email,
            'ticket_type' => $ticketType->name,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'venue' => $event->venue,
            ],
            'scanned_at' => null,
        ],
    ]);
});
