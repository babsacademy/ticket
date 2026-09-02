<?php

namespace App\Http\Controllers\Api\V1\Scanner;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Scanner\EventResource;
use App\Http\Resources\Api\V1\Scanner\TicketDownloadResource;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * List published events happening today or later that the
     * authenticated scanner is assigned to, with their paid ticket count.
     * A scanner sees only their own assignments — not every event on the
     * platform.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->where('date', '>=', now()->startOfDay())
            ->whereHas('assignedScanners', fn (Builder $query) => $query->whereKey($request->user()->id))
            ->withCount(['tickets as ticket_count' => function (Builder $query): void {
                $query->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Paid));
            }])
            ->orderBy('date')
            ->get();

        return EventResource::collection($events);
    }

    /**
     * List an event's valid (paid) tickets, for offline download by the
     * scanner app. Each ticket includes is_scanned and scanned_at so a new
     * device can seed local scan state; scanned_by is never exposed here.
     *
     * Restricted to scanners assigned to this specific event (scanner_event)
     * — without this, any valid scanner token could download every other
     * event's ticket tokens too (IDOR), including their signed QR content.
     */
    public function tickets(Request $request, Event $event): AnonymousResourceCollection
    {
        abort_unless(
            $event->assignedScanners()->whereKey($request->user()->id)->exists(),
            403,
            "Ce compte scanner n'est pas affecté à cet événement.",
        );

        $tickets = Ticket::query()
            ->whereHas('ticketType', fn (Builder $query) => $query->where('event_id', $event->id))
            ->whereHas('order', fn (Builder $query) => $query->where('status', OrderStatus::Paid))
            ->with('ticketType')
            ->get();

        return TicketDownloadResource::collection($tickets);
    }
}
