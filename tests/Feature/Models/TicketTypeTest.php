<?php

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;

test('a ticket type belongs to an event', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    expect($ticketType->event)->toBeInstanceOf(Event::class)
        ->and($ticketType->event->id)->toBe($event->id);
});

test('price is cast to a decimal string', function () {
    $ticketType = TicketType::factory()->create(['price' => 5000]);

    expect($ticketType->fresh()->price)->toBe('5000.00');
});

test('a ticket type has many tickets', function () {
    $ticketType = TicketType::factory()->create();
    Ticket::factory()->for($ticketType)->count(2)->create();

    expect($ticketType->tickets)->toHaveCount(2)
        ->and($ticketType->tickets->first())->toBeInstanceOf(Ticket::class);
});

test('the soldOut state fills the quantity', function () {
    $ticketType = TicketType::factory()->soldOut()->create();

    expect($ticketType->sold_count)->toBe($ticketType->quantity);
});
