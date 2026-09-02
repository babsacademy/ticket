<?php

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
    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('a non-scanner token is rejected', function () {
    Sanctum::actingAs(User::factory()->organizer()->create(), ['*']);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertForbidden();
});

test('it lists published events happening today or later that the scanner is assigned to, with their paid ticket count', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->published()->create([
        'title' => 'Dakar Jazz Festival',
        'venue' => 'Théâtre National Daniel Sorano',
        'city' => 'Dakar',
        'date' => now()->addWeek(),
    ]);
    $this->scanner->assignedEvents()->attach($event);
    $ticketType = TicketType::factory()->for($event)->create();

    $paidOrder = Order::factory()->for($event)->paid()->create();
    Ticket::factory()->for($paidOrder)->for($ticketType)->count(2)->create();

    $pendingOrder = Order::factory()->for($event)->create();
    Ticket::factory()->for($pendingOrder)->for($ticketType)->create();

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()->assertJson([
        'data' => [
            [
                'id' => $event->id,
                'title' => 'Dakar Jazz Festival',
                'venue' => 'Théâtre National Daniel Sorano',
                'city' => 'Dakar',
                'ticket_count' => 2,
            ],
        ],
    ])->assertJsonStructure([
        'data' => [['id', 'title', 'date', 'venue', 'city', 'ticket_count']],
    ]);
});

test('it excludes events the scanner is not assigned to', function () {
    // Regression test (IDOR fix): a scanner token used to see every
    // published event on the platform, not just the ones it's actually
    // meant to work.
    Sanctum::actingAs($this->scanner, ['*']);

    Event::factory()->published()->create(['date' => now()->addWeek()]);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()->assertJsonCount(0, 'data');
});

test('it includes an event happening earlier today', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->published()->create(['date' => now()->startOfDay()]);
    $this->scanner->assignedEvents()->attach($event);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()->assertJsonPath('data.0.id', $event->id);
});

test('it excludes events that already happened', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $event = Event::factory()->published()->create(['date' => now()->subDay()]);
    $this->scanner->assignedEvents()->attach($event);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()->assertJsonCount(0, 'data');
});

test('it excludes non-published events', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $draft = Event::factory()->create(['date' => now()->addWeek()]);
    $cancelled = Event::factory()->cancelled()->create(['date' => now()->addWeek()]);
    $ended = Event::factory()->ended()->create();
    $this->scanner->assignedEvents()->attach([$draft->id, $cancelled->id, $ended->id]);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()->assertJsonCount(0, 'data');
});

test('events are ordered by date, soonest first', function () {
    Sanctum::actingAs($this->scanner, ['*']);

    $later = Event::factory()->published()->create(['date' => now()->addMonth()]);
    $sooner = Event::factory()->published()->create(['date' => now()->addDay()]);
    $this->scanner->assignedEvents()->attach([$later->id, $sooner->id]);

    $response = $this->getJson('/api/v1/scanner/events');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $sooner->id)
        ->assertJsonPath('data.1.id', $later->id);
});
