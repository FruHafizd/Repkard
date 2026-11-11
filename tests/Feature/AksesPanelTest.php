<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AksesPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userDenganRole(UserRole $role, array $attrs = []): User
    {
        return User::factory()->create($attrs)->assignRole($role->value);
    }

    public function test_owner_bisa_admin_tidak_bisa_member(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));
        $this->get('/admin')->assertOk();
        $this->get('/member')->assertForbidden();
    }

    public function test_pegawai_bisa_admin_tidak_bisa_member(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $this->get('/admin')->assertOk();
        $this->get('/member')->assertForbidden();
    }

    public function test_member_bisa_member_tidak_bisa_admin(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Member));
        $this->get('/member')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_user_nonaktif_ditolak_di_kedua_panel(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner, ['is_active' => false]));
        $this->get('/admin')->assertForbidden();
        $this->get('/member')->assertForbidden();
    }

    public function test_pegawai_bisa_list_tapi_tidak_create_dan_edit(): void
    {
        $pegawai = $this->userDenganRole(UserRole::Pegawai);
        $this->actingAs($pegawai);

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$pegawai->id}/edit")->assertForbidden();
    }

    public function test_owner_membuat_pegawai_baru_dengan_must_change_password(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Budi',
                'email' => 'budi@repkard.test',
                'phone' => '0812345678',
                'role' => UserRole::Pegawai->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'budi@repkard.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('pegawai'));
    }

    public function test_tidak_ada_yang_bisa_menghapus_pegawai(): void
    {
        $target = $this->userDenganRole(UserRole::Pegawai);

        foreach ([UserRole::Owner, UserRole::Pegawai, UserRole::Member] as $role) {
            $user = $this->userDenganRole($role);
            $this->assertFalse($user->can('delete', $target));
            $this->assertFalse($user->can('forceDelete', $target));
        }
    }

    public function test_pegawai_tidak_bisa_membuka_halaman_role(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Pegawai));
        $this->get('/admin/shield/roles')->assertForbidden();
    }

    public function test_owner_bisa_membuka_halaman_role(): void
    {
        $this->actingAs($this->userDenganRole(UserRole::Owner));
        $this->get('/admin/shield/roles')->assertOk();
    }

    public function test_owner_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $owner = $this->userDenganRole(UserRole::Owner);
        $this->actingAs($owner);

        Livewire::test(EditUser::class, ['record' => $owner->id])
            ->fillForm(['is_active' => false])
            ->call('save');

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_daftar_pegawai_tidak_memuat_member(): void
    {
        $member = $this->userDenganRole(UserRole::Member);
        $this->actingAs($this->userDenganRole(UserRole::Owner));

        Livewire::test(ListUsers::class)->assertCanNotSeeTableRecords([$member]);
    }
}
