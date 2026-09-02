<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $response = $this->get(route('admin.events.index'));

    $response->assertRedirect(route('login'));
});

test('non-admin users are forbidden from the admin dashboard', function () {
    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer)->get(route('admin.events.index'))->assertForbidden();
});

test('a scanner is redirected away from the admin dashboard before the role check even runs', function () {
    // The 'no-scanner' middleware runs before 'role:admin' in this route
    // group, so a scanner is caught (and logged out) by that first —
    // never reaching a plain 403.
    $scanner = User::factory()->scanner()->create();

    $this->actingAs($scanner)->get(route('admin.events.index'))->assertRedirect(route('login'));
});

test('admins can view the events index with sales stats', function () {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->published()->create(['capacity' => 100]);
    $ticketType = TicketType::factory()->for($event)->create();

    $paidOrder = Order::factory()->for($event)->paid()->create(['total_amount' => 10000]);
    Ticket::factory()->for($paidOrder)->for($ticketType)->count(2)->create();

    $pendingOrder = Order::factory()->for($event)->create(['status' => OrderStatus::Pending, 'total_amount' => 5000]);
    Ticket::factory()->for($pendingOrder)->for($ticketType)->create();

    $response = $this->actingAs($admin)->get(route('admin.events.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/index')
        ->where('events.data.0.id', $event->id)
        ->where('events.data.0.tickets_sold', 2)
        ->where('events.data.0.remaining_capacity', 98)
        ->where('events.data.0.revenue', 10000)
    );
});

test('admins can view the create event page', function () {
    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->get(route('admin.events.create'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/create')
        ->has('organizers', 1)
        ->where('organizers.0.id', $organizer->id)
        ->has('statuses', 4)
    );
});

test('when no organizer account exists, the create page falls back to the current admin, pre-selected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.events.create'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/create')
        ->has('organizers', 1)
        ->where('organizers.0.id', $admin->id)
        ->where('organizers.0.role', 'admin')
        ->where('defaultOrganizerId', $admin->id)
    );
});

test('the create page does not fall back to the admin when a real organizer already exists', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->get(route('admin.events.create'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/create')
        ->where('defaultOrganizerId', null)
    );
});

test('an admin account is accepted as the event organizer', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $admin->id,
        'title' => 'Événement auto-organisé',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();
    expect(Event::firstOrFail()->organizer_id)->toBe($admin->id);
});

test('admins can create an event with ticket types', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $organizer->id,
        'title' => 'Dakar Jazz Festival 2026',
        'description' => 'Un festival de jazz.',
        'date' => '2026-09-15T20:00',
        'venue' => 'Théâtre National Daniel Sorano',
        'city' => 'Dakar',
        'capacity' => 500,
        'status' => EventStatus::Published->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        'ticket_types' => [
            ['name' => 'Standard', 'price' => 5000],
            ['name' => 'VIP', 'price' => 15000],
        ],
    ]);

    $event = Event::firstOrFail();

    $response->assertRedirect(route('admin.events.show', $event));
    $response->assertSessionHasNoErrors();

    expect($event->title)->toBe('Dakar Jazz Festival 2026')
        ->and($event->organizer_id)->toBe($organizer->id)
        ->and($event->status)->toBe(EventStatus::Published)
        ->and($event->cover_image)->not->toBeNull()
        ->and($event->cover_image)->toEndWith('.webp')
        ->and($event->ticketTypes)->toHaveCount(2);

    Storage::disk('public')->assertExists($event->cover_image);

    expect($event->ticketTypes->pluck('name')->all())->toBe(['Standard', 'VIP']);
});

test('a JPG cover image is converted to WebP and resized to at most 1200px wide', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $organizer->id,
        'title' => 'Événement JPG',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg', 2000, 1000),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();

    $event = Event::firstOrFail();
    $stored = Storage::disk('public')->get($event->cover_image);
    $size = getimagesizefromstring($stored);

    expect($event->cover_image)->toEndWith('.webp')
        ->and($size['mime'])->toBe('image/webp')
        ->and($size[0])->toBe(1200) // scaled down from 2000 to the 1200px cap
        ->and($size[1])->toBe(600); // proportional: 1000 * (1200/2000)
});

test('a PNG cover image narrower than the cap is converted to WebP without being upscaled', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $organizer->id,
        'title' => 'Événement PNG',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('cover.png', 400, 300),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();

    $event = Event::firstOrFail();
    $stored = Storage::disk('public')->get($event->cover_image);
    $size = getimagesizefromstring($stored);

    expect($event->cover_image)->toEndWith('.webp')
        ->and($size['mime'])->toBe('image/webp')
        ->and($size[0])->toBe(400) // narrower than the 1200px cap: left as-is
        ->and($size[1])->toBe(300);
});

