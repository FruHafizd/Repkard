<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\MembershipPlans\Pages\CreateMembershipPlan;
use App\Filament\Resources\MembershipPlans\Pages\EditMembershipPlan;
use App\Filament\Resources\MembershipPlans\Pages\ListMembershipPlans;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipPlanTest extends TestCase
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

    private function dataPaketValid(array $override = []): array
    {
        return array_merge([
            'name' => 'Mingguan',
            'description' => null,
            'duration_days' => 7,
            'visit_quota' => null,
            'price' => 75000,
            'is_active' => true,
            'sort_order' => 1,
        ], $override);
    }

    public function test_owner_bisa_membuat_dan_mengubah_paket(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(CreateMembershipPlan::class)
            ->fillForm($this->dataPaketValid())
            ->call('create')
            ->assertHasNoFormErrors();

        $paket = MembershipPlan::where('name', 'Mingguan')->firstOrFail();

        Livewire::test(EditMembershipPlan::class, ['record' => $paket->getKey()])
            ->fillForm(['price' => 80000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(80000, $paket->fresh()->price);
    }

    public function test_pegawai_bisa_melihat_daftar_dan_detail_paket_tetapi_tidak_mengubah(): void
    {
        $paket = MembershipPlan::factory()->create();
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));

        $this->get('/admin/membership-plans')->assertOk();
        $this->get("/admin/membership-plans/{$paket->id}")->assertOk();
        $this->get('/admin/membership-plans/create')->assertForbidden();
        $this->get("/admin/membership-plans/{$paket->id}/edit")->assertForbidden();
    }

    public function test_pegawai_tidak_bisa_membuat_atau_mengubah_paket_di_server(): void
    {
        $paket = MembershipPlan::factory()->create();
        $pegawai = $this->userDenganRole(UserRole::Pegawai);

        $this->assertFalse($pegawai->can('create', MembershipPlan::class));
        $this->assertFalse($pegawai->can('update', $paket));
    }

    public function test_member_tidak_bisa_membuka_daftar_paket_di_admin(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Member));

        $this->get('/admin/membership-plans')->assertForbidden();
    }

    public function test_validasi_durasi_kuota_dan_harga(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(CreateMembershipPlan::class)
            ->fillForm($this->dataPaketValid(['duration_days' => 0, 'visit_quota' => 0, 'price' => -1]))
            ->call('create')
            ->assertHasFormErrors(['duration_days', 'visit_quota', 'price']);
    }

    public function test_kuota_kosong_diterima_sebagai_tanpa_batas(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(CreateMembershipPlan::class)
            ->fillForm($this->dataPaketValid(['visit_quota' => null, 'price' => 0]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(MembershipPlan::where('name', 'Mingguan')->firstOrFail()->visit_quota);
    }

    public function test_scope_active_tidak_mengembalikan_paket_nonaktif(): void
    {
        MembershipPlan::factory()->create(['name' => 'Aktif', 'sort_order' => 2]);
        MembershipPlan::factory()->create(['name' => 'Nonaktif', 'is_active' => false, 'sort_order' => 1]);

        $nama = MembershipPlan::active()->pluck('name')->all();

        $this->assertSame(['Aktif'], $nama);
    }

    public function test_paket_tidak_bisa_dihapus_oleh_siapa_pun(): void
    {
        $paket = MembershipPlan::factory()->create();

        foreach ([UserRole::Owner, UserRole::Pegawai, UserRole::Member] as $role) {
            $user = $this->userDenganRole($role);
            $this->assertFalse($user->can('delete', $paket));
            $this->assertFalse($user->can('forceDelete', $paket));
        }
    }

    public function test_daftar_paket_menampilkan_paket_untuk_owner(): void
    {
        $paket = MembershipPlan::factory()->create();
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(ListMembershipPlans::class)->assertCanSeeTableRecords([$paket]);
    }
}
