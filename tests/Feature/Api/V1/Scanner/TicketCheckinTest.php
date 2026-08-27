<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->scanner = User::factory()->scanner()->create(['name' => 'Mamadou Diallo']);

    $this->event = Event::factory()->published()->create(['title' => 'Dakar Jazz Festival 2026']);
    $ticketType = TicketType::factory()->for($this->event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($this->event)->create();
    $this->ticket = Ticket::factory()->for($order)->for($ticketType)->create(['holder_name' => 'Fatou Sow']);
});

test('an unauthenticated request is rejected', function () {
    $response = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $this->ticket->id]);

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('a non-scanner token is rejected', function () {
    $organizer = User::factory()->organizer()->create();
    Sanctum::actingAs($organizer, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $this->ticket->id]);

    $response->assertForbidden();
});

test('a missing ticket_id returns a validation error', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/checkin', []);

    $response->assertStatus(422)->assertJsonValidationErrors(['ticket_id']);
});

test('an unknown ticket_id returns the documented 404', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => 999999]);

    $response->assertStatus(404)->assertJson(['message' => 'Billet introuvable.']);
});

test('checking in a fresh ticket succeeds with the documented shape and persists the scan', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $response = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $this->ticket->id]);

    $response->assertOk()->assertJson([
        'ticket' => [
            'id' => $this->ticket->id,
            'holder_name' => 'Fatou Sow',
            'ticket_type' => 'VIP',
        ],
        'event' => [
            'id' => $this->event->id,
            'title' => 'Dakar Jazz Festival 2026',
        ],
        'scanned_by' => [
            'id' => $this->scanner->id,
            'name' => 'Mamadou Diallo',
        ],
    ])->assertJsonStructure(['checked_in_at']);

    expect($this->ticket->fresh())
        ->scanned_at->not->toBeNull()
        ->scanned_by->toBe($this->scanner->id);
});

test('checking in the same ticket twice is idempotent: first call succeeds, second call returns 409', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $this->ticket->id])
        ->assertOk();

    $second = $this->postJson('/api/v1/scanner/tickets/checkin', ['ticket_id' => $this->ticket->id]);

    $second->assertStatus(409)->assertJson([
        'message' => 'Ce billet a déjà été scanné.',
        'scanned_by' => 'Mamadou Diallo',
    ])->assertJsonStructure(['message', 'scanned_at', 'scanned_by']);

    // Only proves sequential idempotency; SQLite/single-process Pest cannot
    // exercise true row-lock concurrency for the same ticket.
    expect(Ticket::find($this->ticket->id)->scanned_by)->toBe($this->scanner->id);
});
