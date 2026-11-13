<?php

namespace Database\Factories;

use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAttendance>
 */
class StaffAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'clock_in_at' => now()->setTime(8, 0),
            'clock_in_photo_path' => 'staff-attendance/contoh-masuk.jpg',
            'is_late' => false,
        ];
    }
}
