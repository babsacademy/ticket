<?php

/**
 * Consolidated attack-scenario coverage for the ticket scanner API and the
 * guest checkout form. Each scenario here is also covered, at a finer
 * grain, by its own feature test file (TicketVerifyTest, TicketCheckinTest,
 * CheckoutRateLimitTest, Admin/EventControllerTest) — this file exists as a
 * single place that reads as "here's proof these specific attacks fail",
 * for security review rather than day-to-day regression detail.
 */

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketSignatureService;
use Laravel\Sanctum\Sanctum;

function makeSecuritySignedTicket(array $ticketState = []): array
{
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create($ticketState);

    return [$ticket, $event, $ticketType];
}

// 1. Falsified token: a single character changed in an otherwise
// legitimately-issued QR string (as opposed to a wholesale swapped-out
// signature) must still be rejected — proves the HMAC covers the whole
// string, not just the parts an attacker might think to touch.
test('a token with a single character flipped is rejected as an invalid signature', function () {
    $scanner = User::factory()->scanner()->create();
    Sanctum::actingAs($scanner, ['*']);
    [$ticket] = makeSecuritySignedTicket();

    $qrString = (new TicketSignatureService)->generatePayload($ticket);

    $lastChar = substr($qrString, -1);
    $flippedChar = $lastChar === '0' ? '1' : '0';
    $falsified = substr($qrString, 0, -1).$flippedChar;

    expect($falsified)->not->toBe($qrString);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $falsified]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'invalid_signature',
    ]);
});

// 2. Invented token: a string an attacker fabricates from nothing (no real
// payload was ever signed) — never a "valid_signature.wrong_reason" leak,
// always the same generic rejection as any other bad signature.
test('a wholly fabricated token is rejected, not just an unrecognized ticket', function () {
    $scanner = User::factory()->scanner()->create();
    Sanctum::actingAs($scanner, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/verify', [
        'qr_payload' => 'eyJmYWtlIjp0cnVlfQ.0000000000000000000000000000000000000000000000000000000000000000',
    ]);

    $response->assertOk()->assertExactJson([
        'valid' => false,
        'reason' => 'invalid_signature',
    ]);
});

// 3. Double scan: re-presenting an already-checked-in ticket's QR must
// never grant a second entry, whether at /verify (informational) or
// /checkin (the actual gate that records entry).
test('a double scan is refused at both the verify and check-in endpoints', function () {
    $scanner = User::factory()->scanner()->create();
    Sanctum::actingAs($scanner, ['*']);
    [$ticket] = makeSecuritySignedTicket();
    $qrString = (new TicketSignatureService)->generatePayload($ticket);

    $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $ticket->id])
        ->assertOk();

    $replayedVerify = $this->postJson('/api/v1/scanner/tickets/verify', ['qr_payload' => $qrString]);
    $replayedVerify->assertOk()->assertJson(['valid' => false, 'reason' => 'already_scanned']);

    $replayedCheckin = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $ticket->id]);
    $replayedCheckin->assertStatus(409);

    expect(Ticket::find($ticket->id)->scanned_by)->toBe($scanner->id);
});

// 4. No Bearer token: every scanner endpoint that touches ticket or event
// data must refuse an anonymous caller outright, before any business logic
// (including route-model binding on a made-up ID) runs.
test('every protected scanner endpoint rejects a request with no Bearer token', function (string $method, string $uri) {
    $response = $this->json($method, $uri, $method === 'POST' ? ['ticket_id' => 1, 'qr_payload' => 'x'] : []);

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
})->with([
    'GET /events' => ['GET', '/api/v1/scanner/events'],
    'GET /events/{event}/tickets' => ['GET', '/api/v1/scanner/events/999999/tickets'],
    'POST /tickets/verify' => ['POST', '/api/v1/scanner/tickets/verify'],
    'POST /tickets/checkin' => ['POST', '/api/v1/scanner/tickets/checkin'],
]);

// 5. Dashboard access with the scanner role: per CLAUDE.md's role table, a
// scanner account exists solely to authenticate against the Flutter app —
// it must never reach the admin back-office, regardless of how it got a
// web session.
test('a scanner account cannot reach the admin back-office', function () {
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)->get(route('admin.events.index'))->assertForbidden();
    $this->actingAs($scanner)->get(route('admin.events.create'))->assertForbidden();
});

// 7. Scan state on download: only authenticated scanners may read
// is_scanned/scanned_at, and the response must never leak scanned_by
// (which scanner checked the ticket in).
test('ticket download scan state is only exposed to authenticated scanners and omits scanned_by', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $paidOrder = Order::factory()->for($event)->paid()->create();
    $scannerWhoChecked = User::factory()->scanner()->create();
    $scannedAt = now()->subMinutes(10);

    Ticket::factory()->for($paidOrder)->for($ticketType)->create([
        'scanned_at' => $scannedAt,
        'scanned_by' => $scannerWhoChecked->id,
    ]);

    $anonymous = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");
    $anonymous->assertUnauthorized();

    Sanctum::actingAs(User::factory()->organizer()->create(), ['*']);
    $wrongRole = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");
    $wrongRole->assertForbidden();

    $scanner = User::factory()->scanner()->create();
    Sanctum::actingAs($scanner, ['*']);
    $authorized = $this->getJson("/api/v1/scanner/events/{$event->id}/tickets");

    $authorized->assertOk()
        ->assertJsonPath('data.0.is_scanned', true)
        ->assertJsonPath('data.0.scanned_at', $scannedAt->toIso8601String());

    expect($authorized->json('data.0'))->not->toHaveKey('scanned_by');
});

// 6. Checkout rate limiting: a scripted burst of submissions past the
// documented 5-per-minute limit must be throttled, not merely slowed down.
test('a burst of checkout submissions past the limit is throttled with 429', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('checkout.store', $event), []);
    }

    $sixthAttempt = $this->post(route('checkout.store', $event), []);

    $sixthAttempt->assertStatus(429);
    expect(Order::query()->where('event_id', $event->id)->where('status', OrderStatus::Paid)->count())->toBe(0);
});
