<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display the paginated list of events with sales stats.
     */
    public function index(): Response
    {
        $events = Event::query()
            ->with('organizer')
            ->withCount(['tickets as tickets_sold_count' => function (Builder $query): void {
                $query->whereHas('order', fn (Builder $order) => $order->where('status', OrderStatus::Paid));
            }])
            ->withSum(['orders as revenue' => function (Builder $query): void {
                $query->where('status', OrderStatus::Paid);
            }], 'total_amount')
            ->orderByDesc('date')
            ->paginate(15)
            ->withQueryString();

        $events->getCollection()->transform(function (Event $event): array {
            $ticketsSold = (int) $event->getAttribute('tickets_sold_count');

            return [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->date->toIso8601String(),
                'venue' => $event->venue,
                'city' => $event->city,
                'status' => $event->status->value,
                'capacity' => $event->capacity,
                'organizer' => $event->organizer->name,
                'tickets_sold' => $ticketsSold,
                'remaining_capacity' => $event->capacity - $ticketsSold,
                'revenue' => (float) ($event->getAttribute('revenue') ?? 0),
            ];
        });

        return Inertia::render('admin/events/index', [
            'events' => $events,
        ]);
    }

    /**
     * Show the form for creating a new event.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/events/create', [
            'organizers' => $this->organizers($request),
            'statuses' => $this->statuses(),
            'defaultOrganizerId' => $this->defaultOrganizerId($request),
        ]);
    }

    /**
     * Store a newly created event and its ticket types.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $event = DB::transaction(function () use ($validated, $request): Event {
            $event = Event::create([
                ...collect($validated)->except(['cover_image', 'ticket_types'])->all(),
                'cover_image' => $request->hasFile('cover_image')
                    ? $request->file('cover_image')->store('events', 'public')
                    : null,
            ]);

            foreach ($validated['ticket_types'] as $ticketType) {
                $event->ticketTypes()->create([
                    'name' => $ticketType['name'],
                    'price' => $ticketType['price'],
                    'quantity' => $event->capacity,
                ]);
            }

            return $event;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'événement a été créé."]);

        return to_route('admin.events.show', $event);
    }

    /**
     * Display the event's sold tickets and their scan status.
     */
    public function show(Event $event): Response
    {
        $event->load('organizer', 'ticketTypes');

        $tickets = Ticket::query()
            ->whereHas('ticketType', fn (Builder $query) => $query->where('event_id', $event->id))
            ->whereHas('order', fn (Builder $query) => $query->where('status', OrderStatus::Paid))
            ->with(['ticketType', 'scannedBy'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'holder_name' => $ticket->holder_name,
                'holder_email' => $ticket->holder_email,
                'ticket_type' => $ticket->ticketType->name,
                'scanned_at' => $ticket->scanned_at?->toIso8601String(),
                'scanned_by' => $ticket->scannedBy?->name,
            ]);

        return Inertia::render('admin/events/show', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->date->toIso8601String(),
                'venue' => $event->venue,
                'city' => $event->city,
                'capacity' => $event->capacity,
                'cover_image' => $event->cover_image,
                'status' => $event->status->value,
                'organizer' => $event->organizer->name,
                'ticket_types' => $event->ticketTypes->map(fn ($ticketType) => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'price' => (float) $ticketType->price,
                ]),
            ],
            'tickets' => $tickets,
        ]);
    }

    /**
     * Show the form for editing an existing event.
     */
    public function edit(Request $request, Event $event): Response
    {
        $event->load('ticketTypes');

        return Inertia::render('admin/events/edit', [
            'event' => [
                'id' => $event->id,
                'organizer_id' => $event->organizer_id,
                'title' => $event->title,
                'description' => $event->description,
                'date' => $event->date->format('Y-m-d\TH:i'),
                'venue' => $event->venue,
                'city' => $event->city,
                'capacity' => $event->capacity,
                'cover_image' => $event->cover_image,
                'status' => $event->status->value,
                'ticket_types' => $event->ticketTypes->map(fn ($ticketType) => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'price' => (float) $ticketType->price,
                ]),
            ],
            'organizers' => $this->organizers($request),
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Update an existing event and sync its ticket types.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $request, $event): void {
            $event->update([
                ...collect($validated)->except(['cover_image', 'ticket_types'])->all(),
                ...($request->hasFile('cover_image')
                    ? ['cover_image' => $request->file('cover_image')->store('events', 'public')]
                    : []),
            ]);

            foreach ($validated['ticket_types'] as $ticketType) {
                if (! empty($ticketType['id'])) {
                    $event->ticketTypes()->whereKey($ticketType['id'])->update([
                        'name' => $ticketType['name'],
                        'price' => $ticketType['price'],
                    ]);
                } else {
                    $event->ticketTypes()->create([
                        'name' => $ticketType['name'],
                        'price' => $ticketType['price'],
                        'quantity' => $event->capacity,
                    ]);
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "L'événement a été mis à jour."]);

        return to_route('admin.events.show', $event);
    }

    /**
     * Get the list of users eligible to organize an event. Falls back to
     * the currently authenticated admin when no organizer-role account
     * exists yet, so the form is never stuck with an empty, unusable
     * select — see defaultOrganizerId(), which pre-selects that same
     * fallback on the create form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function organizers(Request $request): array
    {
        $organizers = User::query()
            ->where('role', UserRole::Organizer)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        if ($organizers->isEmpty() && $request->user() instanceof User) {
            $organizers = collect([$request->user()]);
        }

        return $organizers->map(fn (User $organizer): array => [
            'id' => $organizer->id,
            'name' => $organizer->name,
            'email' => $organizer->email,
            'role' => $organizer->role->value,
        ])->all();
    }

    /**
     * The organizer to pre-select on the create form: the current admin
     * when no organizer-role account exists yet (see organizers() above),
     * otherwise null so the placeholder shows and the admin picks
     * explicitly.
     */
    private function defaultOrganizerId(Request $request): ?int
    {
        $hasOrganizer = User::query()->where('role', UserRole::Organizer)->exists();

        return $hasOrganizer ? null : $request->user()?->id;
    }

    /**
     * Get the list of possible event statuses.
     *
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return array_map(fn (EventStatus $status): string => $status->value, EventStatus::cases());
    }
}
