<?php

// Data E2E pengumuman: owner membuat beberapa pengumuman (tayang, terjadwal, berakhir, landing, XSS).
use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$owner = User::role(UserRole::Owner->value)->firstOrFail();

Announcement::factory()->create([
    'title' => 'Gym tutup saat Lebaran',
    'body' => "Tanggal 20-22 Maret, gym tutup.\nTerima kasih.",
    'starts_at' => now()->subDays(2),
    'is_pinned' => true,
    'show_on_landing' => true,
    'created_by' => $owner->id,
]);
Announcement::factory()->create([
    'title' => 'Alat baru datang',
    'body' => 'Leg press baru sudah bisa dipakai.',
    'starts_at' => now()->subDay(),
    'show_on_landing' => true,
    'created_by' => $owner->id,
]);
Announcement::factory()->create([
    'title' => 'Pengumuman lama',
    'body' => 'Sudah berakhir.',
    'starts_at' => now()->subDays(10),
    'ends_at' => now()->subDays(5),
    'created_by' => $owner->id,
]);
Announcement::factory()->create([
    'title' => 'Belum tayang nanti',
    'body' => 'Akan tayang besok.',
    'starts_at' => now()->addDay(),
    'show_on_landing' => true,
    'created_by' => $owner->id,
]);
Announcement::factory()->create([
    'title' => 'Pengumuman milik owner',
    'body' => '<script>alert(1)</script>',
    'starts_at' => now()->subHour(),
    'created_by' => $owner->id,
]);
