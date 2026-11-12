<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'membership_id' => Membership::factory(),
            'amount' => 100000,
            'method' => PaymentMethod::Cash,
            'reason' => 'Pindah kota',
            'recorded_by' => User::factory(),
            'refunded_at' => now(),
        ];
    }
}
