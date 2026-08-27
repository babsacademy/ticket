<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\QrCodeGenerator;
use App\Services\TicketSignatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateTicketsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Generate one signed ticket per purchased unit, then queue the buyer notification.
     *
     * Ticket types are locked for the duration of the transaction to keep concurrent
     * checkouts from corrupting `sold_count`. Because this job only runs after Wave has
     * confirmed payment, `sold_count` is incremented unconditionally here even if it
     * pushes past `quantity` — refusing to issue a paid-for ticket would be worse than a
     * capacity overrun, which the checkout-time check in CheckoutController is meant to
     * prevent in the first place.
     */
    public function handle(TicketSignatureService $signatureService, QrCodeGenerator $qrGenerator): void
    {
        DB::transaction(function () use ($signatureService, $qrGenerator): void {
            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($this->order->id);

            foreach ($order->items as $item) {
                $ticketType = TicketType::query()->lockForUpdate()->findOrFail($item->ticket_type_id);

                for ($i = 0; $i < $item->quantity; $i++) {
                    $ticket = new Ticket([
                        'order_id' => $order->id,
                        'ticket_type_id' => $ticketType->id,
                        'holder_name' => $order->buyer_name,
                        'holder_email' => null,
                        'qr_payload' => '',
                        'signature' => '',
                    ]);
                    $ticket->save();

                    $ticket->setRelation('ticketType', $ticketType);
                    $ticket->setRelation('order', $order);

                    $qrString = $signatureService->generatePayload($ticket);
                    [$payload, $signature] = explode('.', $qrString, 2);

                    $imagePath = "tickets/{$ticket->id}.png";
                    Storage::disk('public')->put($imagePath, $qrGenerator->toPng($qrString));

                    $ticket->update([
                        'qr_payload' => $payload,
                        'signature' => $signature,
                        'qr_image_path' => $imagePath,
                    ]);
                }

                $ticketType->increment('sold_count', $item->quantity);
            }
        });

        SendTicketNotificationJob::dispatch($this->order);
    }
}
