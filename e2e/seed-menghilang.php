<?php

// Data E2E member menghilang: absen peringatan, absen bahaya, baru datang, beku, kuota habis, sudah dihubungi.
use App\Enums\FollowUpChannel;
use App\Enums\FollowUpOutcome;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plan = MembershipPlan::where('is_active', true)->orderBy('sort_order')->firstOrFail();
$pegawai = User::role(UserRole::Pegawai->value)->firstOrFail();

$buat = function (string $key, string $nama, int $n, ?int $hariLalu, array $membership = []) use ($plan, $pegawai) {
    $user = User::factory()->create(['name' => $nama, 'email' => "hilang-{$key}@example.com"])->assignRole(UserRole::Member->value);
    $member = $user->member()->create([
        'phone' => '0816000000'.$n,
        'gender' => 'L',
        'status' => MemberStatus::Active,
        'selected_plan_id' => $plan->id,
    ]);
    $member->forceFill([
        'member_number' => sprintf('RPK-2611-%04d', 950 + $n),
        'activated_at' => now()->subDays(60),
    ])->save();
    $member->memberships()->create($membership + [
        'membership_plan_id' => $plan->id,
        'starts_on' => now()->subDays(40)->toDateString(),
        'ends_on' => now()->addDays(20)->toDateString(),
    ]);
    if ($hariLalu !== null) {
        CheckIn::factory()->create(['member_id' => $member->id, 'checked_in_at' => now()->subDays($hariLalu), 'scanned_by' => $pegawai->id]);
    }

    return $member;
};

$buat('peringatan', 'Hilang Peringatan', 1, 20);
$buat('bahaya', 'Hilang Bahaya', 2, 40);
$buat('baru', 'Hilang Baru Datang', 3, 3);
$buat('beku', 'Hilang Beku', 4, 25, ['frozen_from' => now()->subDay()->toDateString(), 'frozen_until' => now()->addDays(5)->toDateString()]);
$buat('kuota', 'Hilang Kuota Habis', 5, 25, ['visits_remaining' => 0]);
$sudah = $buat('sudah', 'Hilang Sudah Dihubungi', 6, 22);
$sudah->followUps()->create([
    'user_id' => $pegawai->id,
    'contacted_at' => now()->subDays(2),
    'channel' => FollowUpChannel::Chat,
    'outcome' => FollowUpOutcome::Sibuk,
    'note' => 'Lembur sampai akhir bulan',
]);
