<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Member\Pages\Auth\Register;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\PromoService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PromoTest extends TestCase
{
    use RefreshDatabase;

    private PromoService $promos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->promos = app(PromoService::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function plan(int $price = 200000): MembershipPlan
    {
        return MembershipPlan::factory()->create(['price' => $price]);
    }

    private function pending(): Member
    {
        return Member::factory()->create(['status' => MemberStatus::Pending]);
    }

    private function promo(array $attrs = []): PromoCode
    {
        return PromoCode::factory()->create($attrs + ['code' => 'HEMAT']);
    }

    private function bayar(Member $member, PromoCode $promo, ?MembershipPlan $plan = null): void
    {
        Payment::factory()->create([
            'member_id' => $member->getKey(),
            'promo_code_id' => $promo->getKey(),
        ]);
    }

    public function test_persen_20_pada_200000(): void
    {
        $this->promo(['discount_value' => 20]);
        $hasil = $this->promos->check('hemat', $this->plan(200000), $this->pending(), true);

        $this->assertTrue($hasil->valid);
        $this->assertSame(40000, $hasil->discount);
        $this->assertSame(160000, $hasil->finalPrice);
    }

    public function test_persen_dibulatkan_ke_bawah(): void
    {
        $this->promo(['discount_value' => 15]);
        $hasil = $this->promos->check('HEMAT', $this->plan(333333), $this->pending(), true);

        $this->assertSame(49999, $hasil->discount);
    }

    public function test_potongan_nominal_tidak_membuat_harga_negatif(): void
    {
        $this->promo(['discount_type' => DiscountType::Fixed, 'discount_value' => 300000]);
        $hasil = $this->promos->check('HEMAT', $this->plan(200000), $this->pending(), true);

        $this->assertSame(200000, $hasil->discount);
        $this->assertSame(0, $hasil->finalPrice);
    }

    public function test_kode_tidak_ada_atau_nonaktif(): void
    {
        $plan = $this->plan();
        $member = $this->pending();
        $this->promo(['code' => 'MATI', 'is_active' => false]);

        $this->assertSame('Kode promo tidak ditemukan', $this->promos->check('XXX', $plan, $member, true)->message);
        $this->assertFalse($this->promos->check('MATI', $plan, $member, true)->valid);
    }

    public function test_di_luar_periode(): void
    {
        $plan = $this->plan();
        $member = $this->pending();
        $this->promo(['code' => 'BESOK', 'starts_on' => now()->addDay()]);
        $this->promo(['code' => 'LALU', 'ends_on' => now()->subDay()]);

        $this->assertSame('Kode promo belum berlaku', $this->promos->check('BESOK', $plan, $member, true)->message);
        $this->assertSame('Kode promo sudah berakhir', $this->promos->check('LALU', $plan, $member, true)->message);
    }

    public function test_dibatasi_ke_paket_tertentu(): void
    {
        $promo = $this->promo();
        $promo->plans()->attach($this->plan());

        $hasil = $this->promos->check('HEMAT', $this->plan(), $this->pending(), true);

        $this->assertFalse($hasil->valid);
        $this->assertSame('Kode promo tidak berlaku untuk paket ini', $hasil->message);
    }

    public function test_paket_yang_termasuk_diterima(): void
    {
        $plan = $this->plan();
        $this->promo()->plans()->attach($plan);

        $this->assertTrue($this->promos->check('HEMAT', $plan, $this->pending(), true)->valid);
    }

    public function test_khusus_member_baru(): void
    {
        $this->promo(['new_members_only' => true]);
        $plan = $this->plan();
        $member = $this->pending();

        $this->assertSame('Kode promo khusus member baru', $this->promos->check('HEMAT', $plan, $member, false)->message);
        $this->assertTrue($this->promos->check('HEMAT', $plan, $member, true)->valid);
    }

    public function test_kuota_habis(): void
    {
        $promo = $this->promo(['max_uses' => 1, 'max_uses_per_member' => 5]);
        $this->bayar(Member::factory()->create(), $promo);

        $hasil = $this->promos->check('HEMAT', $this->plan(), $this->pending(), true);

        $this->assertSame('Kuota kode promo sudah habis', $hasil->message);
    }

    public function test_batas_per_member(): void
    {
        $promo = $this->promo(['max_uses' => 10, 'max_uses_per_member' => 1]);
        $member = Member::factory()->create();
        $this->bayar($member, $promo);

        $hasil = $this->promos->check('HEMAT', $this->plan(), $member, true);

        $this->assertSame('Anda sudah memakai kode promo ini', $hasil->message);
    }

    public function test_pembayaran_dibatalkan_tidak_dihitung(): void
    {
        $promo = $this->promo(['max_uses' => 1]);
        $member = Member::factory()->create();
        $this->bayar($member, $promo);
        Payment::query()->update(['cancelled_at' => now()]);

        $this->assertTrue($this->promos->check('HEMAT', $this->plan(), $member, true)->valid);
    }

    public function test_service_menolak_aktivasi_kedua_dengan_kode_max_uses_1(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $plan = $this->plan();
        $promo = $this->promo(['max_uses' => 1, 'discount_value' => 20]);
        $service = app(MembershipService::class);

        $service->activate($this->pending(), $plan, PaymentMethod::Cash, 160000, $pegawai, null, $promo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Kuota kode promo sudah habis');
        $service->activate($this->pending(), $plan, PaymentMethod::Cash, 160000, $pegawai, null, $promo);
    }

    public function test_payment_menyimpan_harga_asli_diskon_dan_kode(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $promo = $this->promo(['discount_value' => 20]);
        $membership = app(MembershipService::class)->activate(
            $this->pending(), $this->plan(200000), PaymentMethod::Cash, 160000, $pegawai, null, $promo,
        );

        $payment = $membership->member->payments()->first();

        $this->assertSame(200000, $payment->original_amount);
        $this->assertSame(40000, $payment->discount_amount);
        $this->assertSame($promo->getKey(), $payment->promo_code_id);
        $this->assertSame(160000, $payment->amount);
    }

    public function test_tanpa_promo_menyimpan_harga_asli_dan_diskon_nol(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $membership = app(MembershipService::class)->activate(
            $this->pending(), $this->plan(200000), PaymentMethod::Cash, 200000, $pegawai,
        );

        $payment = $membership->member->payments()->first();

        $this->assertSame(200000, $payment->original_amount);
        $this->assertSame(0, $payment->discount_amount);
        $this->assertNull($payment->promo_code_id);
    }

    public function test_registrasi_dengan_kode_tidak_valid_ditolak(): void
    {
        Filament::setCurrentPanel('member');
        $plan = $this->plan();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Budi', 'email' => 'budi@example.test', 'phone' => '0812345678',
                'password' => 'password-123', 'passwordConfirmation' => 'password-123',
                'selected_plan_id' => $plan->id, 'promo_code' => 'NGAWUR',
            ])
            ->call('register')
            ->assertHasFormErrors(['promo_code']);

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.test']);
    }

    public function test_registrasi_dengan_kode_valid_tersimpan(): void
    {
        Filament::setCurrentPanel('member');
        $plan = $this->plan();
        $this->promo();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Budi', 'email' => 'budi@example.test', 'phone' => '0812345678',
                'password' => 'password-123', 'passwordConfirmation' => 'password-123',
                'selected_plan_id' => $plan->id, 'promo_code' => 'hemat',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $this->assertSame('HEMAT', User::where('email', 'budi@example.test')->first()->member->requested_promo_code);
    }

    public function test_modal_aktifkan_memakai_kode_promo_dan_menolak_kode_salah(): void
    {
        $this->actingAs($this->user(UserRole::Pegawai));
        $plan = $this->plan(200000);
        $this->promo(['discount_value' => 20]);
        $member = Member::factory()->create(['selected_plan_id' => $plan->id, 'status' => MemberStatus::Pending]);
        $data = ['face_checked' => true, 'plan_id' => $plan->id, 'method' => 'cash', 'amount' => 160000];

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('aktifkan')->table($member), $data + ['promo_code' => 'NGAWUR']);
        $this->assertSame(MemberStatus::Pending, $member->fresh()->status);

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('aktifkan')->table($member), $data + ['promo_code' => 'hemat'])
            ->assertHasNoFormErrors();

        $payment = $member->payments()->firstOrFail();
        $this->assertSame(40000, $payment->discount_amount);
        $this->assertSame('HEMAT', $payment->promoCode->code);
    }

    public function test_owner_bisa_membuat_promo(): void
    {
        $this->actingAs($this->user(UserRole::Owner));
        Filament::setCurrentPanel('admin');

        Livewire::test(CreatePromoCode::class)
            ->fillForm([
                'code' => 'baru-10', 'name' => 'Promo Baru', 'discount_type' => 'percent',
                'discount_value' => 10, 'max_uses_per_member' => 1, 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('promo_codes', ['code' => 'BARU-10']);
    }

    public function test_pegawai_hanya_bisa_melihat_promo(): void
    {
        $promo = $this->promo();
        $this->actingAs($this->user(UserRole::Pegawai));
        Filament::setCurrentPanel('admin');

        $this->get('/admin/promo-codes')->assertOk();
        $this->get('/admin/promo-codes/create')->assertForbidden();
        $this->get("/admin/promo-codes/{$promo->id}/edit")->assertForbidden();
    }

    public function test_kode_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $owner = $this->user(UserRole::Owner);
        $dipakai = $this->promo(['code' => 'PAKAI']);
        $this->bayar(Member::factory()->create(), $dipakai);
        $kosong = $this->promo(['code' => 'KOSONG']);

        $this->assertFalse($owner->can('delete', $dipakai));
        $this->assertTrue($owner->can('delete', $kosong));
    }
}
