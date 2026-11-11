<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Events\MemberActivated;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class MembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    private MembershipService $service;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MembershipService::class);
        $this->pegawai = User::factory()->create();
        Carbon::setTestNow('2026-11-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function aktifkan(?Member $member = null, ?MembershipPlan $plan = null)
    {
        return $this->service->activate(
            $member ?? Member::factory()->create(),
            $plan ?? MembershipPlan::factory()->create(),
            PaymentMethod::Cash,
            200000,
            $this->pegawai,
        );
    }

    public function test_nomor_member_berurutan_dan_tidak_direset_per_bulan(): void
    {
        $m1 = $this->aktifkan()->member->fresh();
        $m2 = $this->aktifkan()->member->fresh();

        Carbon::setTestNow('2026-12-02 10:00:00');
        $m3 = $this->aktifkan()->member->fresh();

        $this->assertSame('RPK-2611-0001', $m1->member_number);
        $this->assertSame('RPK-2611-0002', $m2->member_number);
        $this->assertSame('RPK-2612-0003', $m3->member_number);
        $this->assertSame(MemberStatus::Active, $m1->status);
        $this->assertSame($this->pegawai->id, $m1->activated_by);
        $this->assertNotNull($m1->activated_at);
    }

    public function test_aktivasi_mencatat_pembayaran_dan_kuota(): void
    {
        $plan = MembershipPlan::factory()->create(['visit_quota' => 10]);
        $membership = $this->aktifkan(null, $plan);

        $this->assertSame(10, $membership->visits_remaining);
        $payment = $membership->payments()->firstOrFail();
        $this->assertSame(200000, $payment->amount);
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertSame($this->pegawai->id, $payment->recorded_by);
    }

    public function test_member_yang_sudah_aktif_ditolak(): void
    {
        $member = $this->aktifkan()->member;

        $this->expectException(InvalidArgumentException::class);
        $this->aktifkan($member);
    }

    public function test_paket_30_hari_dari_11_nov_berakhir_10_des(): void
    {
        $membership = $this->aktifkan();

        $this->assertSame('2026-11-11', $membership->starts_on->toDateString());
        $this->assertSame('2026-12-10', $membership->ends_on->toDateString());
    }

    public function test_perpanjang_saat_masih_aktif_mulai_sehari_setelah_ends_on(): void
    {
        $plan = MembershipPlan::factory()->create();
        $member = $this->aktifkan(null, $plan)->member;

        $baru = $this->service->renew($member, $plan, PaymentMethod::Qris, 200000, $this->pegawai);

        $this->assertSame('2026-12-11', $baru->starts_on->toDateString());
        $this->assertSame('2027-01-09', $baru->ends_on->toDateString());
    }

    public function test_perpanjang_saat_sudah_habis_mulai_hari_ini(): void
    {
        $plan = MembershipPlan::factory()->create();
        $member = $this->aktifkan(null, $plan)->member;

        Carbon::setTestNow('2027-02-01 09:00:00');
        $baru = $this->service->renew($member, $plan, PaymentMethod::Transfer, 200000, $this->pegawai);

        $this->assertSame('2027-02-01', $baru->starts_on->toDateString());
        $this->assertSame('2027-03-02', $baru->ends_on->toDateString());
    }

    public function test_perpanjang_member_pending_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->renew(Member::factory()->create(), MembershipPlan::factory()->create(), PaymentMethod::Cash, 1, $this->pegawai);
    }

    public function test_bekukan_7_hari_memundurkan_ends_on(): void
    {
        $membership = $this->aktifkan();

        $this->service->freeze($membership, 7, $this->pegawai);

        $membership->refresh();
        $this->assertSame('2026-12-17', $membership->ends_on->toDateString());
        $this->assertSame('2026-11-11', $membership->frozen_from->toDateString());
        $this->assertSame('2026-11-17', $membership->frozen_until->toDateString());
    }

    public function test_bekukan_31_hari_ditolak(): void
    {
        $membership = $this->aktifkan();

        try {
            $this->service->freeze($membership, 31, $this->pegawai);
            $this->fail('Seharusnya ditolak.');
        } catch (InvalidArgumentException) {
            $this->assertSame('2026-12-10', $membership->fresh()->ends_on->toDateString());
        }
    }

    public function test_bekukan_saat_sedang_beku_ditolak(): void
    {
        $membership = $this->aktifkan();
        $this->service->freeze($membership, 7, $this->pegawai);

        $this->expectException(InvalidArgumentException::class);
        $this->service->freeze($membership->fresh(), 3, $this->pegawai);
    }

    public function test_event_member_activated_dikirim(): void
    {
        Event::fake([MemberActivated::class]);

        $membership = $this->aktifkan();

        Event::assertDispatched(MemberActivated::class, fn ($e) => $e->member->is($membership->member));
    }

    public function test_current_membership_mengabaikan_yang_dibatalkan_dan_kedaluwarsa(): void
    {
        $membership = $this->aktifkan();
        $member = $membership->member;

        $this->assertTrue($member->currentMembership()->is($membership));

        $membership->update(['cancelled_at' => now()]);
        $this->assertNull($member->currentMembership());
    }
}
