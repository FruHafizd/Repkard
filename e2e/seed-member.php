<?php

// Data E2E panel member: member aktif (+riwayat), member lain (untuk uji isolasi), member beku, member pending.
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\CheckIn;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\MemberCardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$plan = MembershipPlan::where('is_active', true)->orderBy('sort_order')->firstOrFail();
$admin = User::role(UserRole::Owner->value)->first() ?? User::factory()->create()->assignRole(UserRole::Owner->value);

$buat = function (string $key, string $nama, MemberStatus $status, int $n, array $membership = []) use ($png, $plan) {
    $user = User::factory()->create(['name' => $nama, 'email' => "pm-{$key}@example.com"])->assignRole(UserRole::Member->value);
    $path = "member-photos/e2e-pm-{$n}.png";
    Storage::disk('local')->put($path, $png);
    $member = $user->member()->create([
        'phone' => '0815000000'.$n,
        'gender' => 'P',
        'birth_date' => '1995-05-17',
        'address' => 'Jl. Lama No. 1',
        'photo_path' => $path,
        'status' => $status,
        'selected_plan_id' => $plan->id,
    ]);
    if ($status === MemberStatus::Active) {
        $member->forceFill(['member_number' => sprintf('RPK-2611-%04d', 800 + $n)])->save();
        $m = $member->memberships()->create($membership + [
            'membership_plan_id' => $plan->id,
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->addDays(19)->toDateString(),
        ]);
        app(MemberCardService::class)->issue($member, null);

        return [$member, $m];
    }

    return [$member, null];
};

[$a, $ma] = $buat('aktif', 'Sari Aktif', MemberStatus::Active, 1, ['visits_remaining' => 8]);
[$b, $mb] = $buat('lain', 'Budi Lain', MemberStatus::Active, 2);
$buat('beku', 'Dewi Beku', MemberStatus::Active, 3, [
    'frozen_from' => now()->subDay()->toDateString(),
    'frozen_until' => now()->addDays(5)->toDateString(),
]);
$buat('pending', 'Pia Pending', MemberStatus::Pending, 4);

foreach ([0, 1, 2] as $d) {
    CheckIn::create(['member_id' => $a->id, 'membership_id' => $ma->id, 'method' => 'card', 'result' => 'accepted', 'scanned_by' => $admin->id, 'checked_in_at' => now()->subDays($d)->setTime(7 + $d, 30)]);
}
CheckIn::create(['member_id' => $a->id, 'method' => 'card', 'result' => 'rejected', 'reject_reason' => 'Kuota habis', 'scanned_by' => $admin->id, 'checked_in_at' => now()->subDays(3)]);
CheckIn::create(['member_id' => $b->id, 'membership_id' => $mb->id, 'method' => 'card', 'result' => 'accepted', 'scanned_by' => $admin->id, 'checked_in_at' => now()->subDays(4)->setTime(18, 15)]);

$pa = Payment::create(['member_id' => $a->id, 'membership_id' => $ma->id, 'amount' => 200000, 'method' => PaymentMethod::Cash, 'recorded_by' => $admin->id, 'paid_at' => now()->subDays(10)]);
$pb = Payment::create(['member_id' => $b->id, 'membership_id' => $mb->id, 'amount' => 777000, 'method' => PaymentMethod::Cash, 'recorded_by' => $admin->id, 'paid_at' => now()->subDays(10)]);

@mkdir(__DIR__.'/../test-results', 0777, true);
file_put_contents(__DIR__.'/../test-results/panel-member-ids.json', json_encode(['paymentLain' => $pb->id, 'memberLain' => $b->id]));
