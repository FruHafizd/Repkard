<?php

// Data E2E personal trainer: 2 trainer, 1 paket PT, member "Uji PT" (membership aktif, belum punya PT),
// "Uji PT Tanpa" (aktif tapi tanpa membership berlaku), dan "Uji PT Lain" yang sudah punya PT dengan trainer 2 (subscription id 1).
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Member;
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

$trainers = [];
foreach ([1 => 'Trainer Satu', 2 => 'Trainer Dua'] as $n => $nama) {
    $trainers[$n] = User::factory()->create(['name' => $nama, 'email' => "trainer{$n}@repkard.test", 'password' => 'password'])
        ->assignRole(UserRole::Trainer->value);
    $trainers[$n]->trainerProfile()->create(['specialties' => 'Strength', 'commission_per_session' => 50000]);
}

$paket = PtPackage::create(['name' => 'PT 10 Sesi', 'sessions_count' => 10, 'price' => 1500000, 'validity_days' => 60, 'is_active' => true, 'sort_order' => 1]);

$buat = function (string $nama, int $n, bool $membership) use ($plan, $png): Member {
    $path = "member-photos/e2e-pt-{$n}.png";
    Storage::disk('local')->put($path, $png);
    $user = User::factory()->create(['name' => $nama, 'email' => "pt-{$n}@example.com", 'password' => 'password'])->assignRole(UserRole::Member->value);
    $member = $user->member()->create(['phone' => '0818000000'.$n, 'gender' => 'L', 'photo_path' => $path, 'status' => MemberStatus::Active, 'selected_plan_id' => $plan->id]);
    $member->forceFill(['member_number' => sprintf('RPK-2611-%04d', 980 + $n), 'activated_at' => now()->subDays(3)])->save();

    if ($membership) {
        $member->memberships()->create([
            'membership_plan_id' => $plan->id,
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addDays(26)->toDateString(),
        ]);
    }

    return $member;
};

$buat('Uji PT', 1, true);
$buat('Uji PT Tanpa', 2, false);
$lain = $buat('Uji PT Lain', 3, true);

$sub = PtSubscription::create([
    'member_id' => $lain->id, 'pt_package_id' => $paket->id, 'trainer_id' => $trainers[2]->id,
    'sessions_total' => 10, 'sessions_remaining' => 10,
    'starts_on' => now()->toDateString(), 'expires_on' => now()->addDays(59)->toDateString(), 'created_by' => $pegawai->id,
]);
$lain->payments()->create([
    'pt_subscription_id' => $sub->id, 'type' => PaymentType::Pt, 'amount' => 1500000, 'original_amount' => 1500000,
    'method' => PaymentMethod::Cash, 'recorded_by' => $pegawai->id, 'paid_at' => now()->subDays(1),
]);
