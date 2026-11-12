<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\WalkIns\Pages\ListWalkIns;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalkIn;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class PendatangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function pemasukanHariIni(User $user): string
    {
        $this->actingAs($user);
        $widget = new AdminStatsOverview;
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        foreach ($method->invoke($widget) as $stat) {
            if ($stat->getLabel() === 'Pemasukan hari ini') {
                return $stat->getValue();
            }
        }

        $this->fail('Stat pemasukan tidak ada');
    }

    public function test_pegawai_bisa_mencatat_pendatang(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        Livewire::actingAs($pegawai)
            ->test(ListWalkIns::class)
            ->callAction('create', ['name' => 'Budi', 'amount' => 25000, 'method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('walk_ins', [
            'name' => 'Budi',
            'amount' => 25000,
            'recorded_by' => $pegawai->id,
        ]);
    }

    public function test_pemasukan_hari_ini_menjumlah_member_dan_pendatang_tanpa_yang_dibatalkan(): void
    {
        $owner = $this->user(UserRole::Owner);

        Payment::factory()->create(['amount' => 200000, 'paid_at' => now()]);
        Payment::factory()->create(['amount' => 999999, 'paid_at' => now(), 'cancelled_at' => now()]);
        Payment::factory()->create(['amount' => 777777, 'paid_at' => now()->subDay()]);
        WalkIn::factory()->create(['amount' => 25000]);
        WalkIn::factory()->create(['amount' => 30000, 'cancelled_at' => now()]);
        WalkIn::factory()->create(['amount' => 555555, 'visited_at' => now()->subDay()]);

        $this->assertSame('Rp 225.000', $this->pemasukanHariIni($owner));
    }

    public function test_pegawai_hanya_melihat_pemasukan_yang_dicatat_sendiri(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        WalkIn::factory()->create(['amount' => 25000, 'recorded_by' => $pegawai->id]);
        WalkIn::factory()->create(['amount' => 40000]);
        Payment::factory()->create(['amount' => 200000]);

        $this->assertSame('Rp 25.000', $this->pemasukanHariIni($pegawai));
    }

    public function test_hanya_owner_bisa_membatalkan_catatan_pendatang(): void
    {
        $walkIn = WalkIn::factory()->create();

        $this->assertFalse($this->user(UserRole::Pegawai)->can('cancel', $walkIn));
        $this->assertTrue($this->user(UserRole::Owner)->can('cancel', $walkIn));
    }

    public function test_pembatalan_mengeluarkan_pendatang_dari_pemasukan(): void
    {
        $owner = $this->user(UserRole::Owner);
        $walkIn = WalkIn::factory()->create(['amount' => 25000]);

        Livewire::actingAs($owner)
            ->test(ListWalkIns::class)
            ->callAction(TestAction::make('batalkan')->table($walkIn), ['reason' => 'Salah input'])
            ->assertHasNoActionErrors();

        $this->assertNotNull($walkIn->fresh()->cancelled_at);
        $this->assertSame('Rp 0', $this->pemasukanHariIni($owner));
    }
}
