<?php

namespace App\Jobs;

use App\Mail\TicketOrderMail;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\TwilioNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTicketNotificationJob implements ShouldQueue
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
     * Notify the buyer that their tickets are ready. Prefers email (with a
     * "Télécharger mes billets" link) when a buyer_email was provided;
     * otherwise falls back to a text WhatsApp message per ticket, and to
     * SMS when the WhatsApp send fails. No QR image is attached to either —
     * the QR only ever exists rendered on demand into the PDF
     * (CheckoutController::ticketPdf()), never as a standalone file
     * anywhere a notification could link to.
     */
    public function handle(TwilioNotifier $notifier): void
    {
        $order = $this->order->loadMissing(['event', 'items.ticketType', 'tickets.ticketType']);

        if (filled($order->buyer_email)) {
            $this->sendEmail($order);

            return;
        }

        $this->sendWhatsAppOrSms($order, $notifier);
    }

    /**
     * Email the order summary and a link to download the tickets PDF.
     */
    private function sendEmail(Order $order): void
    {
        try {
            Mail::to($order->buyer_email)->send(new TicketOrderMail($order));
        } catch (Throwable $exception) {
            Log::error('Impossible d\'envoyer l\'e-mail de billets à l\'acheteur.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notify the buyer of each ticket via WhatsApp, falling back to SMS per ticket.
     */
    private function sendWhatsAppOrSms(Order $order, TwilioNotifier $notifier): void
    {
        if (blank($order->buyer_phone)) {
            return;
        }

        /** @var Ticket $ticket */
        foreach ($order->tickets as $ticket) {
            $message = sprintf(
                'Votre billet %s pour "%s" est confirmé. Présentez ce code à l\'entrée : %s.',
                $ticket->ticketType->name,
                $order->event->title,
                $ticket->signature,
            );

            $sent = $notifier->sendWhatsApp($order->buyer_phone, $message);

            if (! $sent) {
                $sent = $notifier->sendSms($order->buyer_phone, $message);
            }

            if (! $sent) {
                Log::error('Impossible de notifier l\'acheteur pour un billet.', [
                    'order_id' => $order->id,
                    'ticket_id' => $ticket->id,
                ]);
            }
        }
    }
}
