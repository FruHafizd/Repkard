<?php

// Data E2E absensi pegawai: "Pegawai Absen" (jam kerja 08:00-16:00, belum absen) dan "Pegawai Lain"
// yang kemarin absen masuk (lupa pulang) dengan foto private.
use App\Enums\UserRole;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

User::factory()->create([
    'name' => 'Pegawai Absen',
    'email' => 'pegawai-absen@repkard.test',
    'password' => 'password',
    'work_start_time' => '08:00',
    'work_end_time' => '16:00',
])->assignRole(UserRole::Pegawai->value);

$lain = User::factory()->create([
    'name' => 'Pegawai Lain',
    'email' => 'pegawai-lain@repkard.test',
    'password' => 'password',
])->assignRole(UserRole::Pegawai->value);

Storage::disk('local')->put('staff-attendance/e2e-lain.png', $png);
StaffAttendance::factory()->create([
    'user_id' => $lain->id,
    'clock_in_at' => now()->subDay()->setTime(8, 0),
    'clock_in_photo_path' => 'staff-attendance/e2e-lain.png',
]);
