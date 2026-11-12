<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Models\WalkIn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalkIn>
 */
class WalkInFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'amount' => 25000,
            'method' => PaymentMethod::Cash,
            'recorded_by' => User::factory(),
            'visited_at' => now(),
        ];
    }
}
