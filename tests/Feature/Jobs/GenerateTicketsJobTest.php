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
    Storage::fake('public');
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
        expect($ticket->holder_name)->toBe('Fatou Sow')
            ->and($ticket->qr_image_path)->not->toBeNull();

        $result = $signatureService->verifySignature($ticket->fullToken());

        expect($result['valid'])->toBeTrue()
            ->and($result['data']['ticket_id'])->toBe($ticket->id);

        Storage::disk('public')->assertExists($ticket->qr_image_path);
    });

    expect($standard->fresh()->sold_count)->toBe(2)
        ->and($vip->fresh()->sold_count)->toBe(1);

    Bus::assertDispatched(SendTicketNotificationJob::class, fn (SendTicketNotificationJob $job) => $job->order->is($order));
});
