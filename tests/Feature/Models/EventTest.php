<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;

test('an event belongs to its organizer', function () {
    $organizer = User::factory()->organizer()->create();
    $event = Event::factory()->for($organizer, 'organizer')->create();

    expect($event->organizer)->toBeInstanceOf(User::class)
        ->and($event->organizer->id)->toBe($organizer->id);
});

test('status is cast to the EventStatus enum and defaults to draft', function () {
    $event = Event::factory()->create();

    expect($event->status)->toBe(EventStatus::Draft);
});

test('factory status states assign the expected status', function () {
    expect(Event::factory()->published()->create()->status)->toBe(EventStatus::Published)
        ->and(Event::factory()->cancelled()->create()->status)->toBe(EventStatus::Cancelled)
        ->and(Event::factory()->ended()->create()->status)->toBe(EventStatus::Ended);
});

test('an event has many ticket types', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->count(3)->create();

    expect($event->ticketTypes)->toHaveCount(3)
        ->and($event->ticketTypes->first())->toBeInstanceOf(TicketType::class);
});

test('an event has many orders', function () {
    $event = Event::factory()->create();
    Order::factory()->for($event)->count(2)->create();

    expect($event->orders)->toHaveCount(2)
        ->and($event->orders->first())->toBeInstanceOf(Order::class);
});
