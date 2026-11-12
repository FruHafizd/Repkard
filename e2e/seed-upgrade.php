<?php

// Data E2E upgrade & refund: dua member aktif dengan paket Bulanan (sudah 10 hari berjalan, sisa 20 dari 30 hari).
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plan = MembershipPlan::where('name', 'Bulanan')->firstOrFail();
$pegawai = User::role(UserRole::Pegawai->value)->firstOrFail();

foreach (['Uji Upgrade' => 1, 'Uji Refund' => 2] as $nama => $n) {
    $user = User::factory()->create(['name' => $nama, 'email' => "upgrade-{$n}@example.com"])->assignRole(UserRole::Member->value);
    $member = $user->member()->create([
        'phone' => '0817000000'.$n, 'gender' => 'L', 'status' => MemberStatus::Active, 'selected_plan_id' => $plan->id,
    ]);
    $member->forceFill(['member_number' => sprintf('RPK-2611-%04d', 970 + $n), 'activated_at' => now()->subDays(10)])->save();
    $membership = $member->memberships()->create([
        'membership_plan_id' => $plan->id,
        'starts_on' => now()->subDays(10)->toDateString(),
        'ends_on' => now()->addDays(19)->toDateString(),
    ]);
    $member->payments()->create([
        'membership_id' => $membership->id, 'amount' => $plan->price, 'original_amount' => $plan->price,
        'method' => PaymentMethod::Cash, 'recorded_by' => $pegawai->id, 'paid_at' => now()->subDays(10),
    ]);
}
