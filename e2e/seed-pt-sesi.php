<?php

// Data E2E jadwal sesi PT: trainer "Trainer Sesi" dan member "Uji Sesi" dengan paket PT 5 sesi yang belum dijadwalkan.
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\MembershipPlan;
use App\Models\PtPackage;
use App\Models\PtSubscription;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$plan = MembershipPlan::where('name', 'Bulanan')->firstOrFail();
$pegawai = User::role(UserRole::Pegawai->value)->firstOrFail();

$trainer = User::factory()->create(['name' => 'Trainer Sesi', 'email' => 'trainer-sesi@repkard.test', 'password' => 'password'])
    ->assignRole(UserRole::Trainer->value);
$trainer->trainerProfile()->create(['specialties' => 'Cardio', 'commission_per_session' => 50000]);

$paket = PtPackage::create(['name' => 'PT 5 Sesi', 'sessions_count' => 5, 'price' => 800000, 'validity_days' => 60, 'is_active' => true, 'sort_order' => 2]);

Storage::disk('local')->put('member-photos/e2e-pt-sesi.png', $png);
$user = User::factory()->create(['name' => 'Uji Sesi', 'email' => 'pt-sesi@example.com', 'password' => 'password'])->assignRole(UserRole::Member->value);
$member = $user->member()->create(['phone' => '08180000099', 'gender' => 'L', 'photo_path' => 'member-photos/e2e-pt-sesi.png', 'status' => MemberStatus::Active, 'selected_plan_id' => $plan->id]);
$member->forceFill(['member_number' => 'RPK-2611-0990', 'activated_at' => now()->subDays(3)])->save();
$member->memberships()->create([
    'membership_plan_id' => $plan->id,
    'starts_on' => now()->subDays(3)->toDateString(),
    'ends_on' => now()->addDays(26)->toDateString(),
]);

PtSubscription::create([
    'member_id' => $member->id, 'pt_package_id' => $paket->id, 'trainer_id' => $trainer->id,
    'sessions_total' => 5, 'sessions_remaining' => 5,
    'starts_on' => now()->toDateString(), 'expires_on' => now()->addDays(59)->toDateString(), 'created_by' => $pegawai->id,
]);
