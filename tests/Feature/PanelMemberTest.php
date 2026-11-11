<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Member\Pages\Auth\EditProfile;
use App\Filament\Member\Pages\KartuSaya;
use App\Filament\Member\Resources\CheckIns\Pages\ListCheckIns;
use App\Filament\Member\Resources\Payments\Pages\ListPayments;
use App\Filament\Member\Widgets\MembershipStatusWidget;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Services\CardTokenService;
use App\Services\MemberCardService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PanelMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel('member');
    }

    private function member(MemberStatus $status = MemberStatus::Active, array $attrs = []): Member
    {
        $user = User::factory()->create()->assignRole(UserRole::Member->value);

        return Member::factory()->for($user)->create($attrs + [
            'status' => $status,
            'member_number' => 'RPK-2611-'.fake()->unique()->numerify('####'),
            'photo_path' => 'member-photos/x.jpg',
            'gender' => 'L',
        ]);
    }

    public function test_riwayat_hanya_berisi_data_member_login(): void
    {
        $a = $this->member();
        $b = $this->member();
        $milikA = CheckIn::factory()->create(['member_id' => $a->id]);
        $milikB = CheckIn::factory()->create(['member_id' => $b->id]);
        $ditolakA = CheckIn::factory()->create(['member_id' => $a->id, 'result' => CheckIn::RESULT_REJECTED]);
        $bayarA = Payment::factory()->create(['member_id' => $a->id]);
        $bayarB = Payment::factory()->create(['member_id' => $b->id]);

        $this->actingAs($a->user);

        Livewire::test(ListCheckIns::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB, $ditolakA]);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$bayarA])
            ->assertCanNotSeeTableRecords([$bayarB]);
    }

    public function test_member_tidak_bisa_membuka_data_member_lain_lewat_url_dan_policy(): void
    {
        $a = $this->member();
        $b = $this->member();
        $checkInB = CheckIn::factory()->create(['member_id' => $b->id]);
        $bayarB = Payment::factory()->create(['member_id' => $b->id]);

        $this->actingAs($a->user);

        $this->get(route('payment.receipt', $bayarB))->assertForbidden();
        $this->get(route('member.card.pdf', $b))->assertForbidden();
        $this->assertFalse($a->user->can('view', $checkInB));
        $this->assertFalse($a->user->can('view', $bayarB));
        $this->assertFalse($a->user->can('create', CheckIn::class));
        $this->assertFalse($a->user->can('update', $bayarB));
        $this->assertFalse($a->user->can('delete', $checkInB));
    }

    public function test_member_bisa_mengunduh_kuitansi_sendiri(): void
    {
        $a = $this->member();
        $bayar = Payment::factory()->create(['member_id' => $a->id]);

        $this->actingAs($a->user)->get(route('payment.receipt', $bayar))->assertOk();
    }

    public function test_profil_hanya_mengubah_field_yang_diizinkan(): void
    {
        $member = $this->member(attrs: ['phone' => '0811', 'birth_date' => '1990-01-01']);
        $user = $member->user;
        $namaAsli = $user->name;
        $emailAsli = $user->email;

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Peretas',
                'email' => 'peretas@example.com',
                'gender' => 'P',
                'birth_date' => '2000-01-01',
                'phone' => '0822',
                'address' => 'Jl. Baru 1',
                'emergency_contact_name' => 'Ibu',
                'emergency_contact_phone' => '0833',
                'currentPassword' => 'password',
            ])
            ->set('data.photo_path', 'member-photos/curian.jpg')
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $user->refresh();

        $this->assertSame('0822', $member->phone);
        $this->assertSame('Jl. Baru 1', $member->address);
        $this->assertSame('Ibu', $member->emergency_contact_name);
        $this->assertSame('0833', $member->emergency_contact_phone);
        $this->assertSame($namaAsli, $user->name);
        $this->assertSame($emailAsli, $user->email);
        $this->assertSame('L', $member->gender);
        $this->assertSame('1990-01-01', $member->birth_date->toDateString());
        $this->assertSame('member-photos/x.jpg', $member->photo_path);
    }

    public function test_profil_bisa_ganti_password(): void
    {
        $member = $this->member();
        $this->actingAs($member->user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'phone' => '0811',
                'password' => 'PasswordBaru123!',
                'passwordConfirmation' => 'PasswordBaru123!',
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(\Hash::check('PasswordBaru123!', $member->user->fresh()->password));
    }

    public function test_kartu_saya_member_pending_tanpa_qr(): void
    {
        $member = $this->member(MemberStatus::Pending);
        $this->actingAs($member->user);

        Livewire::test(KartuSaya::class)
            ->assertSee('Kartu tersedia setelah akun diaktifkan')
            ->assertDontSee('Unduh / Cetak PDF')
            ->assertDontSeeHtml('alt="QR kartu"');
    }

    public function test_kartu_saya_member_aktif_menampilkan_qr_yang_bisa_di_resolve(): void
    {
        $member = $this->member();
        $card = app(MemberCardService::class)->issue($member, null);
        $this->actingAs($member->user);

        $html = Livewire::test(KartuSaya::class)
            ->assertSeeHtml('alt="QR kartu"')
            ->assertSee('Unduh / Cetak PDF')
            ->assertSee($member->member_number)
            ->html();

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertTrue(app(CardTokenService::class)->resolve(app(CardTokenService::class)->make($card))->is($card));
    }

    public function test_dashboard_menampilkan_dibekukan_saat_dalam_rentang_beku(): void
    {
        $member = $this->member();
        Membership::factory()->create([
            'member_id' => $member->id,
            'starts_on' => now()->subDays(5)->toDateString(),
            'ends_on' => now()->addDays(30)->toDateString(),
            'frozen_from' => now()->subDay()->toDateString(),
            'frozen_until' => now()->addDays(3)->toDateString(),
        ]);
        $this->actingAs($member->user);

        Livewire::test(MembershipStatusWidget::class)->assertSee('Dibekukan');
    }

    public function test_dashboard_aktif_dan_habis(): void
    {
        $member = $this->member();
        $this->actingAs($member->user);

        Livewire::test(MembershipStatusWidget::class)->assertSee('Habis');

        Membership::factory()->create(['member_id' => $member->id, 'visits_remaining' => 5]);
        CheckIn::factory()->create(['member_id' => $member->id]);

        Livewire::test(MembershipStatusWidget::class)
            ->assertSee('Aktif')
            ->assertSee('Sisa kuota')
            ->assertSee('Kehadiran bulan ini');
    }

    public function test_widget_status_tidak_tampil_untuk_member_pending(): void
    {
        $this->actingAs($this->member(MemberStatus::Pending)->user);

        $this->assertFalse(MembershipStatusWidget::canView());
    }
}
