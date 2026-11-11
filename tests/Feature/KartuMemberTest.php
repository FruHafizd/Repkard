<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\CardTokenService;
use App\Services\MemberCardService;
use App\Services\MembershipService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class KartuMemberTest extends TestCase
{
    use RefreshDatabase;

    private CardTokenService $tokens;

    private MemberCardService $cards;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $this->tokens = app(CardTokenService::class);
        $this->cards = app(MemberCardService::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function jpeg(): string
    {
        $img = imagecreatetruecolor(60, 80);
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }

    private function memberAktif(?User $user = null, bool $foto = true): Member
    {
        Storage::disk('local')->put('member-photos/x.jpg', $this->jpeg());

        return Member::factory()->create([
            'user_id' => ($user ?? $this->user(UserRole::Member))->id,
            'status' => MemberStatus::Active,
            'member_number' => 'RPK-2611-'.fake()->unique()->numerify('####'),
            'photo_path' => $foto ? 'member-photos/x.jpg' : null,
        ]);
    }

    public function test_aktivasi_member_berfoto_otomatis_punya_kartu_versi_1(): void
    {
        Storage::disk('local')->put('member-photos/x.jpg', 'x');
        $member = Member::factory()->create(['photo_path' => 'member-photos/x.jpg']);

        app(MembershipService::class)->activate(
            $member,
            MembershipPlan::factory()->create(),
            PaymentMethod::Cash,
            200000,
            $this->user(UserRole::Pegawai),
        );

        $this->assertSame(1, $member->activeCard()?->version);
    }

    public function test_aktivasi_member_tanpa_foto_tidak_menerbitkan_kartu(): void
    {
        $member = Member::factory()->create(['photo_path' => null]);

        app(MembershipService::class)->activate(
            $member,
            MembershipPlan::factory()->create(),
            PaymentMethod::Cash,
            200000,
            $this->user(UserRole::Pegawai),
        );

        $this->assertNull($member->activeCard());
    }

    public function test_resolve_mengembalikan_kartu_yang_sama(): void
    {
        $card = $this->cards->issue($this->memberAktif(), null);

        $this->assertTrue($card->is($this->tokens->resolve($this->tokens->make($card))));
    }

    public function test_tanda_tangan_diubah_satu_karakter_ditolak(): void
    {
        $card = $this->cards->issue($this->memberAktif(), null);
        $token = $this->tokens->make($card);
        $last = substr($token, -1);
        $ubah = substr($token, 0, -1).($last === 'A' ? 'B' : 'A');

        $this->assertNull($this->tokens->resolve($ubah));
    }

    public function test_id_diubah_dengan_tanda_tangan_lama_ditolak(): void
    {
        $a = $this->cards->issue($this->memberAktif(), null);
        $b = $this->cards->issue($this->memberAktif(), null);
        $sigA = substr($this->tokens->make($a), strrpos($this->tokens->make($a), '.') + 1);

        $this->assertNull($this->tokens->resolve("RPK1.{$b->id}.{$b->version}.{$sigA}"));
    }

    public function test_terbit_ulang_mematikan_token_lama(): void
    {
        $member = $this->memberAktif();
        $v1 = $this->cards->issue($member, null);
        $tokenV1 = $this->tokens->make($v1);

        $v2 = $this->cards->issue($member, null, 'Kartu hilang');

        $this->assertSame(2, $v2->version);
        $this->assertNull($this->tokens->resolve($tokenV1));
        $this->assertTrue($v2->is($this->tokens->resolve($this->tokens->make($v2))));
        $this->assertSame('Diganti kartu baru: Kartu hilang', $v1->fresh()->revoke_reason);
    }

    public function test_kartu_dicabut_ditolak(): void
    {
        $card = $this->cards->issue($this->memberAktif(), null);
        $this->cards->revoke($card, $this->user(UserRole::Owner), 'Disalahgunakan');

        $this->assertNull($this->tokens->resolve($this->tokens->make($card)));
    }

    public function test_token_sampah_menghasilkan_null_tanpa_exception(): void
    {
        foreach (['1', str_repeat('a', 500), 'RPK1...', '', "  \n", 'RPK1.1.1.'.str_repeat('A', 43)] as $sampah) {
            $this->assertNull($this->tokens->resolve($sampah));
        }
    }

    public function test_secret_kosong_melempar_exception(): void
    {
        $card = $this->cards->issue($this->memberAktif(), null);
        config(['repkard.card_secret' => '']);

        $this->expectException(RuntimeException::class);
        $this->tokens->make($card);
    }

    public function test_pegawai_tidak_bisa_mencabut_kartu_tetapi_owner_bisa(): void
    {
        $member = $this->memberAktif();
        $this->cards->issue($member, null);

        $pegawai = $this->user(UserRole::Pegawai);
        $this->assertFalse($pegawai->can('revokeCard', $member));
        $this->actingAs($pegawai);
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->assertActionHidden('cabutKartu')
            ->call('mountAction', 'cabutKartu')
            ->assertNotNotified();
        $this->assertNotNull($member->activeCard());

        $this->actingAs($this->user(UserRole::Owner));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('cabutKartu'), ['reason' => 'Uji'])
            ->assertNotified();
        $this->assertNull($member->activeCard());
    }

    public function test_pegawai_bisa_terbitkan_ulang_kartu(): void
    {
        $member = $this->memberAktif();
        $this->cards->issue($member, null);

        $this->actingAs($this->user(UserRole::Pegawai));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction(TestAction::make('terbitkanUlangKartu'), ['reason' => 'Kartu hilang'])
            ->assertNotified('Kartu baru diterbitkan. Kartu lama tidak berlaku lagi.');

        $this->assertSame(2, $member->activeCard()->version);
    }

    public function test_member_hanya_bisa_unduh_pdf_kartu_miliknya(): void
    {
        $a = $this->user(UserRole::Member);
        $memberA = $this->memberAktif($a);
        $memberB = $this->memberAktif();
        $this->cards->issue($memberA, null);
        $this->cards->issue($memberB, null);

        $this->actingAs($a)->get(route('member.card.pdf', $memberB))->assertForbidden();

        $res = $this->actingAs($a)->get(route('member.card.pdf', $memberA));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
    }

    public function test_pegawai_bisa_unduh_pdf_dan_tanpa_kartu_aktif_404(): void
    {
        $member = $this->memberAktif();
        $this->actingAs($this->user(UserRole::Pegawai));

        $this->get(route('member.card.pdf', $member))->assertNotFound();

        $this->cards->issue($member, null);
        $this->get(route('member.card.pdf', $member))->assertOk();
    }

    public function test_tamu_diarahkan_login(): void
    {
        $member = $this->memberAktif();
        $this->cards->issue($member, null);

        $this->get(route('member.card.pdf', $member))->assertRedirect();
    }
}
