<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display the public homepage listing upcoming published events.
     */
    public function index(): Response
    {
        $events = Event::query()
            ->where('status', EventStatus::Published)
            ->where('date', '>=', now())
            ->withMin('ticketTypes', 'price')
            ->orderBy('date')
            ->get();

        return Inertia::render('public/home', [
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->title,
                'date' => $event->date->toIso8601String(),
                'venue' => $event->venue,
                'city' => $event->city,
                'cover_image' => $event->cover_image,
                'price_from' => $event->getAttribute('ticket_types_min_price') !== null
                    ? (float) $event->getAttribute('ticket_types_min_price')
                    : null,
            ]),
        ]);
    }

    /**
     * Display the public purchase page for a published event.
     */
    public function show(Request $request, Event $event): Response
    {
        abort_unless($event->status === EventStatus::Published, 404);

        $event->load('ticketTypes');

        return Inertia::render('public/events/show', [
            'paymentStatus' => $request->query('payment'),
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->date->toIso8601String(),
                'venue' => $event->venue,
                'city' => $event->city,
                'cover_image' => $event->cover_image,
            ],
            'ticketTypes' => $event->ticketTypes->map(fn ($ticketType) => [
                'id' => $ticketType->id,
                'name' => $ticketType->name,
                'price' => (float) $ticketType->price,
                'remaining' => max(0, $ticketType->quantity - $ticketType->sold_count),
            ]),
        ]);
    }
}
