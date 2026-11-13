<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PtSessionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\PtSessions\Pages\ListPtSessions;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PtPackage;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\FinanceReportService;
use App\Services\MembershipService;
use App\Services\PtService;
use App\Services\SaleService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/** Temuan review keamanan & hitungan uang untuk fitur tambahan 01–09. */
class ReviewFiturTambahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow('2026-11-11 10:00:00');
        Filament::setCurrentPanel('admin');
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

    /** Member aktif dengan membership 1–30 Nov dan satu pembayaran sebesar harga paket. */
    private function memberDenganMembership(int $harga = 300000): Membership
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active]);
        $plan = MembershipPlan::factory()->create(['price' => $harga, 'duration_days' => 30]);
        $ms = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
        ]);
        Payment::factory()->create(['member_id' => $member->id, 'membership_id' => $ms->id, 'amount' => $harga]);

        return $ms;
    }

    public function test_nomor_transaksi_tidak_bentrok_saat_dua_kasir_checkout_bersamaan(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $produk = Product::factory()->create(['price' => 10000, 'track_stock' => false, 'is_active' => true]);

        // Kasir lain menyimpan transaksi bernomor sama tepat setelah nomor terakhir dibaca.
        $sudah = false;
        DB::listen(function (QueryExecuted $q) use (&$sudah, $pegawai): void {
            if (! $sudah && str_contains($q->sql, 'max("number")')) {
                $sudah = true;
                Sale::factory()->create(['number' => 'TRX-261111-0001', 'recorded_by' => $pegawai->id]);
            }
        });

        $sale = app(SaleService::class)->checkout([$produk->id => 1], PaymentMethod::Qris, null, null, null, $pegawai);

        $this->assertTrue($sudah);
        $this->assertMatchesRegularExpression('/^TRX-261111-\d{4}$/', $sale->number);
        $this->assertSame(1, Sale::where('number', $sale->number)->count());
    }

    public function test_pembayaran_paket_lama_yang_sudah_jadi_kredit_upgrade_tidak_bisa_dibatalkan(): void
    {
        $owner = $this->user(UserRole::Owner);
        $lama = $this->memberDenganMembership(300000);
        $bayarLama = $lama->payments()->sole();
        $paketBaru = MembershipPlan::factory()->create(['price' => 600000, 'duration_days' => 30]);

        $baru = app(MembershipService::class)->upgrade($lama->member, $paketBaru, PaymentMethod::Cash, 400000, $owner);
        $this->assertSame(200000, $baru->credit_amount);

        // Kalau pembayaran lama boleh dibatalkan, kredit Rp 200.000 tetap ada lalu bisa di-refund: uang keluar tanpa pernah masuk.
        $this->assertFalse($owner->can('cancel', $bayarLama->fresh()));

        $this->actingAs($owner);
        Livewire::test(ListPayments::class)
            ->assertActionHidden(TestAction::make('batalkan')->table($bayarLama));
        $this->assertNull($bayarLama->fresh()->cancelled_at);

        // Pembayaran upgrade sendiri tetap bisa dibatalkan (salah catat).
        $this->assertTrue($owner->can('cancel', $baru->payments()->sole()));
    }

    public function test_pembayaran_membership_yang_sudah_direfund_tidak_bisa_dibatalkan(): void
    {
        $owner = $this->user(UserRole::Owner);
        $ms = $this->memberDenganMembership(300000);

        app(MembershipService::class)->refund($ms, 100000, PaymentMethod::Cash, 'Pindah kota', $owner);

        // Membatalkan pembayarannya membuat refund terhitung sebagai uang keluar tanpa pemasukan.
        $this->assertFalse($owner->can('cancel', $ms->payments()->sole()));
    }

    public function test_sesi_dari_paket_pt_yang_dibatalkan_tidak_bisa_ditandai_selesai_atau_tidak_datang(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $trainer = $this->user(UserRole::Trainer);
        TrainerProfile::create(['user_id' => $trainer->id, 'commission_per_session' => 50000]);
        $member = Member::factory()->create(['status' => MemberStatus::Active]);
        Membership::factory()->create(['member_id' => $member->id]);
        $paket = PtPackage::create([
            'name' => 'PT 10', 'sessions_count' => 10, 'price' => 1000000,
            'validity_days' => 60, 'is_active' => true, 'sort_order' => 0,
        ]);

        $pt = app(PtService::class);
        $sub = $pt->sell($member, $paket, $trainer, PaymentMethod::Cash, 1000000, $pegawai);
        $a = $pt->schedule($sub, Carbon::parse('2026-11-12 10:00'), 60, $pegawai);
        $b = $pt->schedule($sub, Carbon::parse('2026-11-13 10:00'), 60, $pegawai);
        $pt->cancel($sub, 'Member minta berhenti');

        try {
            $pt->finish($a, PtSessionStatus::Completed, $trainer);
            $this->fail('Sesi dari paket yang dibatalkan tidak boleh ditandai selesai.');
        } catch (InvalidArgumentException) {
        }

        // Lewat aksi tabel oleh trainer sendiri.
        Livewire::actingAs($trainer)->test(ListPtSessions::class)
            ->callAction(TestAction::make(PtSessionStatus::NoShow->value)->table($b));

        $this->assertSame(PtSessionStatus::Scheduled, $a->fresh()->status);
        $this->assertSame(PtSessionStatus::Scheduled, $b->fresh()->status);
        $this->assertNull($a->fresh()->commission_amount);
        $this->assertNull($b->fresh()->commission_amount);
        $this->assertSame(10, $sub->fresh()->sessions_remaining);

        // Sesi tetap bisa dibatalkan supaya jadwal trainer kosong lagi.
        $pt->cancelSession($a, $pegawai, 'Paket dibatalkan');
        $this->assertSame(PtSessionStatus::Cancelled, $a->fresh()->status);
    }

    public function test_pemasukan_hari_ini_owner_sama_dengan_laporan_keuangan_untuk_hari_yang_sama(): void
    {
        $owner = $this->user(UserRole::Owner);
        $member = Member::factory()->create(['status' => MemberStatus::Active]);

        foreach ([PaymentType::Purchase, PaymentType::Upgrade, PaymentType::Pt] as $type) {
            Payment::factory()->create(['member_id' => $member->id, 'type' => $type, 'amount' => 100000]);
        }
        Payment::factory()->create(['member_id' => $member->id, 'amount' => 999000, 'cancelled_at' => now()]);
        WalkIn::factory()->create(['amount' => 25000]);
        WalkIn::factory()->create(['amount' => 999000, 'cancelled_at' => now()]);
        Sale::factory()->create(['total' => 40000]);
        Sale::factory()->create(['total' => 999000, 'cancelled_at' => now()]);
        Refund::factory()->create(['amount' => 15000]);
        // Hari lain di bulan yang sama: masuk laporan bulanan, tidak masuk "hari ini".
        Payment::factory()->create(['member_id' => $member->id, 'amount' => 70000, 'paid_at' => '2026-11-02 09:00:00']);

        $this->actingAs($owner);
        $widget = new AdminStatsOverview;
        $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);
        $hariIni = collect($stats)->first(fn ($s) => $s->getLabel() === 'Pemasukan hari ini')->getValue();

        $laporan = app(FinanceReportService::class)->monthly(2026, 11);

        $this->assertSame(350000, $laporan['daily']['2026-11-11']['income']);
        $this->assertSame('Rp 350.000', $hariIni);
        $this->assertSame(420000, $laporan['income']['total_income']);
    }

    public function test_field_tersembunyi_tidak_bisa_diisi_dari_browser_saat_mencatat_pengeluaran(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $lain = $this->user(UserRole::Pegawai);
        $kategori = ExpenseCategory::factory()->create();

        Livewire::actingAs($pegawai)->test(CreateExpense::class)
            ->fillForm([
                'expense_category_id' => $kategori->id,
                'amount' => 50000,
                'spent_on' => now()->toDateString(),
                'description' => 'Beli sabun',
            ])
            ->set('data.recorded_by', $lain->id)
            ->set('data.cancelled_at', now()->toDateTimeString())
            ->set('data.cancelled_by', $lain->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $expense = Expense::sole();
        $this->assertSame($pegawai->id, $expense->recorded_by);
        $this->assertNull($expense->cancelled_at);
        $this->assertNull($expense->cancelled_by);
    }
}
