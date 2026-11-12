<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PtSessionStatus;
use App\Enums\UserRole;
use App\Filament\Member\Pages\PersonalTrainer;
use App\Filament\Pages\KomisiTrainer;
use App\Filament\Resources\PtSessions\Pages\CreatePtSession;
use App\Filament\Resources\PtSessions\Pages\ListPtSessions;
use App\Models\Member;
use App\Models\Membership;
use App\Models\PtPackage;
use App\Models\PtSession;
use App\Models\PtSubscription;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\PtService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class SesiPtTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow('2026-11-11 08:00:00');
        Filament::setCurrentPanel('admin');
        $this->pegawai = $this->user(UserRole::Pegawai);
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

    private function trainer(int $komisi = 50000): User
    {
        $trainer = $this->user(UserRole::Trainer);
        TrainerProfile::create(['user_id' => $trainer->id, 'commission_per_session' => $komisi]);

        return $trainer;
    }

    private function jual(?User $trainer = null, int $sesi = 10, ?Member $member = null): PtSubscription
    {
        $member ??= Member::factory()->create(['status' => MemberStatus::Active]);
        Membership::factory()->create(['member_id' => $member->id]);

        $paket = PtPackage::create([
            'name' => "PT {$sesi} Sesi", 'sessions_count' => $sesi, 'price' => 1000000,
            'validity_days' => 60, 'is_active' => true, 'sort_order' => 0,
        ]);

        return app(PtService::class)->sell($member, $paket, $trainer ?? $this->trainer(), PaymentMethod::Cash, 1000000, $this->pegawai);
    }

    private function jadwal(PtSubscription $sub, string $at, int $durasi = 60): PtSession
    {
        return app(PtService::class)->schedule($sub, Carbon::parse($at), $durasi, $this->pegawai);
    }

    public function test_selesai_memotong_sesi_dan_mengunci_komisi(): void
    {
        $trainer = $this->trainer(50000);
        $sub = $this->jual($trainer);
        $sesi = $this->jadwal($sub, '2026-11-12 10:00');

        $sesi = app(PtService::class)->finish($sesi, PtSessionStatus::Completed, $trainer);

        $this->assertSame(9, $sub->fresh()->sessions_remaining);
        $this->assertSame(50000, $sesi->commission_amount);
        $this->assertSame($trainer->id, $sesi->finished_by);

        $trainer->trainerProfile->update(['commission_per_session' => 80000]);
        $this->assertSame(50000, $sesi->fresh()->commission_amount);
    }

    public function test_tidak_datang_terpotong_dan_berkomisi_dibatalkan_tidak(): void
    {
        $trainer = $this->trainer(50000);
        $sub = $this->jual($trainer);
        $noShow = $this->jadwal($sub, '2026-11-12 10:00');
        $batal = $this->jadwal($sub, '2026-11-12 12:00');

        $noShow = app(PtService::class)->finish($noShow, PtSessionStatus::NoShow, $this->pegawai);
        $this->assertSame(9, $sub->fresh()->sessions_remaining);
        $this->assertSame(50000, $noShow->commission_amount);

        $batal = app(PtService::class)->cancelSession($batal, $this->pegawai, 'Member sakit');
        $this->assertSame(9, $sub->fresh()->sessions_remaining);
        $this->assertNull($batal->commission_amount);
        $this->assertSame(PtSessionStatus::Cancelled, $batal->status);
    }

    public function test_sesi_yang_sudah_ditandai_tidak_bisa_ditandai_lagi(): void
    {
        $sub = $this->jual();
        $sesi = $this->jadwal($sub, '2026-11-12 10:00');
        app(PtService::class)->finish($sesi, PtSessionStatus::Completed, $this->pegawai);

        foreach ([fn () => app(PtService::class)->finish($sesi, PtSessionStatus::Completed, $this->pegawai),
            fn () => app(PtService::class)->cancelSession($sesi, $this->pegawai, 'x')] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertSame(9, $sub->fresh()->sessions_remaining);
            }
        }
    }

    public function test_tidak_boleh_menjadwalkan_melebihi_sisa_sesi(): void
    {
        $sub = $this->jual(sesi: 2);
        $this->jadwal($sub, '2026-11-12 10:00');
        $this->jadwal($sub, '2026-11-13 10:00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sisa sesi sudah terjadwal semua');
        $this->jadwal($sub, '2026-11-14 10:00');
    }

    public function test_trainer_bentrok_ditolak_tetapi_jam_berurutan_boleh(): void
    {
        $trainer = $this->trainer();
        $this->jadwal($this->jual($trainer), '2026-11-12 10:00');
        $sub2 = $this->jual($trainer);

        try {
            $this->jadwal($sub2, '2026-11-12 10:30');
            $this->fail('Seharusnya bentrok.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Trainer', $e->getMessage());
        }

        $this->assertSame('2026-11-12 11:00', $this->jadwal($sub2, '2026-11-12 11:00')->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_member_yang_sama_tidak_boleh_bentrok(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active]);
        $this->jadwal($this->jual(member: $member), '2026-11-12 10:00');
        $subLain = $this->jual(member: $member);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member');
        $this->jadwal($subLain, '2026-11-12 10:30');
    }

    public function test_jadwal_setelah_masa_berlaku_atau_di_masa_lalu_ditolak(): void
    {
        $sub = $this->jual(); // berlaku sampai 2026-11-11 + 59 hari = 2027-01-09

        foreach (['2027-01-10 10:00', '2026-11-11 07:00'] as $at) {
            try {
                $this->jadwal($sub, $at);
                $this->fail("Seharusnya ditolak: {$at}");
            } catch (InvalidArgumentException) {
                $this->assertSame(0, PtSession::count());
            }
        }

        $this->jadwal($sub, '2027-01-09 10:00');
        $this->assertSame(1, PtSession::count());
    }

    public function test_trainer_tidak_bisa_melihat_atau_menandai_sesi_trainer_lain(): void
    {
        $a = $this->trainer();
        $b = $this->trainer();
        $milikA = $this->jadwal($this->jual($a), '2026-11-12 10:00');
        $milikB = $this->jadwal($this->jual($b), '2026-11-12 14:00');

        $this->assertTrue($a->can('update', $milikA));
        $this->assertFalse($a->can('view', $milikB));
        $this->assertFalse($a->can('update', $milikB));

        $this->actingAs($a)->get('/admin/pt-sessions')->assertOk();

        $list = Livewire::actingAs($a)->test(ListPtSessions::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB]);

        $list->callAction(TestAction::make('completed')->table($milikA));
        $this->assertSame(PtSessionStatus::Completed, $milikA->fresh()->status);

        // Aksi pada sesi yang bukan miliknya tidak bisa dijalankan (record tidak ada di query trainer).
        try {
            $list->callAction(TestAction::make('completed')->table($milikB));
            $this->fail('Seharusnya ditolak.');
        } catch (ActionNotResolvableException) {
            $this->assertSame(PtSessionStatus::Scheduled, $milikB->fresh()->status);
        }
    }

    public function test_trainer_tidak_bisa_menjadwalkan_klien_trainer_lain(): void
    {
        $a = $this->trainer();
        $subA = $this->jual($a);
        $subB = $this->jual($this->trainer());

        Livewire::actingAs($a)->test(CreatePtSession::class)
            ->fillForm(['pt_subscription_id' => $subB->id, 'scheduled_at' => '2026-11-12 10:00', 'duration_minutes' => 60])
            ->call('create')
            ->assertHasFormErrors(['pt_subscription_id']);
        $this->assertSame(0, PtSession::count());

        Livewire::actingAs($a)->test(CreatePtSession::class)
            ->fillForm(['pt_subscription_id' => $subA->id, 'scheduled_at' => '2026-11-12 10:00', 'duration_minutes' => 60])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(1, PtSession::count());
    }

    public function test_pegawai_menjadwalkan_dan_melihat_semua_sesi(): void
    {
        $sub = $this->jual();

        Livewire::actingAs($this->pegawai)->test(CreatePtSession::class)
            ->fillForm(['pt_subscription_id' => $sub->id, 'scheduled_at' => '2026-11-12 10:00', 'duration_minutes' => 60])
            ->call('create')
            ->assertHasNoFormErrors();

        $sesi = PtSession::firstOrFail();
        Livewire::actingAs($this->pegawai)->test(ListPtSessions::class)->assertCanSeeTableRecords([$sesi]);
    }

    public function test_laporan_komisi_hanya_menghitung_sesi_yang_ditandai_di_bulan_itu(): void
    {
        $trainer = $this->trainer(50000);
        $lain = $this->trainer(70000);
        $sub = $this->jual($trainer);
        $subLain = $this->jual($lain);

        $tandai = function (PtSubscription $s, string $scheduled, string $finishedAt, PtSessionStatus $status) {
            Carbon::setTestNow($finishedAt);
            $sesi = PtSession::create([
                'pt_subscription_id' => $s->id, 'trainer_id' => $s->trainer_id,
                'scheduled_at' => $scheduled, 'status' => PtSessionStatus::Scheduled, 'created_by' => $this->pegawai->id,
            ]);
            app(PtService::class)->finish($sesi, $status, $this->pegawai);
        };

        $tandai($sub, '2026-11-30 22:30', '2026-11-30 23:30:00', PtSessionStatus::Completed);
        $tandai($sub, '2026-11-20 10:00', '2026-11-20 11:00:00', PtSessionStatus::NoShow);
        $tandai($sub, '2026-11-30 23:00', '2026-12-01 00:10:00', PtSessionStatus::Completed); // masuk Desember
        $tandai($subLain, '2026-11-05 10:00', '2026-11-05 11:00:00', PtSessionStatus::Completed);
        Carbon::setTestNow('2026-12-02 09:00:00');

        $owner = $this->user(UserRole::Owner);
        $rows = Livewire::actingAs($owner)->test(KomisiTrainer::class)->set('bulan', '2026-11')->instance()->rows();

        $this->assertCount(2, $rows);
        $baris = collect($rows)->keyBy('trainer');
        $this->assertSame(['trainer' => $trainer->name, 'selesai' => 1, 'tidak_datang' => 1, 'komisi' => 100000], $baris[$trainer->name]);
        $this->assertSame(70000, $baris[$lain->name]['komisi']);

        // Trainer hanya melihat barisnya sendiri.
        $rowsTrainer = Livewire::actingAs($trainer)->test(KomisiTrainer::class)->set('bulan', '2026-11')->instance()->rows();
        $this->assertCount(1, $rowsTrainer);
        $this->assertSame($trainer->name, $rowsTrainer[0]['trainer']);

        $desember = Livewire::actingAs($owner)->test(KomisiTrainer::class)->set('bulan', '2026-12')->instance()->rows();
        $this->assertSame(50000, $desember[0]['komisi']);
    }

    public function test_pegawai_tidak_bisa_membuka_komisi_trainer(): void
    {
        $this->actingAs($this->pegawai)->get('/admin/komisi-trainer')->assertForbidden();
        $this->actingAs($this->user(UserRole::Owner))->get('/admin/komisi-trainer')->assertOk();
        $this->actingAs($this->trainer())->get('/admin/komisi-trainer')->assertOk();
    }

    public function test_member_melihat_jadwal_dan_riwayat_miliknya_saja(): void
    {
        $milik = $this->jual();
        $lain = $this->jual();
        $this->jadwal($milik, '2026-11-12 10:00');
        $selesai = $this->jadwal($milik, '2026-11-13 10:00');
        app(PtService::class)->finish($selesai, PtSessionStatus::Completed, $this->pegawai);
        $this->jadwal($lain, '2026-11-14 15:45');

        Filament::setCurrentPanel('member');
        $this->actingAs($milik->member->user);

        $html = Livewire::test(PersonalTrainer::class)->assertOk()->html();
        $this->assertStringContainsString('12 Nov 2026, 10:00', $html);
        $this->assertStringContainsString('Selesai', $html);
        $this->assertStringNotContainsString('15:45', $html);
    }
}
