<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCard>
 */
class MemberCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'version' => 1,
            'issued_at' => now(),
        ];
    }
}
