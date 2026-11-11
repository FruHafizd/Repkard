<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function pembayaranOleh(User $pencatat, ?User $pemilikMember = null): Payment
    {
        $memberUser = $pemilikMember ?? $this->user(UserRole::Member);
        $member = Member::factory()->create(['user_id' => $memberUser->id, 'status' => MemberStatus::Active]);

        return Payment::factory()->create(['member_id' => $member->id, 'recorded_by' => $pencatat->id]);
    }

    public function test_pegawai_hanya_melihat_pembayaran_yang_ia_catat(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        $milikA = $this->pembayaranOleh($a);
        $milikB = $this->pembayaranOleh($b);

        $this->actingAs($a);

        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB]);

        $this->get(PaymentResource::getUrl('view', ['record' => $milikA]))->assertOk();
        $this->get(PaymentResource::getUrl('view', ['record' => $milikB]))->assertNotFound();
        $this->get(route('payment.receipt', $milikB))->assertForbidden();
    }

    public function test_pegawai_dengan_view_all_payment_melihat_semua(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Pegawai);
        $milikB = $this->pembayaranOleh($b);

        Role::findByName(UserRole::Pegawai->value)->givePermissionTo('ViewAll:Payment');
        $a->forgetCachedPermissions();

        $this->actingAs($a->fresh());

        Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$milikB]);
        $this->get(route('payment.receipt', $milikB))->assertOk();
    }

    public function test_pegawai_tidak_diberi_view_all_payment_secara_default(): void
    {
        $this->assertFalse(Role::findByName(UserRole::Pegawai->value)->hasPermissionTo('ViewAll:Payment'));
        $this->assertTrue(Role::findByName(UserRole::Owner->value)->hasPermissionTo('ViewAll:Payment'));
    }

    public function test_hanya_owner_bisa_membatalkan_pembayaran(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $payment = $this->pembayaranOleh($pegawai);

        $this->actingAs($pegawai);
        Livewire::test(ListPayments::class)
            ->assertActionHidden(TestAction::make('batalkan')->table($payment));
        $this->assertFalse($pegawai->can('cancel', $payment));
        $this->assertNull($payment->fresh()->cancelled_at);

        $owner = $this->user(UserRole::Owner);
        $this->actingAs($owner);
        Livewire::test(ListPayments::class)
            ->callAction(TestAction::make('batalkan')->table($payment), ['reason' => 'salah input']);

        $payment->refresh();
        $this->assertNotNull($payment->cancelled_at);
        $this->assertSame($owner->id, $payment->cancelled_by);
        $this->assertSame('salah input', $payment->cancel_reason);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_member_tidak_bisa_mengunduh_kuitansi_member_lain(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $userA = $this->user(UserRole::Member);
        $userB = $this->user(UserRole::Member);
        $milikA = $this->pembayaranOleh($pegawai, $userA);
        $milikB = $this->pembayaranOleh($pegawai, $userB);

        $this->actingAs($userA);

        $this->get(route('payment.receipt', $milikA))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('payment.receipt', $milikB))->assertForbidden();
    }

    public function test_kuitansi_butuh_login(): void
    {
        $payment = $this->pembayaranOleh($this->user(UserRole::Pegawai));

        $this->get(route('payment.receipt', $payment))->assertRedirect();
    }

    public function test_pegawai_mengaktifkan_member_lewat_modal(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $plan = MembershipPlan::factory()->create(['price' => 250000]);
        $member = Member::factory()->create(['selected_plan_id' => $plan->id]);

        $this->actingAs($pegawai);

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('aktifkan')->table($member), [
                'face_checked' => true,
                'plan_id' => $plan->id,
                'method' => 'transfer',
                'amount' => 250000,
                'proof_path' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertMatchesRegularExpression('/^RPK-\d{4}-0001$/', $member->member_number);
        $payment = $member->payments()->firstOrFail();
        $this->assertSame($pegawai->id, $payment->recorded_by);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_aktivasi_wajib_centang_cocokkan_wajah(): void
    {
        $plan = MembershipPlan::factory()->create();
        $member = Member::factory()->create();
        $this->actingAs($this->user(UserRole::Pegawai));

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('aktifkan')->table($member), [
                'face_checked' => false,
                'plan_id' => $plan->id,
                'method' => 'cash',
                'amount' => 1000,
            ])
            ->assertHasFormErrors(['face_checked']);

        $this->assertSame(MemberStatus::Pending, $member->fresh()->status);
    }

    public function test_tolak_pendaftaran_menghapus_user_member_dan_foto(): void
    {
        $user = $this->user(UserRole::Member);
        $path = 'member-photos/tolak.jpg';
        Storage::disk('local')->put($path, 'x');
        $member = Member::factory()->create(['user_id' => $user->id, 'photo_path' => $path]);

        $this->actingAs($this->user(UserRole::Pegawai));

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('tolak')->table($member), ['reason' => 'foto palsu']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertNull(Member::withTrashed()->find($member->id));
        Storage::disk('local')->assertMissing($path);
    }
}
