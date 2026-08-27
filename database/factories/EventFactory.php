<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Event $event): void {
            if (blank($event->slug)) {
                $event->slug = Str::slug($event->title).'-'.Str::random(6);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'venue' => fake()->company(),
            'city' => fake()->randomElement(['Dakar', 'Thiès', 'Saint-Louis', 'Ziguinchor', 'Touba']),
            'capacity' => fake()->numberBetween(50, 5000),
            'cover_image' => null,
            'status' => EventStatus::Draft,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    /**
     * Indicate that the event has already ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Ended,
            'date' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the event has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Cancelled,
        ]);
    }
}
