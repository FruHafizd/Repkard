<?php

// Data E2E check-in: member aktif dengan berbagai kondisi. Token kartu ditulis ke test-results/checkin-tokens.json.
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\CardTokenService;
use App\Services\MemberCardService;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$plan = MembershipPlan::where('is_active', true)->orderBy('sort_order')->firstOrFail();

$skenario = [
    'valid' => ['name' => 'Budi Valid', 'kuota' => 2],
    'foto' => ['name' => 'Foto Kartu', 'kuota' => null],
    'habis' => ['name' => 'Ani Habis', 'kuota' => null, 'starts' => -35, 'ends' => -5],
    'beku' => ['name' => 'Beku Bayu', 'kuota' => null, 'beku' => true],
    'kuota0' => ['name' => 'Kuota Nol', 'kuota' => 0],
    'manual' => ['name' => 'Manual Mawar', 'kuota' => null],
];

$tokens = [];
$n = 0;
foreach ($skenario as $key => $s) {
    $n++;
    $user = User::factory()->create(['name' => $s['name'], 'email' => "checkin{$n}@example.com"])
        ->assignRole(UserRole::Member->value);
    $path = "member-photos/e2e-checkin-{$n}.png";
    Storage::disk('local')->put($path, $png);
    $member = $user->member()->create([
        'phone' => '08140000000'.$n,
        'gender' => 'L',
        'photo_path' => $path,
        'status' => MemberStatus::Active,
    ]);
    $member->forceFill(['member_number' => sprintf('RPK-2611-%04d', 900 + $n)])->save();
    $beku = $s['beku'] ?? false;
    $member->memberships()->create([
        'membership_plan_id' => $plan->id,
        'starts_on' => now()->addDays($s['starts'] ?? 0)->toDateString(),
        'ends_on' => now()->addDays($s['ends'] ?? 29)->toDateString(),
        'visits_remaining' => $s['kuota'],
        'frozen_from' => $beku ? now()->subDay()->toDateString() : null,
        'frozen_until' => $beku ? now()->addDays(5)->toDateString() : null,
    ]);
    $card = app(MemberCardService::class)->issue($member, null);
    $tokens[$key] = app(CardTokenService::class)->make($card);

    if ($key === 'foto') {
        $qr = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => 10,
            'outputBase64' => false,
        ])))->render($tokens[$key]);
        @mkdir(__DIR__.'/../test-results', 0777, true);
        file_put_contents(__DIR__.'/../test-results/kartu-foto.png', $qr);
    }
}

@mkdir(__DIR__.'/../test-results', 0777, true);
file_put_contents(__DIR__.'/../test-results/checkin-tokens.json', json_encode($tokens));
