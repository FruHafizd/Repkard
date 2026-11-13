<?php

namespace Tests\Feature;

use App\Models\PtPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketPtPublikTest extends TestCase
{
    use RefreshDatabase;

    private function paket(array $attrs = []): PtPackage
    {
        return PtPackage::create($attrs + [
            'name' => 'PT 8 Sesi',
            'sessions_count' => 8,
            'price' => 800000,
            'validity_days' => 60,
            'is_active' => true,
        ]);
    }

    public function test_halaman_paket_pt_hanya_menampilkan_yang_aktif(): void
    {
        $this->paket(['name' => 'PT Hemat']);
        $this->paket(['name' => 'PT Lama', 'is_active' => false]);

        $this->get('/paket-pt')
            ->assertOk()
            ->assertSee('PT Hemat')
            ->assertSee('Rp 800.000')
            ->assertSee('Rp 100.000')
            ->assertDontSee('PT Lama');
    }

    public function test_beranda_menampilkan_paket_pt_dan_link_navigasi(): void
    {
        $this->paket(['name' => 'PT Hemat']);

        $this->get('/')
            ->assertOk()
            ->assertSee('PT Hemat')
            ->assertSee(route('paket-pt'), false);
    }

    public function test_beranda_menyembunyikan_bagian_pt_saat_tidak_ada_paket(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Lihat semua paket PT');
        $this->get('/paket-pt')->assertOk()->assertSee('Belum ada paket Personal Trainer');
    }
}
