<?php

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;

test('an order belongs to a user and an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $order = Order::factory()->for($user)->for($event)->create();

    expect($order->user->id)->toBe($user->id)
        ->and($order->event->id)->toBe($event->id);
});

test('an order can be placed as a guest without a user', function () {
    $order = Order::factory()->guest()->create();

    expect($order->user_id)->toBeNull()
        ->and($order->user)->toBeNull();
});

test('the platform commission is 10 percent of the total amount', function () {
    $order = Order::factory()->create();

    expect((float) $order->commission_amount)->toBe(round((float) $order->total_amount * 0.10, 2))
        ->and((float) $order->commission_amount + (float) $order->net_amount)->toBe((float) $order->total_amount);
});

test('status is cast to the OrderStatus enum and defaults to pending', function () {
    $order = Order::factory()->create();

    expect($order->status)->toBe(OrderStatus::Pending);
});

test('factory status states assign the expected status', function () {
    expect(Order::factory()->paid()->create()->status)->toBe(OrderStatus::Paid)
        ->and(Order::factory()->refunded()->create()->status)->toBe(OrderStatus::Refunded)
        ->and(Order::factory()->failed()->create()->status)->toBe(OrderStatus::Failed);
});

test('an order has many tickets', function () {
    $order = Order::factory()->create();
    Ticket::factory()->for($order)->count(2)->create();

    expect($order->tickets)->toHaveCount(2)
        ->and($order->tickets->first())->toBeInstanceOf(Ticket::class);
});
