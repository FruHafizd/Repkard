<?php

namespace Tests\Feature;

use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use App\Models\WalkIn;
use App\Services\FinanceReportService;
use Carbon\Carbon;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PengeluaranLaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function wita(int $month, int $day, int $hour = 12, int $minute = 0, int $year = 2026): Carbon
    {
        return Carbon::create($year, $month, $day, $hour, $minute, 0, config('app.timezone'));
    }

    private function fixtureNovember(): void
    {
        $listrik = ExpenseCategory::factory()->create(['name' => 'Listrik & air']);
        $gaji = ExpenseCategory::factory()->create(['name' => 'Gaji & honor']);

        Payment::factory()->create(['type' => PaymentType::Purchase, 'amount' => 200000, 'paid_at' => $this->wita(11, 30, 23, 30)]);
        Payment::factory()->create(['type' => PaymentType::Upgrade, 'amount' => 416667, 'paid_at' => $this->wita(11, 5)]);
        Payment::factory()->create(['type' => PaymentType::Pt, 'amount' => 1500000, 'paid_at' => $this->wita(11, 6)]);
        Payment::factory()->create(['type' => PaymentType::Purchase, 'amount' => 300000, 'paid_at' => $this->wita(11, 7), 'cancelled_at' => $this->wita(11, 8)]);
        WalkIn::factory()->count(2)->create(['amount' => 25000, 'visited_at' => $this->wita(11, 9)]);
        Sale::factory()->create(['total' => 40000, 'sold_at' => $this->wita(11, 10)]);
        Sale::factory()->create(['total' => 15000, 'sold_at' => $this->wita(11, 10), 'cancelled_at' => $this->wita(11, 11)]);
        Refund::factory()->create(['amount' => 100000, 'refunded_at' => $this->wita(11, 12)]);

        Expense::factory()->create(['expense_category_id' => $listrik->id, 'amount' => 750000, 'spent_on' => '2026-11-03']);
        Expense::factory()->create(['expense_category_id' => $gaji->id, 'amount' => 2000000, 'spent_on' => '2026-11-25']);
        Expense::factory()->create(['expense_category_id' => $gaji->id, 'amount' => 500000, 'spent_on' => '2026-11-25', 'cancelled_at' => $this->wita(11, 26)]);

        // Di luar November: tidak boleh ikut terhitung.
        Payment::factory()->create(['type' => PaymentType::Purchase, 'amount' => 999000, 'paid_at' => $this->wita(12, 1, 0, 10)]);
        Payment::factory()->create(['type' => PaymentType::Purchase, 'amount' => 888000, 'paid_at' => $this->wita(10, 31, 23, 50)]);
        Expense::factory()->create(['expense_category_id' => $listrik->id, 'amount' => 777000, 'spent_on' => '2026-12-01']);
        Expense::factory()->create(['expense_category_id' => $listrik->id, 'amount' => 666000, 'spent_on' => '2026-10-31']);
    }

    public function test_laporan_bulanan_menghitung_pemasukan_pengeluaran_dan_laba(): void
    {
        $this->fixtureNovember();

        $report = app(FinanceReportService::class)->monthly(2026, 11);

        $this->assertSame(616667, $report['income']['membership']);
        $this->assertSame(1500000, $report['income']['pt']);
        $this->assertSame(50000, $report['income']['walk_in']);
        $this->assertSame(40000, $report['income']['kasir']);
        $this->assertSame(100000, $report['income']['refunds']);
        $this->assertSame(2106667, $report['income']['total_income']);
        $this->assertSame(['Gaji & honor' => 2000000, 'Listrik & air' => 750000], $report['expenses']['by_category']);
        $this->assertSame(2750000, $report['expenses']['total_expenses']);
        $this->assertSame(-643333, $report['net']);
    }

    public function test_transaksi_dibatalkan_dan_di_luar_bulan_tidak_terhitung_dan_harian_sama_dengan_bulanan(): void
    {
        $this->fixtureNovember();

        $report = app(FinanceReportService::class)->monthly(2026, 11);

        $this->assertCount(30, $report['daily']);
        $this->assertSame($report['income']['total_income'], array_sum(array_column($report['daily'], 'income')));
        $this->assertSame($report['expenses']['total_expenses'], array_sum(array_column($report['daily'], 'expenses')));
        // 30 Nov 23:30 WITA masih November.
        $this->assertSame(200000, $report['daily']['2026-11-30']['income']);
        $this->assertSame(0, $report['daily']['2026-11-01']['income']);
        // Bulan lalu (Oktober) hanya berisi pembayaran 888.000 dan pengeluaran 666.000.
        $this->assertSame(888000, $report['previous']['total_income']);
        $this->assertSame(666000, $report['previous']['total_expenses']);
        $this->assertSame(222000, $report['previous']['net']);
    }

    public function test_halaman_laporan_keuangan_hanya_untuk_owner(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai))->get('/admin/laporan-keuangan')->assertForbidden();
        $this->actingAs($this->user(UserRole::Owner))->get('/admin/laporan-keuangan')->assertOk()->assertSee('Laporan Keuangan');
    }

    public function test_kategori_pengeluaran_hanya_owner_dan_seeder_idempoten(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai))->get('/admin/expense-categories')->assertForbidden();
        $this->actingAs($this->user(UserRole::Owner))->get('/admin/expense-categories')->assertOk();

        $this->seed(ExpenseCategorySeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
        $this->assertSame(8, ExpenseCategory::count());
    }

    public function test_kategori_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $owner = $this->user(UserRole::Owner);
        $dipakai = Expense::factory()->create()->category;
        $kosong = ExpenseCategory::factory()->create();

        $this->assertFalse($owner->can('delete', $dipakai));
        $this->assertTrue($owner->can('delete', $kosong));
    }

    public function test_pegawai_hanya_melihat_pengeluaran_miliknya_dan_tidak_bisa_mengubah_atau_membatalkan(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        $miliknya = Expense::factory()->create(['recorded_by' => $a->id]);
        $milikB = Expense::factory()->create(['recorded_by' => $b->id]);

        Livewire::actingAs($a)->test(ListExpenses::class)
            ->assertCanSeeTableRecords([$miliknya])
            ->assertCanNotSeeTableRecords([$milikB])
            ->assertTableActionHidden('batalkan', $miliknya);

        $this->assertFalse($a->can('update', $miliknya));
        $this->assertFalse($a->can('delete', $miliknya));
        $this->assertFalse($a->can('cancel', $miliknya));

        $this->actingAs($a)->get("/admin/expenses/{$miliknya->id}")->assertOk();
        $this->actingAs($a)->get("/admin/expenses/{$milikB->id}")->assertNotFound();
        $this->actingAs($a)->get("/admin/expenses/{$miliknya->id}/edit")->assertNotFound();
    }

    public function test_owner_membatalkan_pengeluaran_dengan_alasan_tanpa_menghapus_baris(): void
    {
        $owner = $this->user(UserRole::Owner);
        $expense = Expense::factory()->create();

        Livewire::actingAs($owner)->test(ListExpenses::class)
            ->callTableAction('batalkan', $expense, ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        Livewire::actingAs($owner)->test(ListExpenses::class)
            ->callTableAction('batalkan', $expense, ['reason' => 'Salah input']);

        $expense->refresh();
        $this->assertTrue($expense->isCancelled());
        $this->assertSame('Salah input', $expense->cancel_reason);
        $this->assertSame($owner->id, $expense->cancelled_by);
    }

    public function test_bukti_pengeluaran_hanya_bisa_dibuka_pencatatnya_dan_tidak_lewat_storage(): void
    {
        Storage::fake('local');
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        Storage::disk('local')->put('expense-proofs/nota.png', 'isi-bukti');
        $expense = Expense::factory()->create(['recorded_by' => $a->id, 'proof_path' => 'expense-proofs/nota.png']);

        $this->actingAs($a)->get("/media/bukti-pengeluaran/{$expense->id}")->assertOk();
        $this->actingAs($b)->get("/media/bukti-pengeluaran/{$expense->id}")->assertForbidden();
        $this->actingAs($this->user(UserRole::Owner))->get("/media/bukti-pengeluaran/{$expense->id}")->assertOk();
        $this->assertContains($this->actingAs($a)->get('/storage/expense-proofs/nota.png')->status(), [403, 404]);
        auth()->logout();
        $this->get("/media/bukti-pengeluaran/{$expense->id}")->assertRedirect();
    }

    public function test_tanggal_pengeluaran_di_masa_depan_ditolak_dan_pengeluaran_valid_tersimpan(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $kategori = ExpenseCategory::factory()->create();

        Livewire::actingAs($pegawai)->test(CreateExpense::class)
            ->fillForm([
                'expense_category_id' => $kategori->id,
                'amount' => 50000,
                'spent_on' => now()->addDay()->toDateString(),
                'description' => 'Beli sabun',
            ])
            ->call('create')
            ->assertHasFormErrors(['spent_on']);

        Livewire::actingAs($pegawai)->test(CreateExpense::class)
            ->fillForm([
                'expense_category_id' => $kategori->id,
                'amount' => 0,
                'spent_on' => now()->toDateString(),
                'description' => 'Beli sabun',
            ])
            ->call('create')
            ->assertHasFormErrors(['amount']);

        Livewire::actingAs($pegawai)->test(CreateExpense::class)
            ->fillForm([
                'expense_category_id' => $kategori->id,
                'amount' => 50000,
                'spent_on' => now()->toDateString(),
                'description' => 'Beli sabun',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('expenses', ['amount' => 50000, 'recorded_by' => $pegawai->id]);
    }
}
