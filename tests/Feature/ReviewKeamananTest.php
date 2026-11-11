<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Member\Pages\LengkapiProfil;
use App\Filament\Pages\ScanCheckIn;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\CardTokenService;
use App\Services\CheckInService;
use App\Services\MemberCardService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi untuk temuan review keamanan Fase 1.
 */
class ReviewKeamananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
    }

    private function user(UserRole $role, array $attrs = []): User
    {
        return User::factory()->create($attrs)->assignRole($role->value);
    }

    private function memberAktifDenganKartu(?int $kuota = null): array
    {
        Storage::disk('local')->put('member-photos/x.jpg', 'x');
        $member = Member::factory()->create([
            'status' => MemberStatus::Active,
            'member_number' => 'RPK-2611-'.fake()->unique()->numerify('####'),
            'photo_path' => 'member-photos/x.jpg',
        ]);
        $card = app(MemberCardService::class)->issue($member, null);
        $ms = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['visit_quota' => $kuota])->id,
            'visits_remaining' => $kuota,
        ]);

        return [$member, $ms, app(CardTokenService::class)->make($card)];
    }

    /** Temuan #1: sesi pegawai yang sudah dinonaktifkan masih bisa mengunduh data lewat route custom. */
    public function test_user_nonaktif_ditolak_di_route_foto_kartu_dan_kuitansi(): void
    {
        [$member] = $this->memberAktifDenganKartu();
        $pegawai = $this->user(UserRole::Pegawai);
        $payment = Payment::factory()->create(['member_id' => $member->id, 'recorded_by' => $pegawai->id]);

        $urls = [
            route('member.photo', $member),
            route('member.card.pdf', $member),
            route('payment.receipt', $payment),
        ];

        foreach ($urls as $url) {
            $this->actingAs($pegawai)->get($url)->assertOk();
        }

        $pegawai->update(['is_active' => false]);

        foreach ($urls as $url) {
            $this->actingAs($pegawai->fresh())->get($url)->assertForbidden();
        }
    }

    public function test_member_nonaktif_ditolak_di_route_kartu_sendiri(): void
    {
        [$member] = $this->memberAktifDenganKartu();
        $member->user->update(['is_active' => false]);

        $this->actingAs($member->user->fresh())->get(route('member.card.pdf', $member))->assertForbidden();
    }

    /** Temuan #2: path file dari browser diteruskan apa adanya oleh FileUpload. */
    public function test_lengkapi_profil_menolak_path_file_yang_tidak_diunggah_sendiri(): void
    {
        Filament::setCurrentPanel('member');
        Storage::disk('local')->put('payment-proofs/rahasia.pdf', 'rahasia');
        $user = $this->user(UserRole::Member);
        Member::factory()->for($user)->create(['gender' => null, 'photo_path' => null]);

        Livewire::actingAs($user)->test(LengkapiProfil::class)
            ->fillForm(['gender' => 'L'])
            ->set('data.photo', ['palsu' => 'payment-proofs/rahasia.pdf'])
            ->call('simpan')
            ->assertHasFormErrors(['photo']);

        $this->assertNull($user->member->fresh()->photo_path);
        Storage::disk('local')->assertExists('payment-proofs/rahasia.pdf');
    }

    public function test_lengkapi_profil_tetap_menerima_unggahan_asli(): void
    {
        Filament::setCurrentPanel('member');
        $user = $this->user(UserRole::Member);
        Member::factory()->for($user)->create(['gender' => null, 'photo_path' => null]);

        Livewire::actingAs($user)->test(LengkapiProfil::class)
            ->fillForm(['gender' => 'L', 'photo' => UploadedFile::fake()->image('f.jpg', 600, 800)])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $path = $user->member->fresh()->photo_path;
        $this->assertStringStartsWith('member-photos/', $path);
        Storage::disk('local')->assertExists($path);
    }

    /**
     * Temuan #3: kuota dibaca dari snapshot yang bisa basi (REPEATABLE READ di MySQL) saat dua scanner
     * memproses kartu yang sama. Disimulasikan: setelah model membership dibaca dengan sisa 1,
     * transaksi lain sudah memakai kunjungan terakhir.
     */
    public function test_kunjungan_terakhir_tidak_bisa_dipakai_dua_kali_saat_bersamaan(): void
    {
        [, $ms, $token] = $this->memberAktifDenganKartu(kuota: 1);
        $service = app(CheckInService::class);
        $result = $service->inspect($token);
        $this->assertTrue($result->allowed);

        $dibaca = 0;
        Event::listen('eloquent.retrieved: '.Membership::class, function () use (&$dibaca, $ms): void {
            // Pembacaan ke-2 di dalam confirm() = pengecekan ulang kuota (non-locking read).
            if (++$dibaca === 2) {
                DB::table('memberships')->where('id', $ms->id)->update(['visits_remaining' => 0]);
            }
        });

        $checkIn = $service->confirm($result, $this->user(UserRole::Pegawai));

        $this->assertSame('rejected', $checkIn->result);
        $this->assertSame('Kuota kunjungan habis', $checkIn->reject_reason);
        $this->assertSame(0, $ms->fresh()->visits_remaining);
    }

    /** Temuan #4: token panel scan bisa diisi langsung dari browser, melewati rate limit scan(). */
    public function test_token_scan_tidak_bisa_diubah_dari_browser(): void
    {
        [, , $token] = $this->memberAktifDenganKartu();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user(UserRole::Pegawai))->test(ScanCheckIn::class)
            ->set('token', $token);
    }
}
