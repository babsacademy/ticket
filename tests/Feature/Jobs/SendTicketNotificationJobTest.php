<?php

use App\Jobs\SendTicketNotificationJob;
use App\Mail\TicketOrderMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\TwilioNotifier;
use Illuminate\Support\Facades\Mail;

test('it emails the buyer and skips WhatsApp/SMS when buyer_email is set', function () {
    Mail::fake();

    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create([
        'buyer_email' => 'fatou@example.com',
        'buyer_phone' => '+221771234567',
    ]);
    Ticket::factory()->for($order)->for($ticketType)->create();

    $this->mock(TwilioNotifier::class, function ($mock) {
        $mock->shouldNotReceive('sendWhatsApp');
        $mock->shouldNotReceive('sendSms');
    });

    SendTicketNotificationJob::dispatchSync($order);

    Mail::assertSent(
        TicketOrderMail::class,
        fn (TicketOrderMail $mail) => $mail->hasTo('fatou@example.com') && $mail->order->is($order),
    );
});

test('it sends a WhatsApp text message for each ticket, with no image attachment', function () {
    // Regression test: the WhatsApp send used to attach a QR image read
    // from the public disk (qr_image_path) — that storage is gone (see
    // GenerateTicketsJob), so this must still send a plain text message
    // rather than erroring or silently dropping the notification.
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->create(['buyer_phone' => '+221771234567']);
    Ticket::factory()->for($order)->for($ticketType)->count(2)->create();

    $this->mock(TwilioNotifier::class, function ($mock) {
        $mock->shouldReceive('sendWhatsApp')
            ->twice()
            ->with('+221771234567', Mockery::type('string'))
            ->andReturn(true);
        $mock->shouldNotReceive('sendSms');
    });

    SendTicketNotificationJob::dispatchSync($order);
});

test('it falls back to SMS when the WhatsApp send fails', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create(['buyer_phone' => '+221771234567']);
    Ticket::factory()->for($order)->for($ticketType)->create();

    $this->mock(TwilioNotifier::class, function ($mock) {
        $mock->shouldReceive('sendWhatsApp')->once()->andReturn(false);
        $mock->shouldReceive('sendSms')
            ->once()
            ->with('+221771234567', Mockery::type('string'))
            ->andReturn(true);
    });

    SendTicketNotificationJob::dispatchSync($order);
});

test('it does nothing when the order has no buyer phone', function () {
    $event = Event::factory()->create();
    $order = Order::factory()->for($event)->create(['buyer_phone' => null]);

    $this->mock(TwilioNotifier::class, function ($mock) {
        $mock->shouldNotReceive('sendWhatsApp');
        $mock->shouldNotReceive('sendSms');
    });

    SendTicketNotificationJob::dispatchSync($order);
});
