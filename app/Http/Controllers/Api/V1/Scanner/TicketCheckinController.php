<?php

namespace App\Http\Controllers\Api\V1\Scanner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Scanner\CheckinTicketRequest;
use App\Http\Resources\Api\V1\Scanner\EventCheckinResource;
use App\Http\Resources\Api\V1\Scanner\ScannedByResource;
use App\Http\Resources\Api\V1\Scanner\TicketCheckinResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketCheckinController extends Controller
{
    /**
     * Mark a verified ticket as checked in (scanned), idempotently.
     */
    public function __invoke(CheckinTicketRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $ticket = Ticket::query()
                ->with(['ticketType.event', 'scannedBy'])
                ->lockForUpdate()
                ->find((int) $request->validated('ticket_id'));

            if (! $ticket) {
                abort(404, 'Billet introuvable.');
            }

            if ($ticket->scanned_at !== null) {
                Log::warning('Scan refusé : billet déjà scanné.', [
                    'ticket_id' => $ticket->id,
                    'scanner_id' => $request->user()?->id,
                    'reason' => 'already_scanned',
                ]);

                return response()->json([
                    'message' => 'Ce billet a déjà été scanné.',
                    'scanned_at' => $ticket->scanned_at->toIso8601String(),
                    'scanned_by' => $ticket->scannedBy?->name,
                ], 409);
            }

            $scanner = $request->user();
            $scannedAt = now();

            $ticket->update([
                'scanned_at' => $scannedAt,
                'scanned_by' => $scanner->id,
            ]);

            return response()->json([
                'checked_in_at' => $scannedAt->toIso8601String(),
                'ticket' => new TicketCheckinResource($ticket),
                'event' => new EventCheckinResource($ticket->ticketType->event),
                'scanned_by' => new ScannedByResource($scanner),
            ]);
        });
    }
}
