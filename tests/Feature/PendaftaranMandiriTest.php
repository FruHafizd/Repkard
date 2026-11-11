<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Member\Pages\Auth\Register;
use App\Filament\Member\Pages\LengkapiProfil;
use App\Filament\Member\Widgets\PendingActivationWidget;
use App\Filament\Pages\GantiPassword;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PendaftaranMandiriTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel('member');
    }

    private function memberBaru(array $userAttrs = [], array $memberAttrs = []): User
    {
        $user = User::factory()->create($userAttrs)->assignRole(UserRole::Member->value);
        Member::factory()->for($user)->create($memberAttrs + ['photo_path' => 'member-photos/x.jpg']);

        return $user;
    }

    public function test_landing_hanya_menampilkan_paket_aktif(): void
    {
        $this->withoutVite();
        MembershipPlan::factory()->create(['name' => 'Paket Aktif', 'price' => 200000]);
        MembershipPlan::factory()->create(['name' => 'Paket Mati', 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Paket Aktif')
            ->assertSee('Rp 200.000')
            ->assertDontSee('Paket Mati');
    }

    public function test_registrasi_membuat_user_member_dan_member_pending(): void
    {
        $plan = MembershipPlan::factory()->create();
        Filament::setCurrentPanel('member');

        Livewire::withQueryParams(['plan' => $plan->id])
            ->test(Register::class)
            ->assertFormSet(['selected_plan_id' => $plan->id])
            ->fillForm([
                'name' => 'Budi',
                'email' => 'budi@example.com',
                'phone' => '081234567890',
                'password' => 'rahasia123',
                'passwordConfirmation' => 'rahasia123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(UserRole::Member->value));
        $this->assertNull($user->email_verified_at);
        $this->assertSame(MemberStatus::Pending, $user->member->status);
        $this->assertSame($plan->id, $user->member->selected_plan_id);
        $this->assertSame('081234567890', $user->member->phone);
    }

    public function test_registrasi_menolak_paket_nonaktif(): void
    {
        $mati = MembershipPlan::factory()->create(['is_active' => false]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Budi',
                'email' => 'budi@example.com',
                'phone' => '081234567890',
                'password' => 'rahasia123',
                'passwordConfirmation' => 'rahasia123',
                'selected_plan_id' => $mati->id,
            ])
            ->call('register')
            ->assertHasFormErrors(['selected_plan_id']);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_user_belum_verifikasi_diarahkan_ke_halaman_verifikasi(): void
    {
        $user = $this->memberBaru(['email_verified_at' => null], ['gender' => null, 'photo_path' => null]);

        $this->actingAs($user)->get('/member')->assertRedirect('/member/email-verification/prompt');
    }

    public function test_member_tanpa_foto_diarahkan_lengkapi_profil_lalu_melihat_widget_pending(): void
    {
        $plan = MembershipPlan::factory()->create(['name' => 'Bulanan Uji', 'price' => 200000]);
        $user = $this->memberBaru([], ['gender' => null, 'photo_path' => null, 'selected_plan_id' => $plan->id]);
        $this->actingAs($user);

        $this->get('/member')->assertRedirect(LengkapiProfil::getUrl());
        $this->get(LengkapiProfil::getUrl())->assertOk();

        Livewire::test(LengkapiProfil::class)
            ->fillForm(['gender' => 'L', 'photo' => UploadedFile::fake()->image('f.jpg', 600, 800)])
            ->call('simpan');

        $user->member->refresh();
        $this->assertSame('L', $user->member->gender);
        $this->assertNotNull($user->member->photo_path);

        $this->get('/member')->assertOk()->assertSeeLivewire(PendingActivationWidget::class);

        Livewire::test(PendingActivationWidget::class)
            ->assertSee('Akun Anda menunggu aktivasi')
            ->assertSee('Bulanan Uji')
            ->assertSee('Rp 200.000');
    }

    public function test_profil_lengkap_tidak_bisa_dibuka_lagi_untuk_ganti_foto(): void
    {
        $user = $this->memberBaru();

        $this->actingAs($user)->get(LengkapiProfil::getUrl())->assertRedirect('/member');
    }

    public function test_member_pending_bisa_ganti_paket_ke_paket_aktif(): void
    {
        $lama = MembershipPlan::factory()->create();
        $baru = MembershipPlan::factory()->create();
        $mati = MembershipPlan::factory()->create(['is_active' => false]);
        $user = $this->memberBaru([], ['selected_plan_id' => $lama->id]);
        $this->actingAs($user);

        Livewire::test(PendingActivationWidget::class)->set('planId', $baru->id)->call('gantiPaket');
        $this->assertSame($baru->id, $user->member->fresh()->selected_plan_id);

        Livewire::test(PendingActivationWidget::class)->set('planId', $mati->id)->call('gantiPaket');
        $this->assertSame($baru->id, $user->member->fresh()->selected_plan_id);
    }

    public function test_member_aktif_tidak_bisa_ganti_paket(): void
    {
        $lama = MembershipPlan::factory()->create();
        $baru = MembershipPlan::factory()->create();
        $user = $this->memberBaru([], ['selected_plan_id' => $lama->id]);
        $this->actingAs($user);

        $widget = Livewire::test(PendingActivationWidget::class)->set('planId', $baru->id);

        $user->member->update(['status' => MemberStatus::Active]);
        $this->assertFalse(PendingActivationWidget::canView());

        $widget->call('gantiPaket')->assertForbidden();
        $this->assertSame($lama->id, $user->member->fresh()->selected_plan_id);
    }

    public function test_pegawai_wajib_ganti_password_sebelum_membuka_halaman_lain(): void
    {
        Filament::setCurrentPanel('admin');
        $pegawai = User::factory()->create(['must_change_password' => true])->assignRole(UserRole::Pegawai->value);
        $this->actingAs($pegawai);

        $this->get('/admin')->assertRedirect('/admin/ganti-password');
        $this->get('/admin/members')->assertRedirect('/admin/ganti-password');
        $this->get('/admin/ganti-password')->assertOk();
    }

    public function test_member_wajib_ganti_password_sebelum_membuka_halaman_lain(): void
    {
        $user = $this->memberBaru(['must_change_password' => true]);
        $this->actingAs($user);

        $this->get('/member')->assertRedirect('/member/ganti-password');
        $this->get('/member/ganti-password')->assertOk();
    }

    public function test_ganti_password_berhasil_menghapus_penanda(): void
    {
        $user = $this->memberBaru(['must_change_password' => true]);
        $this->actingAs($user);

        Livewire::test(GantiPassword::class)
            ->fillForm(['current_password' => 'password', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1'])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('passwordbaru1', $user->password));
        $this->get('/member')->assertOk();
    }

    public function test_ganti_password_menolak_password_lama_salah(): void
    {
        $user = $this->memberBaru(['must_change_password' => true]);
        $this->actingAs($user);

        Livewire::test(GantiPassword::class)
            ->fillForm(['current_password' => 'salah', 'password' => 'passwordbaru1', 'password_confirmation' => 'passwordbaru1'])
            ->call('simpan')
            ->assertHasFormErrors(['current_password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
