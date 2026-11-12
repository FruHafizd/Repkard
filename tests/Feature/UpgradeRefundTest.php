<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Refunds\Pages\ManageRefunds;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\CheckInService;
use App\Services\MembershipService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class UpgradeRefundTest extends TestCase
{
    use RefreshDatabase;

    private MembershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(MembershipService::class);
        Carbon::setTestNow('2026-11-11 10:00:00');
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

    private function member(): Member
    {
        return Member::factory()->create(['status' => MemberStatus::Active]);
    }

    private function paket(int $harga, int $hari = 30, ?int $kuota = null): MembershipPlan
    {
        return MembershipPlan::factory()->create(['price' => $harga, 'duration_days' => $hari, 'visit_quota' => $kuota]);
    }

    /** Membership dengan satu pembayaran sebesar harga paket. */
    private function membership(Member $member, MembershipPlan $plan, string $mulai, string $selesai, ?int $sisaKunjungan = null): Membership
    {
        $m = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_on' => $mulai,
            'ends_on' => $selesai,
            'visits_total' => $plan->visit_quota,
            'visits_remaining' => $sisaKunjungan ?? $plan->visit_quota,
        ]);

        Payment::factory()->create(['member_id' => $member->id, 'membership_id' => $m->id, 'amount' => $plan->price]);

        return $m;
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

    public function test_sisa_nilai_prorata_hari(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-01', '2026-11-30');

        $this->assertSame(133333, $this->service->remainingValue($m));
    }

    public function test_sisa_nilai_memakai_rasio_kunjungan_bila_lebih_kecil(): void
    {
        // Sisa 50 dari 60 hari (5/6), sisa 6 dari 10 kunjungan (3/5) → 220.000 × 6/10.
        $m = $this->membership($this->member(), $this->paket(220000, 60, 10), '2026-10-23', '2026-12-21', 6);

        $this->assertSame(132000, $this->service->remainingValue($m));
    }

    public function test_sisa_nilai_penuh_bila_belum_mulai(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-20', '2026-12-19');

        $this->assertSame(200000, $this->service->remainingValue($m));
    }

    public function test_sisa_nilai_nol_bila_dibatalkan_atau_sudah_habis(): void
    {
        $member = $this->member();
        $dibatalkan = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $dibatalkan->update(['cancelled_at' => now()]);
        $habis = $this->membership($member, $this->paket(200000), '2026-09-01', '2026-09-30');

        $this->assertSame(0, $this->service->remainingValue($dibatalkan));
        $this->assertSame(0, $this->service->remainingValue($habis));
    }

    public function test_pembayaran_dibatalkan_tidak_dihitung_dalam_nilai(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-01', '2026-11-30');
        Payment::factory()->create([
            'member_id' => $m->member_id, 'membership_id' => $m->id, 'amount' => 100000, 'cancelled_at' => now(),
        ]);

        $this->assertSame(200000, $this->service->membershipValue($m));
        $this->assertSame(133333, $this->service->remainingValue($m));
    }

    public function test_kredit_dari_paket_lama_ikut_dihitung(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-01', '2026-11-30');
        $m->update(['credit_amount' => 100000]);

        $this->assertSame(200000, $this->service->remainingValue($m->fresh()));
    }

    public function test_upgrade_menghitung_kredit_dan_selisih(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $member = $this->member();
        $lama = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $baru = $this->paket(550000, 90);

        $hasil = $this->service->upgrade($member, $baru, PaymentMethod::Cash, 416667, $pegawai);

        $this->assertSame(133333, $hasil->credit_amount);
        $this->assertSame('2026-11-11', $hasil->starts_on->toDateString());
        $this->assertSame('2027-02-08', $hasil->ends_on->toDateString());

        $payment = Payment::where('membership_id', $hasil->id)->sole();
        $this->assertSame(PaymentType::Upgrade, $payment->type);
        $this->assertSame(416667, $payment->amount);
        $this->assertSame(550000, $payment->original_amount);
        $this->assertSame('Kredit dari paket lama: Rp 133.333', $payment->note);
        $this->assertSame($pegawai->id, $payment->recorded_by);
        $this->assertSame(PaymentType::Purchase, Payment::where('membership_id', $lama->id)->sole()->type);
    }

    public function test_setelah_upgrade_membership_baru_berjalan_dan_lama_dibatalkan(): void
    {
        $member = $this->member();
        $lama = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $baru = $this->paket(550000, 90);

        $hasil = $this->service->upgrade($member, $baru, PaymentMethod::Cash, 416667, $this->user(UserRole::Pegawai));

        $this->assertTrue($member->currentMembership()->is($hasil));
        $lama->refresh();
        $this->assertNotNull($lama->cancelled_at);
        $this->assertSame('Diupgrade ke '.$baru->name, $lama->cancel_reason);
        $this->assertSame($hasil->id, $lama->replaced_by_id);
    }

    public function test_upgrade_ke_paket_lebih_murah_atau_sama_ditolak(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');

        foreach ([200000, 150000] as $harga) {
            try {
                $this->service->upgrade($member, $this->paket($harga), PaymentMethod::Cash, 0, $this->user(UserRole::Pegawai));
                $this->fail('Upgrade seharusnya ditolak');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('gunakan Perpanjang', $e->getMessage());
            }
        }

        $this->assertSame(1, $member->memberships()->count());
    }

    public function test_upgrade_ditolak_bila_ada_perpanjangan_belum_mulai(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $this->membership($member, $this->paket(200000), '2026-12-01', '2026-12-30');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ada perpanjangan yang belum dimulai');

        $this->service->upgrade($member, $this->paket(550000, 90), PaymentMethod::Cash, 0, $this->user(UserRole::Pegawai));
    }

    public function test_upgrade_ditolak_saat_dibekukan(): void
    {
        $member = $this->member();
        $m = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $m->update(['frozen_from' => '2026-11-10', 'frozen_until' => '2026-11-15']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dibekukan');

        $this->service->upgrade($member, $this->paket(550000, 90), PaymentMethod::Cash, 0, $this->user(UserRole::Pegawai));
    }

    public function test_upgrade_ditolak_tanpa_membership_berjalan(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->upgrade($this->member(), $this->paket(550000, 90), PaymentMethod::Cash, 0, $this->user(UserRole::Pegawai));
    }

    public function test_pegawai_bisa_upgrade_lewat_aksi_di_halaman_member(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $baru = $this->paket(550000, 90);

        $this->actingAs($this->user(UserRole::Pegawai));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('upgrade'), ['plan_id' => $baru->id, 'method' => 'cash', 'amount' => 416667])
            ->assertNotified('Paket di-upgrade');

        $this->assertSame($baru->id, $member->currentMembership()->membership_plan_id);
    }

    public function test_aksi_upgrade_hanya_menawarkan_paket_lebih_mahal(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $murah = $this->paket(100000);

        $this->actingAs($this->user(UserRole::Pegawai));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('upgrade'), ['plan_id' => $murah->id, 'method' => 'cash', 'amount' => 0])
            ->assertHasActionErrors(['plan_id']);

        $this->assertSame(1, $member->memberships()->count());
    }

    public function test_refund_oleh_pegawai_ditolak_dan_oleh_owner_tersimpan(): void
    {
        $member = $this->member();
        $m = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');

        $pegawai = $this->user(UserRole::Pegawai);
        $this->assertFalse($pegawai->can('refund', $member));
        $this->actingAs($pegawai);
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertActionHidden('refund')
            ->call('mountAction', 'refund')
            ->assertNotNotified();
        $this->assertNull($m->fresh()->cancelled_at);
        $this->assertSame(0, Refund::count());

        $owner = $this->user(UserRole::Owner);
        $this->actingAs($owner);
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('refund'), [
                'amount' => 133333, 'method' => 'cash', 'reason' => 'Pindah kota', 'confirmed' => true,
            ])
            ->assertNotified('Refund dicatat dan membership dibatalkan');

        $refund = Refund::sole();
        $this->assertSame(133333, $refund->amount);
        $this->assertSame($owner->id, $refund->recorded_by);
        $this->assertSame($m->id, $refund->membership_id);
        $this->assertSame('Refund: Pindah kota', $m->fresh()->cancel_reason);
        $this->assertSame(MemberStatus::Active, $member->fresh()->status);
    }

    public function test_refund_tanpa_centang_konfirmasi_ditolak(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');

        $this->actingAs($this->user(UserRole::Owner));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('refund'), [
                'amount' => 100000, 'method' => 'cash', 'reason' => 'Uji', 'confirmed' => false,
            ])
            ->assertHasActionErrors(['confirmed']);

        $this->assertSame(0, Refund::count());
    }

    public function test_checkin_ditolak_setelah_refund(): void
    {
        $member = $this->member();
        $m = $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $owner = $this->user(UserRole::Owner);

        $this->service->refund($m, 100000, PaymentMethod::Cash, 'Pindah kota', $owner);

        $hasil = app(CheckInService::class)->inspectMember($member);
        $this->assertFalse($hasil->allowed);
        $this->assertSame('Masa aktif sudah habis', $hasil->reason);
    }

    public function test_refund_melebihi_nilai_atau_tanpa_alasan_ditolak(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-01', '2026-11-30');
        $owner = $this->user(UserRole::Owner);

        foreach ([[200001, 'Uji'], [0, 'Uji'], [1000, '  ']] as [$jumlah, $alasan]) {
            try {
                $this->service->refund($m, $jumlah, PaymentMethod::Cash, $alasan, $owner);
                $this->fail('Refund seharusnya ditolak');
            } catch (InvalidArgumentException) {
                // diharapkan
            }
        }

        $this->assertNull($m->fresh()->cancelled_at);
        $this->assertSame(0, Refund::count());
    }

    public function test_refund_membership_yang_sudah_dibatalkan_ditolak(): void
    {
        $m = $this->membership($this->member(), $this->paket(200000), '2026-11-01', '2026-11-30');
        $owner = $this->user(UserRole::Owner);
        $this->service->refund($m, 100000, PaymentMethod::Cash, 'Uji', $owner);

        $this->expectException(InvalidArgumentException::class);
        $this->service->refund($m, 100000, PaymentMethod::Cash, 'Uji lagi', $owner);
    }

    public function test_halaman_refund_hanya_untuk_owner(): void
    {
        Refund::factory()->create(['reason' => 'Pindah kota']);

        $this->actingAs($this->user(UserRole::Pegawai))->get('/admin/refunds')->assertForbidden();

        $this->actingAs($this->user(UserRole::Owner));
        Livewire::test(ManageRefunds::class)->assertSee('Pindah kota');
    }

    public function test_migration_mengisi_visits_total_untuk_membership_lama(): void
    {
        $member = $this->member();
        $dengan = $this->membership($member, $this->paket(220000, 60, 10), '2026-11-01', '2026-12-30');
        $tanpa = $this->membership($member, $this->paket(200000), '2026-09-01', '2026-09-30');

        $migration = require database_path('migrations/2026_11_13_012459_add_upgrade_refund_columns.php');
        $migration->down();
        $migration->up();

        $this->assertSame(10, $dengan->fresh()->visits_total);
        $this->assertNull($tanpa->fresh()->visits_total);
    }

    public function test_pemasukan_hari_ini_dikurangi_refund_untuk_owner(): void
    {
        $owner = $this->user(UserRole::Owner);
        $pegawai = $this->user(UserRole::Pegawai);
        $member = $this->member();

        Payment::factory()->create(['member_id' => $member->id, 'amount' => 200000, 'recorded_by' => $pegawai->id]);
        WalkIn::create(['name' => 'Budi', 'amount' => 25000, 'method' => 'cash', 'recorded_by' => $pegawai->id, 'visited_at' => now()]);
        Refund::factory()->create(['member_id' => $member->id, 'amount' => 80000, 'refunded_at' => now()]);
        Refund::factory()->create(['member_id' => $member->id, 'amount' => 50000, 'refunded_at' => now()->subDay()]);

        $this->assertSame('Rp 145.000', $this->pemasukanHariIni($owner));
        // Pegawai tidak melihat semua pembayaran, jadi refund tidak dikurangkan.
        $this->assertSame('Rp 225.000', $this->pemasukanHariIni($pegawai));
    }

    public function test_riwayat_membership_dan_tabel_pembayaran_menampilkan_upgrade(): void
    {
        $member = $this->member();
        $this->membership($member, $this->paket(200000), '2026-11-01', '2026-11-30');
        $baru = $this->paket(550000, 90);
        $this->service->upgrade($member, $baru, PaymentMethod::Cash, 416667, $this->user(UserRole::Pegawai));

        $this->actingAs($this->user(UserRole::Owner));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('Dibatalkan')
            ->assertSee('Diupgrade ke '.$baru->name);

        $upgrade = Payment::where('type', PaymentType::Upgrade)->sole();
        Livewire::test(ListPayments::class)
            ->filterTable('type', PaymentType::Upgrade->value)
            ->assertCanSeeTableRecords([$upgrade])
            ->assertCountTableRecords(1);
    }
}
