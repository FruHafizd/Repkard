<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Pages\ScanCheckIn;
use App\Filament\Resources\CheckIns\Pages\ListCheckIns;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\CardTokenService;
use App\Services\CheckInService;
use App\Services\MemberCardService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private CheckInService $service;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(CheckInService::class);
        $this->staff = $this->user(UserRole::Pegawai);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    /**
     * @param  array<string, mixed>  $membership
     * @return array{0: Member, 1: MemberCard, 2: Membership, 3: string}
     */
    private function siap(array $membership = [], ?int $kuota = null): array
    {
        $member = Member::factory()->create([
            'status' => MemberStatus::Active,
            'member_number' => 'RPK-2611-'.fake()->unique()->numerify('####'),
        ]);
        $card = app(MemberCardService::class)->issue($member, null);
        $ms = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['visit_quota' => $kuota])->id,
            'visits_remaining' => $kuota,
        ], $membership));

        return [$member, $card, $ms, app(CardTokenService::class)->make($card)];
    }

    private function scanTolak(string $token, string $alasan): void
    {
        $result = $this->service->inspect($token);
        $this->assertFalse($result->allowed);
        $this->assertSame($alasan, $result->reason);

        $this->service->reject($result, $this->staff, $result->reason);
        $this->assertDatabaseHas('check_ins', ['result' => 'rejected', 'reject_reason' => $alasan]);
    }

    public function test_token_tidak_valid_ditolak_dan_tersimpan_tanpa_member(): void
    {
        $this->scanTolak('sampah', 'Kartu tidak valid atau sudah tidak berlaku');
        $this->assertNull(CheckIn::first()->member_id);
    }

    public function test_member_tidak_aktif_ditolak(): void
    {
        [$member, , , $token] = $this->siap();
        $member->update(['status' => MemberStatus::Inactive]);

        $this->scanTolak($token, 'Akun member tidak aktif');
    }

    public function test_masa_aktif_habis_ditolak(): void
    {
        [, , , $token] = $this->siap(['starts_on' => now()->subDays(40)->toDateString(), 'ends_on' => now()->subDays(10)->toDateString()]);

        $this->scanTolak($token, 'Masa aktif sudah habis');
    }

    public function test_membership_dibekukan_ditolak(): void
    {
        [, , , $token] = $this->siap(['frozen_from' => now()->subDay()->toDateString(), 'frozen_until' => now()->addDays(5)->toDateString()]);

        $this->scanTolak($token, 'Membership sedang dibekukan');
    }

    public function test_kuota_habis_ditolak(): void
    {
        [, , $ms, $token] = $this->siap(kuota: 3);
        $ms->update(['visits_remaining' => 0]);

        $this->scanTolak($token, 'Kuota kunjungan habis');
    }

    public function test_inspect_tidak_menulis_ke_database(): void
    {
        [, , , $token] = $this->siap(kuota: 5);

        $tabel = ['check_ins', 'memberships', 'member_cards', 'members'];
        $sebelum = array_map(fn ($t) => \DB::table($t)->count(), $tabel);
        $kuota = Membership::first()->visits_remaining;

        $this->service->inspect($token);
        $this->service->inspect('sampah');

        $this->assertSame($sebelum, array_map(fn ($t) => \DB::table($t)->count(), $tabel));
        $this->assertSame($kuota, Membership::first()->visits_remaining);
    }

    public function test_konfirmasi_paket_kuota_mengurangi_sisa(): void
    {
        [, , $ms, $token] = $this->siap(kuota: 10);

        $checkIn = $this->service->confirm($this->service->inspect($token), $this->staff);

        $this->assertSame('accepted', $checkIn->result);
        $this->assertSame(9, $ms->fresh()->visits_remaining);
    }

    public function test_paket_tanpa_batas_tetap_null(): void
    {
        [, , $ms, $token] = $this->siap();

        $this->service->confirm($this->service->inspect($token), $this->staff);

        $this->assertNull($ms->fresh()->visits_remaining);
        $this->assertDatabaseHas('check_ins', ['result' => 'accepted', 'method' => 'card']);
    }

    public function test_kuota_satu_dikonfirmasi_dua_kali_yang_kedua_ditolak(): void
    {
        [, , $ms, $token] = $this->siap(kuota: 1);
        $result = $this->service->inspect($token);

        $pertama = $this->service->confirm($result, $this->staff);
        $kedua = $this->service->confirm($result, $this->staff);

        $this->assertSame('accepted', $pertama->result);
        $this->assertSame('rejected', $kedua->result);
        $this->assertSame('Kuota kunjungan habis', $kedua->reject_reason);
        $this->assertSame(0, $ms->fresh()->visits_remaining);
    }

    public function test_kartu_dicabut_antara_inspect_dan_confirm_ditolak(): void
    {
        [, $card, $ms, $token] = $this->siap(kuota: 5);
        $result = $this->service->inspect($token);
        $this->assertTrue($result->allowed);

        app(MemberCardService::class)->revoke($card, $this->staff, 'hilang');

        $checkIn = $this->service->confirm($result, $this->staff);

        $this->assertSame('rejected', $checkIn->result);
        $this->assertSame(5, $ms->fresh()->visits_remaining);
    }

    public function test_token_versi_lama_setelah_terbit_ulang_ditolak(): void
    {
        [$member, , , $token] = $this->siap();
        app(MemberCardService::class)->issue($member, $this->staff);

        $this->scanTolak($token, 'Kartu tidak valid atau sudah tidak berlaku');
    }

    public function test_wajah_tidak_cocok_tersimpan_rejected(): void
    {
        [, , $ms, $token] = $this->siap(kuota: 5);

        Livewire::actingAs($this->staff)->test(ScanCheckIn::class)
            ->call('scan', $token)
            ->assertSee('Wajah cocok')
            ->call('tolakWajah')
            ->assertSee('Wajah tidak cocok');

        $this->assertDatabaseHas('check_ins', ['result' => 'rejected', 'reject_reason' => 'Wajah tidak cocok']);
        $this->assertSame(5, $ms->fresh()->visits_remaining);
    }

    public function test_alur_scan_lalu_konfirmasi_lewat_halaman(): void
    {
        [, , $ms, $token] = $this->siap(kuota: 5);

        Livewire::actingAs($this->staff)->test(ScanCheckIn::class)
            ->call('scan', $token)
            ->call('konfirmasi');

        $this->assertSame(4, $ms->fresh()->visits_remaining);
        $this->assertDatabaseHas('check_ins', ['result' => 'accepted', 'scanned_by' => $this->staff->id]);
    }

    public function test_scan_ditolak_lewat_halaman_langsung_tersimpan(): void
    {
        Livewire::actingAs($this->staff)->test(ScanCheckIn::class)
            ->call('scan', 'sampah')
            ->assertSee('Kartu tidak valid');

        $this->assertDatabaseHas('check_ins', ['result' => 'rejected', 'member_id' => null]);
    }

    public function test_rate_limit_scan(): void
    {
        $page = Livewire::actingAs($this->staff)->test(ScanCheckIn::class);

        for ($i = 0; $i < 61; $i++) {
            $page->call('scan', 'sampah');
        }

        $this->assertSame(60, CheckIn::count());
    }

    public function test_check_in_manual_tanpa_alasan_gagal_validasi(): void
    {
        [$member] = $this->siap();

        Livewire::actingAs($this->staff)->test(ScanCheckIn::class)
            ->callAction('manual', ['member_id' => $member->id, 'reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame(0, CheckIn::count());
    }

    public function test_check_in_manual_dengan_alasan_berhasil(): void
    {
        [$member, , $ms] = $this->siap(kuota: 4);

        Livewire::actingAs($this->staff)->test(ScanCheckIn::class)
            ->callAction('manual', ['member_id' => $member->id, 'reason' => 'Kartu ketinggalan'])
            ->assertSee('Wajah cocok')
            ->call('konfirmasi');

        $this->assertDatabaseHas('check_ins', [
            'member_id' => $member->id,
            'method' => 'manual',
            'result' => 'accepted',
            'manual_reason' => 'Kartu ketinggalan',
        ]);
        $this->assertSame(3, $ms->fresh()->visits_remaining);
    }

    public function test_pegawai_tidak_bisa_ubah_hapus_riwayat_owner_bisa(): void
    {
        $checkIn = CheckIn::factory()->create();
        $owner = $this->user(UserRole::Owner);

        $this->assertFalse($this->staff->can('update', $checkIn));
        $this->assertFalse($this->staff->can('delete', $checkIn));
        $this->assertTrue($this->staff->can('viewAny', CheckIn::class));
        $this->assertTrue($this->staff->can('view', $checkIn));
        $this->assertTrue($owner->can('update', $checkIn));
        $this->assertTrue($owner->can('delete', $checkIn));
    }

    public function test_member_hanya_bisa_melihat_check_in_miliknya(): void
    {
        $akun = $this->user(UserRole::Member);
        $milik = CheckIn::factory()->create(['member_id' => Member::factory()->create(['user_id' => $akun->id])->id]);
        $lain = CheckIn::factory()->create();

        $this->assertTrue($akun->can('view', $milik));
        $this->assertFalse($akun->can('view', $lain));
        $this->assertFalse($akun->can('update', $milik));
    }

    public function test_halaman_scan_akses_per_role(): void
    {
        $url = ScanCheckIn::getUrl(panel: 'admin');

        $this->actingAs($this->user(UserRole::Pegawai))->get($url)->assertOk();
        $this->actingAs($this->user(UserRole::Owner))->get($url)->assertOk();
        $this->actingAs($this->user(UserRole::Member))->get($url)->assertForbidden();
    }

    public function test_riwayat_check_in_tampil_untuk_pegawai(): void
    {
        $checkIn = CheckIn::factory()->create();

        Livewire::actingAs($this->staff)->test(ListCheckIns::class)
            ->assertCanSeeTableRecords([$checkIn]);
    }
}
