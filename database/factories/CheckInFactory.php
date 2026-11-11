<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'method' => 'card',
            'result' => CheckIn::RESULT_ACCEPTED,
            'scanned_by' => User::factory(),
            'checked_in_at' => now(),
        ];
    }
}
