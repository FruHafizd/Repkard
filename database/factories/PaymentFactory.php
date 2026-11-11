<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'amount' => 200000,
            'method' => PaymentMethod::Cash,
            'recorded_by' => User::factory(),
            'paid_at' => now(),
        ];
    }
}
