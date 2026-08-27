<?php

use App\Models\Event;
use App\Models\TicketType;
use Inertia\Testing\AssertableInertia as Assert;

test('it lists published upcoming events with their minimum ticket price', function () {
    $event = Event::factory()->published()->create([
        'title' => 'Dakar Jazz Festival',
        'date' => now()->addWeek(),
    ]);
    TicketType::factory()->for($event)->create(['price' => 10000]);
    TicketType::factory()->for($event)->create(['price' => 5000]);

    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/home')
        ->has('events', 1)
        ->where('events.0.id', $event->id)
        ->where('events.0.title', 'Dakar Jazz Festival')
        ->where('events.0.price_from', 5000)
    );
});

test('an event without ticket types has a null price_from', function () {
    Event::factory()->published()->create(['date' => now()->addWeek()]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('events.0.price_from', null)
    );
});

test('it excludes events that already happened', function () {
    Event::factory()->published()->create(['date' => now()->subDay()]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page->has('events', 0));
});

test('it excludes draft, cancelled, and ended events', function () {
    Event::factory()->create(['date' => now()->addWeek()]);
    Event::factory()->cancelled()->create(['date' => now()->addWeek()]);
    Event::factory()->ended()->create();

    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page->has('events', 0));
});

test('events are ordered by date, soonest first', function () {
    $later = Event::factory()->published()->create(['date' => now()->addMonth()]);
    $sooner = Event::factory()->published()->create(['date' => now()->addDay()]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('events.0.id', $sooner->id)
        ->where('events.1.id', $later->id)
    );
});
