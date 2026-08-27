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

test('it sends a WhatsApp message with the QR image for each ticket', function () {
    $event = Event::factory()->create(['title' => 'Dakar Jazz Festival']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    $order = Order::factory()->for($event)->create(['buyer_phone' => '+221771234567']);
    Ticket::factory()->for($order)->for($ticketType)->count(2)->create(['qr_image_path' => 'tickets/abc.png']);

    $this->mock(TwilioNotifier::class, function ($mock) {
        $mock->shouldReceive('sendWhatsApp')
            ->twice()
            ->with('+221771234567', Mockery::type('string'), Mockery::type('string'))
            ->andReturn(true);
        $mock->shouldNotReceive('sendSms');
    });

    SendTicketNotificationJob::dispatchSync($order);
});

test('it falls back to SMS when the WhatsApp send fails', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $order = Order::factory()->for($event)->create(['buyer_phone' => '+221771234567']);
    Ticket::factory()->for($order)->for($ticketType)->create(['qr_image_path' => 'tickets/abc.png']);

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
