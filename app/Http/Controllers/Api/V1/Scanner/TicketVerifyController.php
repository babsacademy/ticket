<?php

namespace App\Http\Controllers\Api\V1\Scanner;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scanner\VerifyTicketRequest;
use App\Http\Resources\Api\V1\Scanner\TicketVerificationResource;
use App\Models\Ticket;
use App\Services\TicketSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TicketVerifyController extends Controller
{
    public function __construct(private readonly TicketSignatureService $signatureService)
    {
        //
    }

    /**
     * Verify the authenticity and current status of a scanned ticket, without checking it in.
     */
    public function __invoke(VerifyTicketRequest $request): JsonResponse
    {
        $result = $this->signatureService->verifySignature($request->validated('qr_payload'));

        if (! $result['valid']) {
            Log::warning('Scan refusé : signature invalide.', [
                'ticket_id' => null,
                'scanner_id' => $request->user()?->id,
                'reason' => $result['reason'],
            ]);

            return response()->json([
                'valid' => false,
                'reason' => $result['reason'],
            ]);
        }

        $ticket = Ticket::query()
            ->with(['ticketType.event', 'order'])
            ->find($result['data']['ticket_id']);

        if (! $ticket) {
            return response()->json([
                'valid' => false,
                'reason' => 'ticket_not_found',
            ]);
        }

        if (in_array($ticket->ticketType->event->status, [EventStatus::Ended, EventStatus::Cancelled], strict: true)) {
            return response()->json([
                'valid' => false,
                'reason' => 'event_ended',
            ]);
        }

        if ($ticket->scanned_at !== null) {
            Log::warning('Scan refusé : billet déjà scanné.', [
                'ticket_id' => $ticket->id,
                'scanner_id' => $request->user()?->id,
                'reason' => 'already_scanned',
            ]);

            return response()->json([
                'valid' => false,
                'reason' => 'already_scanned',
                'scanned_at' => $ticket->scanned_at->toIso8601String(),
            ]);
        }

        return response()->json([
            'valid' => true,
            'ticket' => new TicketVerificationResource($ticket),
        ]);
    }
}
