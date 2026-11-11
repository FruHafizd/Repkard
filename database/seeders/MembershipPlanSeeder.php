<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Harian', 'duration_days' => 1, 'visit_quota' => 1, 'price' => 25000],
            ['name' => 'Bulanan', 'duration_days' => 30, 'visit_quota' => null, 'price' => 200000],
            ['name' => '3 Bulan', 'duration_days' => 90, 'visit_quota' => null, 'price' => 550000],
            ['name' => 'Tahunan', 'duration_days' => 365, 'visit_quota' => null, 'price' => 2000000],
            ['name' => '10x Kunjungan', 'duration_days' => 60, 'visit_quota' => 10, 'price' => 220000],
        ];

        foreach ($plans as $index => $plan) {
            MembershipPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan + ['is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
