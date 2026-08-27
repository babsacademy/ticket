<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Standard', 'VIP', 'Early Bird', 'Backstage']),
            'price' => fake()->randomElement([2500, 5000, 10000, 25000]),
            'quantity' => fake()->numberBetween(20, 500),
            'sold_count' => 0,
            'sale_start' => now(),
            'sale_end' => now()->addMonths(3),
        ];
    }

    /**
     * Indicate that the ticket type is sold out.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'sold_count' => $attributes['quantity'],
        ]);
    }
}
