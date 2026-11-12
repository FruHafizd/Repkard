<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Filament\Pages\Kasir;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Widgets\AdminStatsOverview;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class KasirTest extends TestCase
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

    private function service(): SaleService
    {
        return app(SaleService::class);
    }

    private function jual(array $items, User $by, int $cash = 1000000): Sale
    {
        return $this->service()->checkout($items, PaymentMethod::Cash, $cash, null, null, $by);
    }

    public function test_checkout_dua_produk_menghitung_total_mengurangi_stok_dan_mencatat_movement(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $a = Product::factory()->create(['price' => 5000, 'stock' => 10]);
        $b = Product::factory()->create(['price' => 25000, 'stock' => 4]);

        $sale = $this->service()->checkout([$a->id => 2, $b->id => 1], PaymentMethod::Cash, 50000, null, 'Budi', $pegawai);

        $this->assertSame(35000, $sale->total);
        $this->assertSame(15000, $sale->change());
        $this->assertSame('TRX-'.now()->format('ymd').'-0001', $sale->number);
        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(3, $b->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $a->id, 'quantity' => -2, 'type' => StockMovementType::Sale->value, 'sale_id' => $sale->id]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_name' => $a->name, 'unit_price' => 5000, 'quantity' => 2, 'subtotal' => 10000]);
    }

    public function test_nomor_transaksi_urut_per_hari(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['stock' => 50]);

        $this->travelTo(now()->setTime(10, 0));
        $pertama = $this->jual([$p->id => 1], $pegawai);
        $kedua = $this->jual([$p->id => 1], $pegawai);

        $this->travelTo(now()->addDay());
        $besok = $this->jual([$p->id => 1], $pegawai);

        $this->assertStringEndsWith('-0001', $pertama->number);
        $this->assertStringEndsWith('-0002', $kedua->number);
        $this->assertStringEndsWith('-0001', $besok->number);
        $this->assertNotSame($pertama->number, $besok->number);
    }

    public function test_harga_diubah_setelah_transaksi_item_tetap_harga_lama(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['price' => 5000]);

        $sale = $this->jual([$p->id => 2], $pegawai);
        $p->update(['price' => 9000]);

        $item = $sale->items()->first();
        $this->assertSame(5000, $item->unit_price);
        $this->assertSame(10000, $item->subtotal);
        $this->assertSame(10000, $sale->fresh()->total);
    }

    public function test_stok_kurang_ditolak_dan_stok_tidak_berubah(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['name' => 'Protein', 'stock' => 2]);

        try {
            $this->jual([$p->id => 3], $pegawai);
            $this->fail('Seharusnya ditolak');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Stok Protein tinggal 2', $e->getMessage());
        }

        $this->assertSame(2, $p->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_produk_tanpa_hitung_stok_bisa_dijual(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['track_stock' => false, 'stock' => 0, 'price' => 5000]);

        $sale = $this->jual([$p->id => 3], $pegawai);

        $this->assertSame(15000, $sale->total);
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_produk_nonaktif_ditolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['is_active' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->jual([$p->id => 1], $pegawai);
    }

    public function test_keranjang_kosong_dan_jumlah_nol_ditolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create();

        foreach ([[], [$p->id => 0], [$p->id => -1]] as $items) {
            try {
                $this->jual($items, $pegawai);
                $this->fail('Seharusnya ditolak');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Sale::count());
    }

    public function test_tunai_kurang_dari_total_ditolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['price' => 10000, 'stock' => 5]);

        try {
            $this->service()->checkout([$p->id => 1], PaymentMethod::Cash, 9000, null, null, $pegawai);
            $this->fail('Seharusnya ditolak');
        } catch (InvalidArgumentException) {
            $this->assertSame(5, $p->fresh()->stock);
            $this->assertSame(0, Sale::count());
        }
    }

    public function test_void_mengembalikan_stok_dan_void_dua_kali_ditolak(): void
    {
        $owner = $this->user(UserRole::Owner);
        $p = Product::factory()->create(['stock' => 10]);
        $sale = $this->jual([$p->id => 4], $owner);
        $this->assertSame(6, $p->fresh()->stock);

        $this->service()->void($sale, $owner, 'Salah input');

        $this->assertSame(10, $p->fresh()->stock);
        $this->assertTrue($sale->fresh()->isCancelled());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'quantity' => 4, 'type' => StockMovementType::Void->value]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->void($sale->fresh(), $owner, 'Lagi');
    }

    public function test_restock_dan_koreksi_stok(): void
    {
        $owner = $this->user(UserRole::Owner);
        $p = Product::factory()->create(['stock' => 5]);

        $this->service()->restock($p, 10, 'Belanja', $owner);
        $this->assertSame(15, $p->fresh()->stock);

        $this->service()->adjust($p, 12, 'Hilang', $owner);
        $this->assertSame(12, $p->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'quantity' => -3, 'type' => StockMovementType::Adjustment->value]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->restock($p, 0, null, $owner);
    }

    public function test_pegawai_tidak_melihat_penjualan_pegawai_lain_tetapi_owner_melihat_semua(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        $owner = $this->user(UserRole::Owner);
        $p = Product::factory()->create(['stock' => 20]);
        $milikA = $this->jual([$p->id => 1], $a);
        $milikB = $this->jual([$p->id => 1], $b);

        Livewire::actingAs($a)->test(ListSales::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB]);

        $this->actingAs($a)->get(route('kasir.receipt', $milikA))->assertOk();
        $this->actingAs($a)->get(route('kasir.receipt', $milikB))->assertForbidden();

        Livewire::actingAs($owner)->test(ListSales::class)
            ->assertCanSeeTableRecords([$milikA, $milikB]);
        $this->actingAs($owner)->get(route('kasir.receipt', $milikB))->assertOk();
    }

    public function test_struk_tamu_dialihkan_ke_login(): void
    {
        $sale = Sale::factory()->create();

        $this->get(route('kasir.receipt', $sale))->assertRedirect();
    }

    public function test_pegawai_tidak_bisa_void_atau_tambah_stok(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $owner = $this->user(UserRole::Owner);
        $p = Product::factory()->create(['stock' => 5]);
        $sale = $this->jual([$p->id => 1], $pegawai);

        Livewire::actingAs($pegawai)->test(ViewProduct::class, ['record' => $p->getKey()])
            ->assertActionHidden('tambahStok')
            ->assertActionHidden('koreksiStok');
        $this->assertSame(4, $p->fresh()->stock);

        Livewire::actingAs($pegawai)->test(ListSales::class)
            ->assertTableActionHidden('batalkan', $sale);
        $this->assertFalse(Gate::forUser($pegawai)->allows('cancel', $sale));
        $this->assertFalse($sale->fresh()->isCancelled());

        Livewire::actingAs($owner)->test(ListSales::class)
            ->callTableAction('batalkan', $sale, ['reason' => 'Salah input'])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($sale->fresh()->isCancelled());
    }

    public function test_pegawai_hanya_baca_produk(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create();

        $this->actingAs($pegawai)->get('/admin/products')->assertOk();
        $this->actingAs($pegawai)->get('/admin/products/'.$p->id)->assertOk();
        $this->actingAs($pegawai)->get('/admin/products/create')->assertForbidden();
        $this->actingAs($pegawai)->get('/admin/products/'.$p->id.'/edit')->assertForbidden();
    }

    public function test_halaman_kasir_hanya_untuk_staf_dan_member_tidak_bisa(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai))->get('/admin/kasir')->assertOk();
        $this->actingAs($this->user(UserRole::Owner))->get('/admin/kasir')->assertOk();
        $this->actingAs($this->user(UserRole::Member))->get('/admin/kasir')->assertForbidden();
    }

    public function test_halaman_kasir_bayar_tunai_dan_keranjang_dikosongkan(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['price' => 5000, 'stock' => 10]);

        Livewire::actingAs($pegawai)->test(Kasir::class)
            ->call('add', $p->id)
            ->call('add', $p->id)
            ->assertSet('cart', [$p->id => 2])
            ->set('data.method', 'cash')
            ->set('data.cash_received', 20000)
            ->assertSee('Rp 10.000')
            ->call('bayar')
            ->assertSet('cart', []);

        $sale = Sale::firstOrFail();
        $this->assertSame(10000, $sale->total);
        $this->assertSame(20000, $sale->cash_received);
        $this->assertSame($pegawai->id, $sale->recorded_by);
        $this->assertSame(8, $p->fresh()->stock);
    }

    public function test_halaman_kasir_tidak_menambah_melebihi_stok_dan_cart_tidak_bisa_diubah_dari_browser(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['stock' => 1]);

        $test = Livewire::actingAs($pegawai)->test(Kasir::class)
            ->call('add', $p->id)
            ->call('add', $p->id)
            ->assertSet('cart', [$p->id => 1]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $test->set('cart', [$p->id => 99]);
    }

    public function test_rate_limit_checkout_30_per_menit(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $p = Product::factory()->create(['stock' => 100]);

        $test = Livewire::actingAs($pegawai)->test(Kasir::class)
            ->set('data.method', 'cash')
            ->set('data.cash_received', 100000);

        for ($i = 0; $i < 31; $i++) {
            $test->call('add', $p->id)->set('data.cash_received', 100000)->call('bayar');
        }

        $this->assertSame(30, Sale::count());
    }

    public function test_pemasukan_hari_ini_memuat_penjualan_kasir_milik_sendiri(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        $owner = $this->user(UserRole::Owner);
        $p = Product::factory()->create(['price' => 5000, 'stock' => 20]);
        $this->jual([$p->id => 2], $a);
        $dibatalkan = $this->jual([$p->id => 1], $b);
        $this->service()->void($dibatalkan, $owner, 'x');
        $this->jual([$p->id => 3], $b);

        $this->assertSame('Rp 10.000', $this->pemasukan($a));
        $this->assertSame('Rp 15.000', $this->pemasukan($b));
        $this->assertSame('Rp 25.000', $this->pemasukan($owner));
    }

    private function pemasukan(User $user): string
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
}
