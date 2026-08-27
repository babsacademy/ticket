<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scannerIds = User::query()->where('role', UserRole::Scanner)->pluck('id');

        Order::with('event.ticketTypes')
            ->where('status', OrderStatus::Paid)
            ->get()
            ->each(function (Order $order): void {
                Ticket::factory()
                    ->for($order)
                    ->for($order->event->ticketTypes->random())
                    ->count(fake()->numberBetween(1, 3))
                    ->create();
            });

        Ticket::query()
            ->inRandomOrder()
            ->limit((int) round(Ticket::query()->count() * 0.3))
            ->get()
            ->each(function (Ticket $ticket) use ($scannerIds): void {
                $ticket->update([
                    'scanned_at' => now(),
                    'scanned_by' => $scannerIds->isNotEmpty() ? $scannerIds->random() : null,
                ]);
            });
    }
}
