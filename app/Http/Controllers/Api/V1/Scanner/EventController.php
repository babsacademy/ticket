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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * List published events happening today or later, with their paid ticket count.
     */
    public function index(): AnonymousResourceCollection
    {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->where('date', '>=', now()->startOfDay())
            ->withCount(['tickets as ticket_count' => function (Builder $query): void {
                $query->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Paid));
            }])
            ->orderBy('date')
            ->get();

        return EventResource::collection($events);
    }

    /**
     * List an event's valid (paid) tickets, for offline download by the scanner app.
     * Each ticket includes is_scanned and scanned_at so a new device can
     * seed local scan state; scanned_by is never exposed here.
     */
    public function tickets(Event $event): AnonymousResourceCollection
    {
        $tickets = Ticket::query()
            ->whereHas('ticketType', fn (Builder $query) => $query->where('event_id', $event->id))
            ->whereHas('order', fn (Builder $query) => $query->where('status', OrderStatus::Paid))
            ->with('ticketType')
            ->get();

        return TicketDownloadResource::collection($tickets);
    }
}
