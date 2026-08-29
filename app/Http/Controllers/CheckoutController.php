<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\Public\StoreCheckoutRequest;
use App\Jobs\GenerateTicketsJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\CommissionService;
use App\Services\QrCodeGenerator;
use App\Services\WavePaymentGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CommissionService $commissionService,
        private readonly WavePaymentGateway $waveGateway,
        private readonly QrCodeGenerator $qrCodeGenerator,
    ) {
        //
    }

    /**
     * Validate and place a guest ticket order, then hand the buyer off to Wave to pay.
     */
    public function store(StoreCheckoutRequest $request, Event $event): Response
    {
        abort_unless($event->status === EventStatus::Published, 404);

        $validated = $request->validated();
        $ticketTypes = $event->ticketTypes()->get()->keyBy('id');

        /** @var array<int, array{ticket_type_id: int, quantity: int}> $items */
        $items = $validated['items'];

        $totalAmount = collect($items)->sum(
            fn (array $item): float => (float) $ticketTypes[$item['ticket_type_id']]->price * $item['quantity'],
        );

        $amounts = $this->commissionService->calculate($totalAmount);

        $order = DB::transaction(function () use ($validated, $items, $event, $amounts, $ticketTypes): Order {
            $order = Order::create([
                'event_id' => $event->id,
                'buyer_name' => $validated['buyer_name'],
                'buyer_email' => $validated['buyer_email'] ?? null,
                'buyer_phone' => $validated['buyer_phone'],
                'total_amount' => $amounts['total'],
                'commission_amount' => $amounts['commission'],
                'net_amount' => $amounts['net'],
                'status' => OrderStatus::Pending,
            ]);

            foreach ($items as $item) {
                /** @var TicketType $ticketType */
                $ticketType = $ticketTypes[$item['ticket_type_id']];

                $order->items()->create([
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $ticketType->price,
                ]);
            }

            return $order;
        });

        // Free tickets (total = 0) and dev/test environments without a Wave
        // account configured (PAYMENT_ENABLED=false) skip Wave entirely: the
        // order is confirmed immediately instead of waiting for a webhook.
        if ($amounts['total'] <= 0.0 || ! config('tickets.payment_enabled')) {
            $this->markOrderPaidAndGenerateTickets($order);

            return to_route('checkout.confirmation', $order);
        }

        try {
            $launch = $this->waveGateway->initiate(
                $order,
                successUrl: route('events.show', ['event' => $event, 'payment' => 'success']),
                errorUrl: route('events.show', ['event' => $event, 'payment' => 'error']),
            );
        } catch (RuntimeException) {
            $order->update(['status' => OrderStatus::Failed]);

            return back()->withErrors([
                'payment' => 'Le paiement Wave est momentanément indisponible. Veuillez réessayer.',
            ]);
        }

        $order->update(['payment_reference' => $launch['session_id']]);

        return Inertia::location($launch['launch_url']);
    }

    /**
     * Display the order confirmation page, showing whichever tickets have been generated so far.
     */
    public function confirmation(Order $order): InertiaResponse
    {
        $order->load(['event', 'items.ticketType', 'tickets.ticketType']);

        return Inertia::render('public/checkout/confirmation', [
            'order' => [
                'confirmation_token' => $order->confirmation_token,
                'buyer_name' => $order->buyer_name,
                'buyer_phone' => $order->buyer_phone,
                'total_amount' => (float) $order->total_amount,
                'status' => $order->status->value,
            ],
            'event' => [
                'title' => $order->event->title,
                'date' => $order->event->date->toIso8601String(),
                'venue' => $order->event->venue,
                'city' => $order->event->city,
            ],
            'items' => $order->items->map(fn ($item) => [
                'ticket_type' => $item->ticketType->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ]),
            'tickets' => $order->tickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'holder_name' => $ticket->holder_name,
                'ticket_type' => $ticket->ticketType->name,
            ]),
        ]);
    }

    /**
     * Generate a downloadable PDF with one page per ticket belonging to the order.
     */
    public function ticketPdf(Order $order): Response
    {
        $order->load(['event', 'tickets.ticketType']);

        abort_if($order->tickets->isEmpty(), 404);

        // The QR is rendered on the fly from Ticket::fullToken() rather than
        // read from storage/qr_image_path: the web and worker containers
        // don't share a filesystem in production, so a PNG the worker wrote
        // to disk isn't visible here. Regenerating is deterministic (same
        // input in, same PNG out) and needs no shared storage.
        $tickets = $order->tickets->map(fn (Ticket $ticket): array => [
            'qr_src' => 'data:image/png;base64,'.base64_encode(
                $this->qrCodeGenerator->toPng($ticket->fullToken())
            ),
            'type_name' => $ticket->ticketType->name,
            'number' => sprintf('#%06d', $ticket->id),
        ]);

        $pdf = Pdf::loadView('pdf.ticket', [
            'event' => $order->event,
            'order' => $order,
            'tickets' => $tickets,
            'platformName' => (string) config('app.name'),
        ]);

        return $pdf->download("billet-{$order->confirmation_token}.pdf");
    }

    /**
     * Receive Wave's payment confirmation webhook.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->waveGateway->verifyWebhookSignature($payload, $request->header('Wave-Signature'))) {
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        $event = json_decode($payload, true);
        $checkoutStatus = Arr::get($event, 'data.checkout_status');
        $orderId = Arr::get($event, 'data.client_reference');

        if (! is_numeric($orderId)) {
            return response()->json(['message' => 'Référence de commande introuvable.'], 404);
        }

        $order = Order::find((int) $orderId);

        if ($order === null) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['message' => 'Déjà traité.']);
        }

        if ($checkoutStatus !== 'complete') {
            Log::info('Webhook Wave reçu sans confirmation de paiement.', [
                'order_id' => $order->id,
                'checkout_status' => $checkoutStatus,
            ]);

            return response()->json(['message' => 'Événement ignoré.']);
        }

        $this->markOrderPaidAndGenerateTickets($order);

        return response()->json(['message' => 'Paiement confirmé.']);
    }

    /**
     * Mark an order as paid and queue ticket generation for it.
     */
    private function markOrderPaidAndGenerateTickets(Order $order): void
    {
        $order->update(['status' => OrderStatus::Paid]);

        GenerateTicketsJob::dispatch($order);
    }
}
