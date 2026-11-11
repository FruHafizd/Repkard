<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(29)->toDateString(),
        ];
    }
}
