<?php

use App\Models\Event;
use App\Models\TicketType;
use Inertia\Testing\AssertableInertia as Assert;

test('a published event is publicly viewable with its ticket types', function () {
    $event = Event::factory()->published()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create([
        'name' => 'Standard',
        'price' => 5000,
        'quantity' => 100,
        'sold_count' => 20,
    ]);

    $response = $this->get(route('events.show', $event));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/events/show')
        ->where('event.id', $event->id)
        ->where('event.slug', $event->slug)
        ->has('ticketTypes', 1)
        ->where('ticketTypes.0.id', $ticketType->id)
        ->where('ticketTypes.0.remaining', 80)
    );
});

test('a draft event is not publicly accessible', function () {
    $event = Event::factory()->create();

    $this->get(route('events.show', $event))->assertNotFound();
});

test('an unknown event slug returns a 404', function () {
    $this->get('/events/does-not-exist')->assertNotFound();
});

test('a sold out ticket type reports zero remaining, never negative', function () {
    $event = Event::factory()->published()->create();
    TicketType::factory()->for($event)->create(['quantity' => 10, 'sold_count' => 15]);

    $response = $this->get(route('events.show', $event));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('ticketTypes.0.remaining', 0)
    );
});
