<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TRX-'.now()->format('ymd').'-'.fake()->unique()->numerify('####'),
            'total' => 10000,
            'method' => PaymentMethod::Cash,
            'cash_received' => 10000,
            'recorded_by' => User::factory(),
            'sold_at' => now(),
        ];
    }
}
