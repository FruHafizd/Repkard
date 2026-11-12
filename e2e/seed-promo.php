<?php

// Data E2E promo: kode 20% semua paket, kode berkuota habis, dan dua calon member yang membawa kode saat daftar.
use App\Enums\DiscountType;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\MembershipPlan;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$owner = User::role(UserRole::Owner->value)->firstOrFail();
$plan = MembershipPlan::where('is_active', true)->orderBy('sort_order')->firstOrFail();

PromoCode::create([
    'code' => 'E2E20', 'name' => 'Promo E2E 20 persen', 'discount_type' => DiscountType::Percent,
    'discount_value' => 20, 'created_by' => $owner->id,
]);
PromoCode::create([
    'code' => 'E2EHABIS', 'name' => 'Promo E2E kuota habis', 'discount_type' => DiscountType::Fixed,
    'discount_value' => 10000, 'max_uses' => 0, 'created_by' => $owner->id,
]);

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

foreach (['Calon Promo' => ['081400000001', 'E2E20'], 'Calon Habis' => ['081400000002', 'E2EHABIS']] as $name => [$phone, $kode]) {
    $user = User::factory()->create(['name' => $name, 'email' => "promo-{$phone}@example.com"])->assignRole(UserRole::Member->value);
    $path = "member-photos/e2e-{$phone}.png";
    Storage::disk('local')->put($path, $png);
    $user->member()->create([
        'phone' => $phone, 'gender' => 'L', 'photo_path' => $path,
        'status' => MemberStatus::Pending, 'selected_plan_id' => $plan->id, 'requested_promo_code' => $kode,
    ]);
}
