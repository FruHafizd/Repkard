<?php

namespace Database\Factories;

use App\Enums\PtSessionStatus;
use App\Models\PtSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtSession>
 */
class PtSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => PtSessionStatus::Scheduled,
        ];
    }
}
