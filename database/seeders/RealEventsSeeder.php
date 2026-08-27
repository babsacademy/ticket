<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealEventsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application with real launch data: the admin and scanner
     * accounts, and the two real events on sale. No orders or tickets are
     * created — the platform starts with a clean sales history.
     */
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Babacar Thiam',
            'email' => 'thiambabs77@gmail.com',
            'password' => 'Babs2024!',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Agent Scanner',
            'email' => 'scanner@scanticket.sn',
            'password' => 'Scanner2024!',
            'role' => UserRole::Scanner,
            'email_verified_at' => now(),
        ]);

        $this->createEvent(
            organizerId: $admin->id,
            title: 'Wally B. Seck en Concert',
            date: now()->addDays(30),
            venue: 'Stade Léopold Sédar Senghor',
            city: 'Dakar',
            capacity: 500,
            ticketTypes: [
                ['name' => 'Tribune', 'price' => 10000, 'quantity' => 300],
                ['name' => 'Carré Or', 'price' => 25000, 'quantity' => 150],
                ['name' => 'VIP', 'price' => 100000, 'quantity' => 50],
            ],
        );

        $this->createEvent(
            organizerId: $admin->id,
            title: 'Youssou Ndour — Grand Concert',
            date: now()->addDays(45),
            venue: 'Grand Théâtre National',
            city: 'Dakar',
            capacity: 500,
            ticketTypes: [
                ['name' => 'Tribune', 'price' => 10000, 'quantity' => 300],
                ['name' => 'Carré Or', 'price' => 20000, 'quantity' => 150],
                ['name' => 'VIP', 'price' => 30000, 'quantity' => 50],
            ],
        );
    }

    /**
     * Create a published event with its ticket types.
     *
     * @param  array<int, array{name: string, price: int, quantity: int}>  $ticketTypes
     */
    private function createEvent(
        int $organizerId,
        string $title,
        CarbonInterface $date,
        string $venue,
        string $city,
        int $capacity,
        array $ticketTypes,
    ): void {
        $event = new Event([
            'organizer_id' => $organizerId,
            'title' => $title,
            'date' => $date,
            'venue' => $venue,
            'city' => $city,
            'capacity' => $capacity,
            'status' => EventStatus::Published,
        ]);

        // The `slug` attribute is intentionally excluded from Event's
        // fillable list, and this seeder runs under WithoutModelEvents (so
        // the model's own auto-slug `creating` hook does not fire) — set it
        // directly before saving.
        $event->slug = Str::slug($title);
        $event->save();

        foreach ($ticketTypes as $ticketType) {
            TicketType::create([
                'event_id' => $event->id,
                'name' => $ticketType['name'],
                'price' => $ticketType['price'],
                'quantity' => $ticketType['quantity'],
                'sold_count' => 0,
            ]);
        }
    }
}
