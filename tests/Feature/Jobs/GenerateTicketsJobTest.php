<?php

use App\Jobs\GenerateTicketsJob;
use App\Jobs\SendTicketNotificationJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\TicketSignatureService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

test('it generates one signed, verifiable ticket per purchased unit and queues the notification', function () {
    Bus::fake([SendTicketNotificationJob::class]);

    $event = Event::factory()->published()->create();
    $standard = TicketType::factory()->for($event)->create(['quantity' => 100, 'sold_count' => 0]);
    $vip = TicketType::factory()->for($event)->create(['quantity' => 20, 'sold_count' => 0]);

    $order = Order::factory()->for($event)->create(['buyer_name' => 'Fatou Sow']);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $standard->id, 'quantity' => 2, 'unit_price' => $standard->price]);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $vip->id, 'quantity' => 1, 'unit_price' => $vip->price]);

    GenerateTicketsJob::dispatchSync($order);

    expect(Ticket::count())->toBe(3)
        ->and(Ticket::where('ticket_type_id', $standard->id)->count())->toBe(2)
        ->and(Ticket::where('ticket_type_id', $vip->id)->count())->toBe(1);

    $signatureService = app(TicketSignatureService::class);

    Ticket::all()->each(function (Ticket $ticket) use ($signatureService): void {
        expect($ticket->holder_name)->toBe('Fatou Sow');

        $result = $signatureService->verifySignature($ticket->fullToken());

        expect($result['valid'])->toBeTrue()
            ->and($result['data']['ticket_id'])->toBe($ticket->id);
    });

    expect($standard->fresh()->sold_count)->toBe(2)
        ->and($vip->fresh()->sold_count)->toBe(1);

    Bus::assertDispatched(SendTicketNotificationJob::class, fn (SendTicketNotificationJob $job) => $job->order->is($order));
});

test('it never writes a QR image to the public disk', function () {
    // Regression test: qr_image_path (and the public-disk PNG it pointed
    // at) let anyone who could guess/enumerate the URL view another
    // buyer's ticket QR without authentication — the QR must only ever be
    // rendered on demand (CheckoutController::ticketPdf()), never stored.
    Storage::fake('public');
    Bus::fake([SendTicketNotificationJob::class]);

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['quantity' => 10, 'sold_count' => 0]);
    $order = Order::factory()->for($event)->create(['buyer_name' => 'Fatou Sow']);
    OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'unit_price' => $ticketType->price]);

    GenerateTicketsJob::dispatchSync($order);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
