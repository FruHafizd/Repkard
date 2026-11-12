<?php

namespace Database\Factories;

use App\Enums\FollowUpChannel;
use App\Enums\FollowUpOutcome;
use App\Models\Member;
use App\Models\MemberFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberFollowUp>
 */
class MemberFollowUpFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'user_id' => User::factory(),
            'contacted_at' => now(),
            'channel' => FollowUpChannel::Telepon,
            'outcome' => FollowUpOutcome::AkanDatang,
        ];
    }
}
