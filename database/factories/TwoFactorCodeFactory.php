<?php

namespace Database\Factories;

use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<TwoFactorCode>
 */
class TwoFactorCodeFactory extends Factory
{
    /**
     * The plaintext code most recently produced by this factory, so tests
     * can submit it back through the verification endpoint without
     * knowing the hashing scheme.
     */
    public static string $plainCode = '123456';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => Hash::make(self::$plainCode),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    /**
     * Indicate that the code has already expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
