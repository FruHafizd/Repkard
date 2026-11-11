<?php

// Data tambahan khusus E2E: pegawai dengan password sementara.
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

User::factory()->create([
    'name' => 'Pegawai Baru',
    'email' => 'pegawai-baru@repkard.test',
    'password' => 'sementara123',
    'must_change_password' => true,
])->assignRole(UserRole::Pegawai->value);

// Dua pendaftar mandiri menunggu aktivasi (satu untuk diaktifkan, satu untuk ditolak), lengkap dengan foto private.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$plan = MembershipPlan::where('is_active', true)->orderBy('sort_order')->firstOrFail();

foreach (['Calon Aktivasi' => '081300000001', 'Calon Ditolak' => '081300000002'] as $name => $phone) {
    $user = User::factory()->create(['name' => $name, 'email' => preg_replace('/\D/', '', $phone).'@example.com'])
        ->assignRole(UserRole::Member->value);
    $path = 'member-photos/e2e-'.$phone.'.png';
    Storage::disk('local')->put($path, $png);
    $user->member()->create([
        'phone' => $phone,
        'gender' => 'L',
        'photo_path' => $path,
        'status' => MemberStatus::Pending,
        'selected_plan_id' => $plan->id,
    ]);
}
