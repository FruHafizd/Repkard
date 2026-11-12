<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Pages\MemberMenghilang;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\MemberFollowUp;
use App\Models\Membership;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberMenghilangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    /** Member aktif dengan membership berlaku; check-in accepted terakhir $hariLalu hari lalu (null = belum pernah). */
    private function member(?int $hariLalu, array $membership = [], int $diaktifkanHariLalu = 60): Member
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active]);
        $member->forceFill(['activated_at' => now()->subDays($diaktifkanHariLalu)])->save();

        Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'starts_on' => now()->subDays(40)->toDateString(),
            'ends_on' => now()->addDays(20)->toDateString(),
        ], $membership));

        if ($hariLalu !== null) {
            CheckIn::factory()->create([
                'member_id' => $member->id,
                'checked_in_at' => now()->subDays($hariLalu),
            ]);
        }

        return $member;
    }

    private function ids(int $days = 14): array
    {
        return Member::query()->mulaiMenghilang($days)->pluck('members.id')->all();
    }

    public function test_checkin_terakhir_15_hari_lalu_masuk_dan_13_hari_tidak(): void
    {
        $masuk = $this->member(15);
        $tidak = $this->member(13);

        $ids = $this->ids();

        $this->assertContains($masuk->id, $ids);
        $this->assertNotContains($tidak->id, $ids);

        $row = Member::query()->mulaiMenghilang(14)->find($masuk->id);
        $this->assertSame(15, $row->days_absent);
        $this->assertTrue($row->last_visit_at->isSameDay(now()->subDays(15)));
    }

    public function test_belum_pernah_checkin_dihitung_dari_tanggal_aktivasi(): void
    {
        $member = $this->member(null, diaktifkanHariLalu: 20);
        $baru = $this->member(null, diaktifkanHariLalu: 5);

        $this->assertContains($member->id, $this->ids());
        $this->assertNotContains($baru->id, $this->ids());
        $this->assertSame(20, Member::query()->mulaiMenghilang(14)->find($member->id)->days_absent);
    }

    public function test_checkin_ditolak_tidak_dihitung_sebagai_kunjungan(): void
    {
        $member = $this->member(20);
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'result' => CheckIn::RESULT_REJECTED,
            'checked_in_at' => now()->subDays(2),
        ]);

        $this->assertContains($member->id, $this->ids());
        $this->assertSame(20, Member::query()->mulaiMenghilang(14)->find($member->id)->days_absent);
    }

    public function test_membership_dibekukan_hari_ini_tidak_masuk(): void
    {
        $beku = $this->member(20, [
            'frozen_from' => now()->subDays(2)->toDateString(),
            'frozen_until' => now()->addDays(5)->toDateString(),
        ]);
        $bekuLewat = $this->member(20, [
            'frozen_from' => now()->subDays(10)->toDateString(),
            'frozen_until' => now()->subDays(5)->toDateString(),
        ]);

        $ids = $this->ids();

        $this->assertNotContains($beku->id, $ids);
        $this->assertContains($bekuLewat->id, $ids);
    }

    public function test_membership_habis_atau_dibatalkan_tidak_masuk(): void
    {
        $habis = $this->member(20, ['starts_on' => now()->subDays(60)->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);
        $batal = $this->member(20, ['cancelled_at' => now()->subDay()]);

        $ids = $this->ids();

        $this->assertNotContains($habis->id, $ids);
        $this->assertNotContains($batal->id, $ids);
    }

    public function test_kuota_habis_tidak_masuk_tetapi_kuota_tersisa_masuk(): void
    {
        $nol = $this->member(20, ['visits_remaining' => 0]);
        $sisa = $this->member(20, ['visits_remaining' => 3]);

        $ids = $this->ids();

        $this->assertNotContains($nol->id, $ids);
        $this->assertContains($sisa->id, $ids);
    }

    public function test_member_tidak_aktif_tidak_masuk(): void
    {
        $member = $this->member(20);
        $member->update(['status' => MemberStatus::Inactive]);

        $this->assertNotContains($member->id, $this->ids());
    }

    public function test_dihubungi_3_hari_lalu_hanya_tampil_saat_filter_dimatikan(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $dihubungi = $this->member(20);
        $belum = $this->member(20);
        MemberFollowUp::factory()->create(['member_id' => $dihubungi->id, 'contacted_at' => now()->subDays(3)]);

        // Filter "belum dihubungi 7 hari" aktif secara default.
        Livewire::test(MemberMenghilang::class)
            ->assertCanSeeTableRecords([$belum])
            ->assertCanNotSeeTableRecords([$dihubungi])
            ->removeTableFilter('belum_dihubungi')
            ->assertCanSeeTableRecords([$belum, $dihubungi]);
    }

    public function test_filter_ambang_bahaya(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $bahaya = $this->member(31);
        $peringatan = $this->member(20);

        Livewire::test(MemberMenghilang::class)
            ->filterTable('ambang_bahaya', true)
            ->assertCanSeeTableRecords([$bahaya])
            ->assertCanNotSeeTableRecords([$peringatan]);
    }

    public function test_urut_default_absen_terlama_dulu(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $sedang = $this->member(20);
        $lama = $this->member(40);
        $baru = $this->member(15);

        Livewire::test(MemberMenghilang::class)
            ->assertCanSeeTableRecords([$lama, $sedang, $baru], inOrder: true);
    }

    public function test_mencatat_tindak_lanjut_menyimpan_pencatat(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $this->actingAs($pegawai);
        $member = $this->member(20);

        Livewire::test(MemberMenghilang::class)
            ->callTableAction('catatTindakLanjut', $member, [
                'channel' => 'telepon',
                'outcome' => 'sibuk',
                'note' => 'Sedang lembur',
                'next_contact_on' => now()->addDays(3)->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('member_follow_ups', [
            'member_id' => $member->id,
            'user_id' => $pegawai->id,
            'channel' => 'telepon',
            'outcome' => 'sibuk',
        ]);
        $this->assertTrue($member->latestFollowUp->contacted_at->equalTo(now()));
    }

    public function test_tanggal_hubungi_lagi_tidak_boleh_kemarin(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $member = $this->member(20);

        Livewire::test(MemberMenghilang::class)
            ->callTableAction('catatTindakLanjut', $member, [
                'channel' => 'chat',
                'outcome' => 'sakit',
                'next_contact_on' => now()->subDay()->toDateString(),
            ])
            ->assertHasTableActionErrors(['next_contact_on']);

        $this->assertDatabaseCount('member_follow_ups', 0);
    }

    public function test_pegawai_bisa_membuka_halaman_dan_member_ditolak(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai))
            ->get(MemberMenghilang::getUrl())
            ->assertOk();

        $this->actingAs($this->user(UserRole::Member))
            ->get(MemberMenghilang::getUrl())
            ->assertForbidden();

        auth()->logout();
        $this->get(MemberMenghilang::getUrl())->assertRedirect();
    }

    public function test_pegawai_tanpa_izin_tidak_bisa_membuka_halaman(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        Role::findByName(UserRole::Pegawai->value)->revokePermissionTo('View:MemberMenghilang');

        $this->actingAs($pegawai)
            ->get(MemberMenghilang::getUrl())
            ->assertForbidden();
    }

    public function test_stat_dashboard_menghitung_member_mulai_menghilang(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $this->member(20);
        $this->member(30);
        $this->member(3);

        Livewire::test(AdminStatsOverview::class)
            ->assertSee('Mulai menghilang')
            ->assertSeeHtml(MemberMenghilang::getUrl());

        $this->assertCount(2, $this->ids());
    }

    public function test_jumlah_query_tidak_bertambah_per_baris(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));

        $pertama = $this->member(20);
        MemberFollowUp::factory()->create(['member_id' => $pertama->id, 'contacted_at' => now()->subDays(10)]);
        $this->hitungQuery(); // pemanasan: cache permission terisi
        $kecil = $this->hitungQuery();

        foreach (range(1, 29) as $i) {
            $member = $this->member(20 + $i);
            MemberFollowUp::factory()->create(['member_id' => $member->id, 'contacted_at' => now()->subDays(10)]);
        }
        $besar = $this->hitungQuery();

        $this->assertSame($kecil, $besar);
    }

    private function hitungQuery(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(MemberMenghilang::class)->assertSuccessful();

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }
}
