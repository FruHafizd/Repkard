<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->environment('local')) {
            $this->call(MembershipPlanSeeder::class);

            foreach ([
                ['owner@repkard.test', 'Owner Repkard', UserRole::Owner],
                ['pegawai@repkard.test', 'Pegawai Repkard', UserRole::Pegawai],
            ] as [$email, $name, $role]) {
                User::factory()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => 'password',
                ])->assignRole($role->value);
            }
        }
    }
}
