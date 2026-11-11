<?php

// Data tambahan khusus E2E: pegawai dengan password sementara.
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

User::factory()->create([
    'name' => 'Pegawai Baru',
    'email' => 'pegawai-baru@repkard.test',
    'password' => 'sementara123',
    'must_change_password' => true,
])->assignRole(UserRole::Pegawai->value);
