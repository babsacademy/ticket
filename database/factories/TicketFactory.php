<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = base64_encode((string) json_encode([
            'ticket_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'event_id' => fake()->numberBetween(1, 1000),
            'holder_id' => fake()->numberBetween(1, 1000),
            'issued_at' => now()->toIso8601String(),
        ]));

        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'holder_name' => fake()->name(),
            'holder_email' => fake()->safeEmail(),
            'qr_payload' => $payload,
            'signature' => hash('sha256', $payload.fake()->uuid()),
            'scanned_at' => null,
            'scanned_by' => null,
        ];
    }

    /**
     * Indicate that the ticket has already been scanned.
     */
    public function scanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'scanned_at' => now(),
            'scanned_by' => User::factory()->scanner(),
        ]);
    }
}