test('updating an event with a new cover image deletes the old one', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'cover_image' => 'events/old-cover.webp']);
    Storage::disk('public')->put('events/old-cover.webp', 'fake-old-webp-bytes');
    TicketType::factory()->for($event)->create();

    $response = $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'organizer_id' => $organizer->id,
        'title' => $event->title,
        'date' => '2026-10-01T19:00',
        'venue' => $event->venue,
        'capacity' => $event->capacity,
        'status' => EventStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('new-cover.jpg'),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();

    $event->refresh();

    Storage::disk('public')->assertMissing('events/old-cover.webp');
    Storage::disk('public')->assertExists($event->cover_image);
    expect($event->cover_image)->not->toBe('events/old-cover.webp')
        ->and($event->cover_image)->toEndWith('.webp');
});

test('the public homepage exposes the converted WebP cover image path', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $organizer->id,
        'title' => 'Événement avec image',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Published->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $event = Event::firstOrFail();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('events.0.cover_image', $event->cover_image)
    );
});

test('creating an event without any ticket type fails validation', function () {
    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $organizer->id,
        'title' => 'Événement sans billets',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'ticket_types' => [],
    ]);

    $response->assertSessionHasErrors('ticket_types');
    expect(Event::count())->toBe(0);
});

test('validation errors are rendered in French', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'title' => 'Événement',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg')->size(5121),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasErrors([
        'organizer_id' => 'Le champ organisateur est obligatoire.',
        'cover_image' => "L'image de couverture ne doit pas dépasser 5 Mo.",
    ]);
});

test('a cover image up to 5 Mo is accepted', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $admin->id,
        'title' => 'Événement',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg')->size(5120),
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();
});

test('an organizer_id that does not belong to an organizer fails validation', function () {
    $admin = User::factory()->admin()->create();
    $scanner = User::factory()->scanner()->create();

    $response = $this->actingAs($admin)->post(route('admin.events.store'), [
        'organizer_id' => $scanner->id,
        'title' => 'Événement',
        'date' => '2026-09-15T20:00',
        'venue' => 'Un lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'ticket_types' => [['name' => 'Standard', 'price' => 1000]],
    ]);

    $response->assertSessionHasErrors('organizer_id');
});

test('admins can view an event with its sold tickets and their scan status', function () {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'VIP']);

    $paidOrder = Order::factory()->for($event)->paid()->create();
    $scannedTicket = Ticket::factory()->for($paidOrder)->for($ticketType)->scanned()->create([
        'holder_name' => 'Fatou Sow',
        'created_at' => now()->subMinute(),
    ]);
    $unscannedTicket = Ticket::factory()->for($paidOrder)->for($ticketType)->create([
        'holder_name' => 'Mamadou Diallo',
        'created_at' => now(),
    ]);

    $pendingOrder = Order::factory()->for($event)->create(['status' => OrderStatus::Pending]);
    Ticket::factory()->for($pendingOrder)->for($ticketType)->create();

    $response = $this->actingAs($admin)->get(route('admin.events.show', $event));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/show')
        ->where('event.id', $event->id)
        ->has('tickets', 2)
        ->where('tickets.0.id', $unscannedTicket->id)
        ->where('tickets.0.scanned_at', null)
        ->where('tickets.1.id', $scannedTicket->id)
    );
});

test('admins can view the edit page with the event and its ticket types', function () {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $response = $this->actingAs($admin)->get(route('admin.events.edit', $event));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/events/edit')
        ->where('event.id', $event->id)
        ->has('event.ticket_types', 1)
        ->where('event.ticket_types.0.id', $ticketType->id)
    );
});

test('admins can update an event, edit an existing ticket type, and add a new one', function () {
    $admin = User::factory()->admin()->create();
    $organizer = User::factory()->organizer()->create();
    $event = Event::factory()->create(['title' => 'Ancien titre']);
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Standard', 'price' => 5000]);

    $response = $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'organizer_id' => $organizer->id,
        'title' => 'Nouveau titre',
        'date' => '2026-10-01T19:00',
        'venue' => 'Nouveau lieu',
        'capacity' => 200,
        'status' => EventStatus::Published->value,
        'ticket_types' => [
            ['id' => $ticketType->id, 'name' => 'Standard', 'price' => 6000],
            ['name' => 'VIP', 'price' => 20000],
        ],
    ]);

    $response->assertRedirect(route('admin.events.show', $event));
    $response->assertSessionHasNoErrors();

    $event->refresh();

    expect($event->title)->toBe('Nouveau titre')
        ->and($event->organizer_id)->toBe($organizer->id)
        ->and($event->ticketTypes)->toHaveCount(2);

    expect($ticketType->fresh()->price)->toBe('6000.00');
});

test('a ticket_types.*.id from another event is rejected on update', function () {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->create();
    $otherEventTicketType = TicketType::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.events.update', $event), [
        'organizer_id' => User::factory()->organizer()->create()->id,
        'title' => 'Titre',
        'date' => '2026-10-01T19:00',
        'venue' => 'Lieu',
        'capacity' => 100,
        'status' => EventStatus::Draft->value,
        'ticket_types' => [
            ['id' => $otherEventTicketType->id, 'name' => 'Standard', 'price' => 1000],
        ],
    ]);

    $response->assertSessionHasErrors('ticket_types.0.id');
});
