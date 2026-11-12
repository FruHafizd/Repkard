<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Filament\Member\Pages\PersonalTrainer;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\PtPackages\Pages\CreatePtPackage;
use App\Filament\Resources\PtPackages\Pages\ListPtPackages;
use App\Filament\Resources\PtSubscriptions\Pages\ListPtSubscriptions;
use App\Filament\Resources\PtSubscriptions\Pages\ViewPtSubscription;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\AkanHabisTable;
use App\Filament\Widgets\CheckInHariIniTable;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PtPackage;
use App\Models\PtSubscription;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\PtService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalTrainerTest extends TestCase
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

    private function member(bool $withMembership = true): Member
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active]);

        if ($withMembership) {
            Membership::factory()->create(['member_id' => $member->id]);
        }

        return $member;
    }

    private function paket(array $attrs = []): PtPackage
    {
        return PtPackage::create($attrs + [
            'name' => 'PT 10 Sesi',
            'sessions_count' => 10,
            'price' => 1500000,
            'validity_days' => 60,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function jual(?Member $member = null, ?User $trainer = null, ?PtPackage $paket = null): PtSubscription
    {
        return app(PtService::class)->sell(
            $member ?? $this->member(),
            $paket ?? $this->paket(),
            $trainer ?? $this->user(UserRole::Trainer),
            PaymentMethod::Cash,
            1500000,
            $this->user(UserRole::Pegawai),
        );
    }

    public function test_trainer_hanya_bisa_membuka_klien_ptnya_sendiri(): void
    {
        $trainer = $this->user(UserRole::Trainer);
        $lain = $this->user(UserRole::Trainer);
        $milik = $this->jual(trainer: $trainer);
        $bukanMilik = $this->jual(trainer: $lain);

        $this->actingAs($trainer)->get('/admin/pt-subscriptions')->assertOk();
        $this->actingAs($trainer)->get("/admin/pt-subscriptions/{$milik->id}")->assertOk();
        // Trainer membuka klien milik trainer lain → 403.
        $this->actingAs($trainer)->get("/admin/pt-subscriptions/{$bukanMilik->id}")->assertForbidden();

        Livewire::actingAs($trainer)->test(ListPtSubscriptions::class)
            ->assertCanSeeTableRecords([$milik])
            ->assertCanNotSeeTableRecords([$bukanMilik]);
    }

    public function test_trainer_tidak_bisa_membuka_menu_dan_data_lain(): void
    {
        $trainer = $this->user(UserRole::Trainer);
        $member = $this->member();
        $payment = Payment::factory()->create(['member_id' => $member->id]);

        foreach (['/admin/members', "/admin/members/{$member->id}", '/admin/payments', "/admin/payments/{$payment->id}", '/admin/scan-check-in', '/admin/kasir', '/admin/users', '/admin/sales'] as $url) {
            // 403, atau 404 bila resource membatasi query ke catatan sendiri: yang penting datanya tidak tampil.
            $this->assertContains($this->actingAs($trainer)->get($url)->getStatusCode(), [403, 404], $url);
        }

        $this->actingAs($trainer)->get(route('payment.receipt', $payment))->assertForbidden();

        $this->actingAs($trainer)->get('/admin')->assertOk();
        foreach ([AdminStatsOverview::class, AkanHabisTable::class, CheckInHariIniTable::class] as $widget) {
            $this->assertFalse($widget::canView());
        }
    }

    public function test_pegawai_dan_owner_masih_melihat_widget_dashboard(): void
    {
        foreach ([UserRole::Owner, UserRole::Pegawai] as $role) {
            $this->actingAs($this->user($role));
            $this->assertTrue(AdminStatsOverview::canView());
        }
    }

    public function test_jual_paket_ke_member_tanpa_membership_berlaku_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PT hanya untuk member dengan membership aktif');

        try {
            $this->jual(member: $this->member(withMembership: false));
        } finally {
            $this->assertSame(0, PtSubscription::count());
            $this->assertSame(0, Payment::count());
        }
    }

    public function test_memilih_user_non_trainer_sebagai_trainer_ditolak(): void
    {
        foreach ([$this->user(UserRole::Pegawai), User::factory()->create(['is_active' => false])->assignRole(UserRole::Trainer->value)] as $bukanTrainer) {
            try {
                $this->jual(trainer: $bukanTrainer);
                $this->fail('Seharusnya ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, PtSubscription::count());
            }
        }
    }

    public function test_paket_nonaktif_tidak_bisa_dijual(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->jual(paket: $this->paket(['is_active' => false]));
    }

    public function test_penjualan_membuat_subscription_dan_payment_pt(): void
    {
        $member = $this->member();
        $trainer = $this->user(UserRole::Trainer);

        $sub = app(PtService::class)->sell($member, $this->paket(), $trainer, PaymentMethod::Transfer, 1400000, $this->user(UserRole::Pegawai));

        $this->assertSame(10, $sub->sessions_total);
        $this->assertSame(10, $sub->sessions_remaining);
        $this->assertSame('2026-11-11', $sub->starts_on->toDateString());
        $this->assertSame('2027-01-09', $sub->expires_on->toDateString());
        $this->assertTrue($sub->isUsable());

        $payment = Payment::sole();
        $this->assertSame(PaymentType::Pt, $payment->type);
        $this->assertNull($payment->membership_id);
        $this->assertSame($sub->id, $payment->pt_subscription_id);
        $this->assertSame(1500000, $payment->original_amount);
        $this->assertSame(1400000, $payment->amount);
        $this->assertSame($member->id, $payment->member_id);
    }

    public function test_is_usable_mengikuti_pembatalan_sesi_dan_kedaluwarsa(): void
    {
        $sub = $this->jual();
        $this->assertSame('Aktif', $sub->statusLabel());

        $sub->update(['sessions_remaining' => 0]);
        $this->assertFalse($sub->isUsable());
        $this->assertSame('Habis', $sub->statusLabel());

        $sub->update(['sessions_remaining' => 3, 'expires_on' => '2026-11-11']);
        $this->assertTrue($sub->fresh()->isUsable());

        $sub->update(['expires_on' => '2026-11-10']);
        $this->assertSame('Kedaluwarsa', $sub->fresh()->statusLabel());

        $sub->update(['expires_on' => '2026-12-01', 'cancelled_at' => now()]);
        $this->assertFalse($sub->fresh()->isUsable());
        $this->assertSame('Dibatalkan', $sub->fresh()->statusLabel());
    }

    public function test_aksi_jual_paket_pt_di_member(): void
    {
        $member = $this->member();
        $paket = $this->paket();
        $trainer = $this->user(UserRole::Trainer);

        Livewire::actingAs($this->user(UserRole::Pegawai))->test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction(TestAction::make('jualPt'), [
                'pt_package_id' => $paket->id,
                'trainer_id' => $trainer->id,
                'method' => 'cash',
                'amount' => 1500000,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, PtSubscription::where('member_id', $member->id)->where('trainer_id', $trainer->id)->count());
        $this->assertSame(PaymentType::Pt, Payment::sole()->type);
    }

    public function test_kuitansi_payment_pt_bisa_diunduh(): void
    {
        $this->jual();
        $payment = Payment::sole();

        $this->assertSame(
            'Paket PT: PT 10 Sesi (10 sesi) — Trainer '.$payment->ptSubscription->trainer->name,
            $payment->itemLabel(),
        );

        $response = $this->actingAs($this->user(UserRole::Owner))->get(route('payment.receipt', $payment));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pegawai_tidak_bisa_mengubah_komisi_atau_membuat_paket_pt(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $trainer = $this->user(UserRole::Trainer);
        TrainerProfile::create(['user_id' => $trainer->id, 'commission_per_session' => 50000]);

        $this->actingAs($pegawai)->get('/admin/pt-packages/create')->assertForbidden();
        $this->actingAs($pegawai)->get("/admin/users/{$trainer->id}/edit")->assertForbidden();
        $this->actingAs($pegawai)->get('/admin/pt-packages')->assertOk();

        Livewire::actingAs($pegawai)->test(CreatePtPackage::class)->assertForbidden();
        Livewire::actingAs($pegawai)->test(EditUser::class, ['record' => $trainer->getKey()])->assertForbidden();

        $this->assertSame(50000, $trainer->trainerProfile()->first()->commission_per_session);
    }

    public function test_owner_mengatur_komisi_dan_paket_pt(): void
    {
        $owner = $this->user(UserRole::Owner);
        $trainer = $this->user(UserRole::Trainer);

        Livewire::actingAs($owner)->test(EditUser::class, ['record' => $trainer->getKey()])
            ->fillForm([
                'role' => UserRole::Trainer->value,
                'trainerProfile.specialties' => 'Fat loss',
                'trainerProfile.commission_per_session' => 75000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(75000, $trainer->trainerProfile()->first()->commission_per_session);

        Livewire::actingAs($owner)->test(CreatePtPackage::class)
            ->fillForm(['name' => 'PT 5', 'sessions_count' => 5, 'validity_days' => 30, 'price' => 800000, 'sort_order' => 0, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pt_packages', ['name' => 'PT 5', 'sessions_count' => 5]);
        Livewire::actingAs($owner)->test(ListPtPackages::class)->assertOk();
    }

    public function test_hanya_owner_bisa_ganti_trainer_dan_membatalkan(): void
    {
        $sub = $this->jual();
        $baru = $this->user(UserRole::Trainer);

        Livewire::actingAs($this->user(UserRole::Pegawai))->test(ViewPtSubscription::class, ['record' => $sub->getKey()])
            ->assertActionHidden('gantiTrainer')
            ->assertActionHidden('batalkan');

        $owner = $this->user(UserRole::Owner);
        Livewire::actingAs($owner)->test(ViewPtSubscription::class, ['record' => $sub->getKey()])
            ->callAction('gantiTrainer', ['trainer_id' => $baru->id, 'reason' => 'Trainer cuti'])
            ->assertHasNoActionErrors()
            ->callAction('batalkan', ['reason' => 'Member pindah'])
            ->assertHasNoActionErrors();

        $sub->refresh();
        $this->assertSame($baru->id, $sub->trainer_id);
        $this->assertTrue($sub->isCancelled());
        $this->assertSame('Member pindah', $sub->cancel_reason);
        $this->assertSame(1, Payment::count()); // tidak otomatis refund
    }

    public function test_member_hanya_melihat_paket_pt_miliknya(): void
    {
        $milik = $this->jual();
        $lain = $this->jual();
        $milik->member->user->assignRole(UserRole::Member->value);

        Filament::setCurrentPanel('member');
        $this->actingAs($milik->member->user);

        $html = Livewire::test(PersonalTrainer::class)->assertOk()->html();
        $this->assertStringContainsString($milik->trainer->name, $html);
        $this->assertStringNotContainsString($lain->trainer->name, $html);

        $kosong = Member::factory()->create(['status' => MemberStatus::Active]);
        $kosong->user->assignRole(UserRole::Member->value);
        $this->actingAs($kosong->user);

        Livewire::test(PersonalTrainer::class)->assertSee('Tanyakan paket personal trainer ke petugas.');
    }
}
