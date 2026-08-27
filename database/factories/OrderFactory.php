<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * The platform commission rate applied to every order.
     */
    private const COMMISSION_RATE = 0.10;

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Order $order): void {
            if (blank($order->confirmation_token)) {
                $order->confirmation_token = Str::random(48);
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
        $totalAmount = fake()->randomElement([2500, 5000, 10000, 25000, 50000]);
        $commissionAmount = round($totalAmount * self::COMMISSION_RATE, 2);

        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'total_amount' => $totalAmount,
            'commission_amount' => $commissionAmount,
            'net_amount' => $totalAmount - $commissionAmount,
            'status' => OrderStatus::Pending,
            'payment_reference' => null,
        ];
    }

    /**
     * Indicate that the order has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'payment_reference' => strtoupper(fake()->bothify('PAY-########')),
        ]);
    }

    /**
     * Indicate that the order has been refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
        ]);
    }

    /**
     * Indicate that the order failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Failed,
        ]);
    }

    /**
     * Indicate that the order was placed as a guest (no linked user).
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
