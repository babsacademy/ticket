<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketSignatureService;
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
                    'token' => $paidTicket->fullToken(),
                    'holder_name' => 'Fatou Sow',
                    'ticket_type' => 'VIP',
                    'is_scanned' => false,
                    'scanned_at' => null,
                ],
            ],
        ]);
});

test('a scanned ticket exposes is_scanned and scanned_at but never scanned_by', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $paidOrder = Order::factory()->for($event)->paid()->create();
    $scannedAt = now()->subHour();
    $scanner = User::factory()->scanner()->create();

    $scannedTicket = Ticket::factory()->for($paidOrder)->for($ticketType)->create([
        'holder_name' => 'Fatou Sow',
        'scanned_at' => $scannedAt,
        'scanned_by' => $scanner->id,
    ]);

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertOk()
        ->assertJson([
            'data' => [
                [
                    'id' => $scannedTicket->id,
                    'is_scanned' => true,
                    'scanned_at' => $scannedAt->toIso8601String(),
                ],
            ],
        ]);

    expect($response->json('data.0'))->not->toHaveKey('scanned_by');
});

test('an unscanned ticket returns is_scanned false and scanned_at null', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $paidOrder = Order::factory()->for($event)->paid()->create();
    Ticket::factory()->for($paidOrder)->for($ticketType)->create([
        'scanned_at' => null,
        'scanned_by' => null,
    ]);

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertOk()
        ->assertJsonPath('data.0.is_scanned', false)
        ->assertJsonPath('data.0.scanned_at', null);
});

test('the downloaded token matches the exact string encoded in the physical QR', function () {
    // Regression test: the offline scanner app matches a scanned QR
    // against this token by exact string equality, so it must be the
    // full "payload.signature" string — not just the payload half — or
    // every offline scan fails against a real, valid ticket. This uses a
    // genuinely signed ticket (not the factory's independently-fake
    // qr_payload/signature) and proves round-trip decodability through
    // the real signature service, exactly like a live QR scan would.
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->paid()->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create();

    $signatureService = app(TicketSignatureService::class);
    $qrString = $signatureService->generatePayload($ticket);
    [$payload, $signature] = explode('.', $qrString, 2);
    $ticket->update(['qr_payload' => $payload, 'signature' => $signature]);

    $response = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $response->assertOk();
    $token = $response->json('data.0.token');

    expect($token)->toBe($qrString);

    $verification = $signatureService->verifySignature($token);
    expect($verification['valid'])->toBeTrue()
        ->and($verification['data']['ticket_id'])->toBe($ticket->id);
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
