<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\TicketSignatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

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
     *
     * Deliberately does NOT render a QR PNG to disk: that used to go to the
     * "public" disk (storage/app/public/tickets/{id}.png), reachable by
     * anyone who could guess or enumerate the URL — no authentication, no
     * relation to who actually bought the ticket. The QR is rendered on
     * demand instead, from Ticket::fullToken(), wherever it's actually
     * needed (CheckoutController::ticketPdf()).
     */
    public function handle(TicketSignatureService $signatureService): void
    {
        DB::transaction(function () use ($signatureService): void {
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

                    $ticket->update([
                        'qr_payload' => $payload,
                        'signature' => $signature,
                    ]);
                }

                $ticketType->increment('sold_count', $item->quantity);
            }
        });

        SendTicketNotificationJob::dispatch($this->order);
    }
}
