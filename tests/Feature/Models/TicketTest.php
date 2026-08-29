<?php

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;

test('a ticket belongs to an order and a ticket type', function () {
    $order = Order::factory()->create();
    $ticketType = TicketType::factory()->create();
    $ticket = Ticket::factory()->for($order)->for($ticketType)->create();

    expect($ticket->order->id)->toBe($order->id)
        ->and($ticket->ticketType->id)->toBe($ticketType->id);
});

test('a fresh ticket has not been scanned', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->scanned_at)->toBeNull()
        ->and($ticket->scanned_by)->toBeNull()
        ->and($ticket->scannedBy)->toBeNull();
});

test('the scanned state marks the ticket as checked in by a scanner', function () {
    $ticket = Ticket::factory()->scanned()->create();

    expect($ticket->scanned_at)->not->toBeNull()
        ->and($ticket->scannedBy)->toBeInstanceOf(User::class)
        ->and($ticket->scannedBy->role)->toBe(UserRole::Scanner);
});

test('qr payload and signature are generated for every ticket', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->qr_payload)->not->toBeEmpty()
        ->and($ticket->signature)->not->toBeEmpty();
});

test('fullToken combines qr_payload and signature with a dot, matching what verifySignature expects', function () {
    $ticket = Ticket::factory()->create([
        'qr_payload' => 'cGF5bG9hZA==',
        'signature' => 'abc123',
    ]);

    expect($ticket->fullToken())->toBe('cGF5bG9hZA==.abc123');
});
