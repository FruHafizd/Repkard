<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\AkanHabisTable;
use App\Filament\Widgets\CheckInHariIniTable;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userDenganRole(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function member(MemberStatus $status): Member
    {
        return Member::factory()->create(['status' => $status]);
    }

    private function membership(Member $member, int $endsInDays, ?string $cancelled = null): Membership
    {
        return Membership::factory()->for($member)->create([
            'ends_on' => now()->addDays($endsInDays)->toDateString(),
            'cancelled_at' => $cancelled,
        ]);
    }

    /** @return array<string, mixed> */
    private function statistik(): array
    {
        $widget = new AdminStatsOverview;
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        $hasil = [];
        foreach ($method->invoke($widget) as $stat) {
            $hasil[$stat->getLabel()] = $stat->getValue();
        }

        return $hasil;
    }

    private function skenarioDataUji(): array
    {
        $aktif1 = $this->member(MemberStatus::Active);
        $this->membership($aktif1, 29);

        $aktif2 = $this->member(MemberStatus::Active);
        $this->membership($aktif2, 29);

        $habis3 = $this->member(MemberStatus::Active);
        $membershipHabis3 = $this->membership($habis3, 3);

        $this->member(MemberStatus::Pending);

        $habis8 = $this->member(MemberStatus::Active);
        $membershipHabis8 = $this->membership($habis8, 8);

        $checkIns = [
            CheckIn::factory()->create(['member_id' => $aktif1->id, 'checked_in_at' => now()]),
            CheckIn::factory()->create(['member_id' => $aktif2->id, 'checked_in_at' => now()->subHour()]),
            CheckIn::factory()->create(['member_id' => $habis3->id, 'checked_in_at' => now()]),
        ];
        CheckIn::factory()->create(['member_id' => $aktif1->id, 'checked_in_at' => now()->subDay()]);

        return compact('habis3', 'membershipHabis3', 'membershipHabis8', 'checkIns');
    }

    public function test_angka_statistik_dihitung_tepat(): void
    {
        $this->skenarioDataUji();

        $statistik = $this->statistik();

        // Member aktif = 2 (30 hari) + 1 (habis 3 hari) + 1 (habis 8 hari, masih berlaku hari ini).
        $this->assertSame(4, $statistik['Member aktif']);
        $this->assertSame(1, $statistik['Menunggu aktivasi']);
        $this->assertSame(1, $statistik['Habis dalam 7 hari']);
        $this->assertSame(3, $statistik['Check-in hari ini']);
    }

    public function test_member_aktif_tanpa_membership_berlaku_tidak_dihitung(): void
    {
        $this->member(MemberStatus::Active);

        $this->assertSame(0, $this->statistik()['Member aktif']);
    }

    public function test_tabel_akan_habis_hanya_berisi_membership_dalam_7_hari(): void
    {
        $data = $this->skenarioDataUji();

        Livewire::actingAs($this->userDenganRole(UserRole::Pegawai))->test(AkanHabisTable::class)
            ->assertCanSeeTableRecords([$data['membershipHabis3']])
            ->assertCanNotSeeTableRecords([$data['membershipHabis8']]);
    }

    public function test_tabel_check_in_hanya_menampilkan_hari_ini(): void
    {
        $data = $this->skenarioDataUji();

        Livewire::actingAs($this->userDenganRole(UserRole::Pegawai))->test(CheckInHariIniTable::class)
            ->assertCanSeeTableRecords($data['checkIns'])
            ->assertCountTableRecords(3);
    }

    public function test_owner_dan_pegawai_bisa_membuka_dashboard_tanpa_angka_rupiah(): void
    {
        $this->skenarioDataUji();

        foreach ([UserRole::Owner, UserRole::Pegawai] as $role) {
            $this->actingAs($this->userDenganRole($role));

            $this->get('/admin')
                ->assertOk()
                ->assertDontSee('Rp ');

            Livewire::test(Dashboard::class)
                ->assertSeeLivewire(AdminStatsOverview::class)
                ->assertSeeLivewire(AkanHabisTable::class)
                ->assertSeeLivewire(CheckInHariIniTable::class)
                ->assertDontSee('Rp ');
        }
    }

    public function test_member_tidak_bisa_membuka_dashboard_admin(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Member));

        $this->get('/admin')->assertForbidden();
    }
}
