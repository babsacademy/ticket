<?php

use App\Mail\TicketOrderMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Facades\Mail;

test('it has a subject mentioning the event', function () {
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $order = Order::factory()->for($event)->create();

    $mail = new TicketOrderMail($order);

    $mail->assertHasSubject('Vos billets pour Dakar Jazz Festival');
});

test('it renders the order summary and a link to download the tickets', function () {
    $event = Event::factory()->create([
        'title' => 'Dakar Jazz Festival',
        'venue' => 'Théâtre National Daniel Sorano',
        'city' => 'Dakar',
    ]);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP', 'price' => 15000]);
    $order = Order::factory()->for($event)->create([
        'buyer_name' => 'Fatou Sow',
        'total_amount' => 15000,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
        'unit_price' => 15000,
    ]);

    $mail = new TicketOrderMail($order->fresh());

    $mail->assertSeeInHtml('Fatou Sow')
        ->assertSeeInHtml('Dakar Jazz Festival')
        ->assertSeeInHtml('Théâtre National Daniel Sorano')
        ->assertSeeInHtml('VIP')
        ->assertSeeInHtml('Télécharger mes billets')
        ->assertSeeInHtml(route('checkout.ticket-pdf', $order));
});

test('it is sent to the buyer email address', function () {
    Mail::fake();

    $event = Event::factory()->create();
    $order = Order::factory()->for($event)->create(['buyer_email' => 'fatou@example.com']);

    Mail::to($order->buyer_email)->send(new TicketOrderMail($order));

    Mail::assertSent(
        TicketOrderMail::class,
        fn (TicketOrderMail $mail) => $mail->hasTo('fatou@example.com') && $mail->order->is($order),
    );
});
