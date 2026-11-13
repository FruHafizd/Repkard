<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Filament\Member\Widgets\AnnouncementsWidget;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PengumumanTest extends TestCase
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

    private function member(MemberStatus $status = MemberStatus::Active): User
    {
        $user = $this->user(UserRole::Member);

        Member::factory()->for($user)->create([
            'status' => $status,
            'member_number' => 'RPK-2611-'.fake()->unique()->numerify('####'),
            'photo_path' => 'member-photos/x.jpg',
            'gender' => 'L',
        ]);

        return $user;
    }

    private function pengumuman(array $attrs = []): Announcement
    {
        return Announcement::factory()->create($attrs);
    }

    public function test_published_hanya_berisi_yang_sedang_tayang_dan_disematkan_di_atas(): void
    {
        $biasa = $this->pengumuman(['title' => 'Biasa', 'starts_at' => now()->subDays(2)]);
        $sematan = $this->pengumuman(['title' => 'Sematan', 'starts_at' => now()->subDays(5), 'is_pinned' => true]);
        $this->pengumuman(['title' => 'Belum mulai', 'starts_at' => now()->addDay()]);
        $this->pengumuman(['title' => 'Sudah berakhir', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subHour()]);

        $this->assertSame(
            [$sematan->id, $biasa->id],
            Announcement::query()->published()->pluck('id')->all(),
        );
    }

    public function test_dashboard_member_menampilkan_pengumuman_untuk_pending_dan_active(): void
    {
        $this->pengumuman(['title' => 'Gym tutup saat Lebaran']);
        $this->pengumuman(['title' => 'Belum tayang', 'starts_at' => now()->addDay()]);

        Filament::setCurrentPanel('member');
        $this->assertContains(AnnouncementsWidget::class, Filament::getPanel('member')->getWidgets());

        foreach ([MemberStatus::Pending, MemberStatus::Active] as $status) {
            Livewire::actingAs($this->member($status))
                ->test(AnnouncementsWidget::class)
                ->assertSee('Gym tutup saat Lebaran')
                ->assertDontSee('Belum tayang');
        }
    }

    public function test_widget_pengumuman_tidak_tampil_saat_kosong_atau_member_nonaktif(): void
    {
        $this->actingAs($this->member(MemberStatus::Active));
        Filament::setCurrentPanel('member');
        $this->assertFalse(AnnouncementsWidget::canView());

        $this->pengumuman(['title' => 'Ada']);
        $this->assertTrue(AnnouncementsWidget::canView());

        $this->actingAs($this->member(MemberStatus::Inactive));
        $this->assertFalse(AnnouncementsWidget::canView());
    }

    public function test_halaman_pengumuman_member_hanya_menampilkan_yang_tayang(): void
    {
        $this->pengumuman(['title' => 'Tayang sekarang']);
        $this->pengumuman(['title' => 'Sudah lewat', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);

        $this->actingAs($this->member())
            ->get('/member/announcements')
            ->assertOk()
            ->assertSee('Tayang sekarang')
            ->assertDontSee('Sudah lewat');
    }

    public function test_isi_pengumuman_selalu_di_escape(): void
    {
        $this->pengumuman(['title' => 'Berbahaya', 'body' => "<script>alert(1)</script>\nBaris dua"]);

        $this->actingAs($this->member())
            ->get('/member/announcements')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<br', false);

        Filament::setCurrentPanel('member');
        Livewire::actingAs($this->member())
            ->test(AnnouncementsWidget::class)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_landing_hanya_menampilkan_pengumuman_show_on_landing_yang_tayang_maks_dua(): void
    {
        $this->pengumuman(['title' => 'Jam libur Lebaran', 'show_on_landing' => true, 'is_pinned' => true]);
        $this->pengumuman(['title' => 'Alat baru', 'show_on_landing' => true]);
        $this->pengumuman(['title' => 'Ketiga di landing', 'show_on_landing' => true, 'starts_at' => now()->subDays(9)]);
        $this->pengumuman(['title' => 'Hanya member']);
        $this->pengumuman(['title' => 'Landing belum tayang', 'show_on_landing' => true, 'starts_at' => now()->addDay()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Jam libur Lebaran')
            ->assertSee('Alat baru')
            ->assertDontSee('Ketiga di landing')
            ->assertDontSee('Hanya member')
            ->assertDontSee('Landing belum tayang');
    }

    public function test_halaman_publik_pengumuman_menampilkan_semua_yang_tayang_di_landing(): void
    {
        $this->pengumuman(['title' => 'Satu', 'show_on_landing' => true]);
        $this->pengumuman(['title' => 'Dua', 'show_on_landing' => true]);
        $this->pengumuman(['title' => 'Tiga', 'show_on_landing' => true, 'starts_at' => now()->subDays(9)]);
        $this->pengumuman(['title' => 'Hanya member']);
        $this->pengumuman(['title' => 'Belum tayang', 'show_on_landing' => true, 'starts_at' => now()->addDay()]);

        $this->get('/pengumuman')
            ->assertOk()
            ->assertSee('Satu')
            ->assertSee('Dua')
            ->assertSee('Tiga')
            ->assertDontSee('Hanya member')
            ->assertDontSee('Belum tayang');

        $this->get('/')->assertSee(route('pengumuman'), false);
    }

    public function test_pegawai_bisa_membuat_pengumuman_dan_tercatat_pembuatnya(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        Livewire::actingAs($pegawai)
            ->test(CreateAnnouncement::class)
            ->fillForm([
                'title' => 'Alat baru datang',
                'body' => 'Leg press sudah bisa dipakai.',
                'starts_at' => now(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('announcements', [
            'title' => 'Alat baru datang',
            'created_by' => $pegawai->id,
        ]);
    }

    public function test_pegawai_bisa_mengubah_miliknya_tetapi_tidak_milik_orang_lain(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $milikSendiri = $this->pengumuman(['created_by' => $pegawai->id]);
        $milikOrangLain = $this->pengumuman(['created_by' => $this->user(UserRole::Owner)->id]);

        $this->actingAs($pegawai)
            ->get("/admin/announcements/{$milikSendiri->id}/edit")
            ->assertOk();

        $this->actingAs($pegawai)
            ->get("/admin/announcements/{$milikOrangLain->id}/edit")
            ->assertForbidden();

        $this->assertFalse($pegawai->can('update', $milikOrangLain));
    }

    public function test_pegawai_tidak_bisa_menghapus_pengumuman(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $milikSendiri = $this->pengumuman(['created_by' => $pegawai->id]);

        Livewire::actingAs($pegawai)
            ->test(EditAnnouncement::class, ['record' => $milikSendiri->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        $this->assertFalse($pegawai->can('delete', $milikSendiri));
        $this->assertDatabaseHas('announcements', ['id' => $milikSendiri->id]);
    }

    public function test_owner_bisa_mengubah_dan_menghapus_semua_pengumuman(): void
    {
        $owner = $this->user(UserRole::Owner);
        $milikOrangLain = $this->pengumuman(['created_by' => $this->user(UserRole::Pegawai)->id]);

        $this->actingAs($owner)
            ->get("/admin/announcements/{$milikOrangLain->id}/edit")
            ->assertOk();

        Livewire::actingAs($owner)
            ->test(EditAnnouncement::class, ['record' => $milikOrangLain->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('announcements', ['id' => $milikOrangLain->id]);
    }

    public function test_halaman_admin_pengumuman_bisa_dibuka_dan_difilter_status(): void
    {
        $owner = $this->user(UserRole::Owner);
        $tayang = $this->pengumuman(['title' => 'Tayang admin']);
        $this->pengumuman(['title' => 'Terjadwal admin', 'starts_at' => now()->addDay()]);

        $this->actingAs($owner)
            ->get('/admin/announcements')
            ->assertOk()
            ->assertSee('Tayang admin');

        $this->actingAs($owner)
            ->get("/admin/announcements/{$tayang->id}")
            ->assertOk()
            ->assertSee('Tayang admin');

        Livewire::actingAs($owner)
            ->test(ListAnnouncements::class)
            ->filterTable('status', 'terjadwal')
            ->assertCanSeeTableRecords(Announcement::query()->where('starts_at', '>', now())->get())
            ->assertCanNotSeeTableRecords([$tayang]);
    }

    public function test_member_tidak_bisa_membuka_resource_pengumuman_di_admin(): void
    {
        $this->actingAs($this->member())
            ->get('/admin/announcements')
            ->assertForbidden();
    }
}
