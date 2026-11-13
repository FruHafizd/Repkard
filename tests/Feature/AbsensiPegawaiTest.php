<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Absensi;
use App\Filament\Resources\StaffAttendances\Pages\ListStaffAttendances;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AbsensiPegawaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['repkard.late_tolerance_minutes' => 10]);
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole($role->value);
    }

    private function foto(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(300, 300));

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    private function service(): AttendanceService
    {
        return app(AttendanceService::class);
    }

    private function pesanValidasi(callable $fn): string
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return (string) collect($e->errors())->flatten()->first();
        }

        $this->fail('ValidationException tidak dilempar');
    }

    public function test_toleransi_keterlambatan_masuk_0810_tidak_terlambat_0811_terlambat(): void
    {
        $a = $this->user(UserRole::Pegawai, ['work_start_time' => '08:00:00']);
        $b = $this->user(UserRole::Trainer, ['work_start_time' => '08:00:00']);

        Carbon::setTestNow('2026-03-02 08:10:00');
        $this->assertFalse($this->service()->clockIn($a, $this->foto())->is_late);

        Carbon::setTestNow('2026-03-02 08:11:00');
        $this->assertTrue($this->service()->clockIn($b, $this->foto())->is_late);
    }

    public function test_tanpa_jam_kerja_tidak_pernah_terlambat(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        Carbon::setTestNow('2026-03-02 13:00:00');

        $this->assertFalse($this->service()->clockIn($pegawai, $this->foto())->is_late);
    }

    public function test_absen_masuk_dua_kali_di_hari_yang_sama_ditolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        Carbon::setTestNow('2026-03-02 08:00:00');
        $this->service()->clockIn($pegawai, $this->foto());

        Carbon::setTestNow('2026-03-02 09:00:00');
        $pesan = $this->pesanValidasi(fn () => $this->service()->clockIn($pegawai, $this->foto()));

        $this->assertSame('Anda sudah absen masuk hari ini', $pesan);
        $this->assertSame(1, StaffAttendance::count());
    }

    public function test_absen_pulang_tanpa_absen_masuk_ditolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        $pesan = $this->pesanValidasi(fn () => $this->service()->clockOut($pegawai, $this->foto()));

        $this->assertSame('Anda belum absen masuk hari ini', $pesan);
        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_absen_pulang_mengisi_jam_dan_foto_pulang(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        Carbon::setTestNow('2026-03-02 08:00:00');
        $this->service()->clockIn($pegawai, $this->foto());

        Carbon::setTestNow('2026-03-02 17:30:00');
        $absen = $this->service()->clockOut($pegawai, $this->foto());

        $this->assertSame('17:30', $absen->clock_out_at->format('H:i'));
        $this->assertSame('9 j 30 m', $absen->durationLabel());
        Storage::disk('local')->assertExists($absen->clock_out_photo_path);

        $pesan = $this->pesanValidasi(fn () => $this->service()->clockOut($pegawai, $this->foto()));
        $this->assertSame('Anda belum absen masuk hari ini', $pesan);
    }

    public function test_kemarin_lupa_pulang_hari_ini_tetap_bisa_masuk_dan_rekap_menandai_lupa_pulang(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        Carbon::setTestNow('2026-03-02 08:00:00');
        $kemarin = $this->service()->clockIn($pegawai, $this->foto());

        Carbon::setTestNow('2026-03-03 08:00:00');
        $hariIni = $this->service()->clockIn($pegawai, $this->foto());

        $this->assertNotSame($kemarin->id, $hariIni->id);
        $this->assertTrue($kemarin->fresh()->isForgotClockOut());
        $this->assertFalse($hariIni->fresh()->isForgotClockOut());

        $owner = $this->user(UserRole::Owner);
        Livewire::actingAs($owner)
            ->test(ListStaffAttendances::class)
            ->assertSee('Lupa pulang');
        $this->assertSame(1, Livewire::actingAs($owner)->test(ListStaffAttendances::class)->instance()->ringkasan()[0]['lupa_pulang']);
    }

    public function test_tanpa_foto_atau_bukan_gambar_ditolak_dan_tidak_menyimpan_apa_pun(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        foreach (['', 'data:text/plain;base64,'.base64_encode('halo'), 'data:image/png;base64,'.base64_encode('bukan gambar'), 'data:image/jpeg;base64,@@@'] as $buruk) {
            try {
                $this->service()->clockIn($pegawai, $buruk);
                $this->fail('Foto buruk seharusnya ditolak: '.$buruk);
            } catch (ValidationException) {
                // diharapkan
            }
        }

        $this->assertSame(0, StaffAttendance::count());
        $this->assertSame([], Storage::disk('local')->allFiles('staff-attendance'));
    }

    public function test_halaman_absensi_menolak_klik_tanpa_foto(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);

        Livewire::actingAs($pegawai)->test(Absensi::class)->call('absen');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_halaman_absensi_absen_masuk_lalu_pulang(): void
    {
        $trainer = $this->user(UserRole::Trainer);
        Carbon::setTestNow('2026-03-02 08:00:00');

        $page = Livewire::actingAs($trainer)->test(Absensi::class)
            ->assertSee('Belum absen masuk')
            ->set('data.photo', $this->foto())
            ->call('absen')
            ->assertSee('Sudah absen masuk pukul 08:00');

        $this->assertSame(1, StaffAttendance::where('user_id', $trainer->id)->count());

        Carbon::setTestNow('2026-03-02 17:00:00');
        $page->set('data.photo', $this->foto())
            ->call('absen')
            ->assertSee('Sudah absen pulang pukul 17:00');

        $this->assertNotNull(StaffAttendance::first()->clock_out_at);
    }

    public function test_owner_tidak_bisa_absen_dan_tidak_melihat_halaman_absensi(): void
    {
        $owner = $this->user(UserRole::Owner);

        $pesan = $this->pesanValidasi(fn () => $this->service()->clockIn($owner, $this->foto()));
        $this->assertSame('Hanya pegawai dan trainer aktif yang dapat absen.', $pesan);
        $this->assertSame(0, StaffAttendance::count());

        $this->actingAs($owner)->get('/admin/absensi')->assertForbidden();
        $this->actingAs($this->user(UserRole::Pegawai))->get('/admin/absensi')->assertOk();
        $this->actingAs($this->user(UserRole::Trainer))->get('/admin/absensi')->assertOk();
        $this->actingAs($this->user(UserRole::Member))->get('/admin/absensi')->assertForbidden();
    }

    public function test_pegawai_nonaktif_tidak_bisa_absen(): void
    {
        $pegawai = $this->user(UserRole::Pegawai, ['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->service()->clockIn($pegawai, $this->foto());
    }

    public function test_pegawai_tidak_bisa_melihat_absensi_dan_foto_pegawai_lain_owner_bisa(): void
    {
        $a = $this->user(UserRole::Pegawai);
        $b = $this->user(UserRole::Trainer);
        $owner = $this->user(UserRole::Owner);

        Carbon::setTestNow('2026-03-02 08:00:00');
        $absenA = $this->service()->clockIn($a, $this->foto());
        $absenB = $this->service()->clockIn($b, $this->foto());
        Carbon::setTestNow('2026-03-02 17:00:00');
        $this->service()->clockOut($b, $this->foto());

        $this->actingAs($a)->get("/media/absensi/{$absenA->id}/masuk")->assertOk();
        $this->actingAs($a)->get("/media/absensi/{$absenB->id}/masuk")->assertForbidden();
        $this->actingAs($a)->get("/media/absensi/{$absenB->id}/pulang")->assertForbidden();
        $this->actingAs($owner)->get("/media/absensi/{$absenB->id}/masuk")->assertOk();
        $this->actingAs($owner)->get("/media/absensi/{$absenB->id}/pulang")->assertOk();
        $this->actingAs($owner)->get("/media/absensi/{$absenA->id}/pulang")->assertNotFound();
        $this->actingAs($owner)->get("/media/absensi/{$absenA->id}/lainnya")->assertNotFound();
        $this->actingAs($this->user(UserRole::Member))->get("/media/absensi/{$absenA->id}/masuk")->assertForbidden();

        auth()->logout();
        $this->get("/media/absensi/{$absenA->id}/masuk")->assertRedirect();

        // Rekap: pegawai hanya melihat barisnya sendiri, owner semuanya.
        Livewire::actingAs($a)->test(ListStaffAttendances::class)
            ->assertCanSeeTableRecords([$absenA])
            ->assertCanNotSeeTableRecords([$absenB]);
        Livewire::actingAs($owner)->test(ListStaffAttendances::class)
            ->assertCanSeeTableRecords([$absenA, $absenB]);
    }

    public function test_pegawai_tidak_bisa_mengoreksi_dan_service_menolak(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $absen = StaffAttendance::factory()->create(['user_id' => $pegawai->id]);

        $this->assertFalse($pegawai->can('correct', $absen));
        $this->assertTrue($this->user(UserRole::Owner)->can('correct', $absen));

        Livewire::actingAs($pegawai)->test(ListStaffAttendances::class)
            ->assertActionHidden(TestAction::make('koreksi')->table($absen));

        try {
            $this->service()->correct($absen, now()->setTime(7, 0), null, 'Mau ubah sendiri', $pegawai);
            $this->fail('Seharusnya ditolak');
        } catch (AuthorizationException) {
            // diharapkan
        }

        $this->assertNull($absen->fresh()->corrected_by);
    }

    public function test_koreksi_owner_tanpa_alasan_gagal_validasi_dengan_alasan_berhasil_dan_hitung_ulang_terlambat(): void
    {
        $owner = $this->user(UserRole::Owner);
        $pegawai = $this->user(UserRole::Pegawai, ['work_start_time' => '08:00:00']);
        Carbon::setTestNow('2026-03-02 09:00:00');
        $absen = $this->service()->clockIn($pegawai, $this->foto());
        $this->assertTrue($absen->is_late);

        Livewire::actingAs($owner)->test(ListStaffAttendances::class)
            ->callAction(TestAction::make('koreksi')->table($absen), [
                'clock_in_at' => '2026-03-02 08:05:00',
                'clock_out_at' => '2026-03-02 17:00:00',
                'reason' => '',
            ])->assertHasActionErrors(['reason' => 'required']);
        $this->assertNull($absen->fresh()->corrected_by);

        Livewire::actingAs($owner)->test(ListStaffAttendances::class)->callAction(TestAction::make('koreksi')->table($absen), [
            'clock_in_at' => '2026-03-02 08:05:00',
            'clock_out_at' => '2026-03-02 17:00:00',
            'reason' => 'Mesin lambat, sebenarnya datang 08:05',
        ])->assertHasNoActionErrors();

        $absen->refresh();
        $this->assertFalse($absen->is_late);
        $this->assertSame('08:05', $absen->clock_in_at->format('H:i'));
        $this->assertSame('17:00', $absen->clock_out_at->format('H:i'));
        $this->assertSame($owner->id, $absen->corrected_by);
        $this->assertSame('Mesin lambat, sebenarnya datang 08:05', $absen->correction_reason);
    }

    public function test_koreksi_jam_pulang_sebelum_jam_masuk_ditolak(): void
    {
        $owner = $this->user(UserRole::Owner);
        Carbon::setTestNow('2026-03-02 12:00:00');
        $absen = StaffAttendance::factory()->create(['clock_in_at' => '2026-03-02 08:00:00']);

        Livewire::actingAs($owner)->test(ListStaffAttendances::class)
            ->callAction(TestAction::make('koreksi')->table($absen), [
                'clock_in_at' => '2026-03-02 08:00:00',
                'clock_out_at' => '2026-03-02 07:00:00',
                'reason' => 'Salah',
            ])->assertHasActionErrors(['clock_out_at']);

        $this->pesanValidasi(fn () => $this->service()->correct(
            $absen, Carbon::parse('2026-03-02 08:00:00'), Carbon::parse('2026-03-02 07:00:00'), 'Salah', $owner,
        ));
        $this->pesanValidasi(fn () => $this->service()->correct(
            $absen, Carbon::parse('2026-03-02 08:00:00'), null, '   ', $owner,
        ));
        $this->assertNull($absen->fresh()->corrected_by);
    }

    public function test_ringkasan_per_orang_hadir_terlambat_lupa_pulang_dan_total_jam(): void
    {
        $owner = $this->user(UserRole::Owner);
        $a = $this->user(UserRole::Pegawai, ['name' => 'Ani']);
        $b = $this->user(UserRole::Trainer, ['name' => 'Budi']);
        Carbon::setTestNow('2026-03-20 12:00:00');

        StaffAttendance::factory()->create(['user_id' => $a->id, 'clock_in_at' => '2026-03-02 08:00:00', 'clock_out_at' => '2026-03-02 16:00:00']);
        StaffAttendance::factory()->create(['user_id' => $a->id, 'clock_in_at' => '2026-03-03 08:30:00', 'clock_out_at' => '2026-03-03 12:30:00', 'is_late' => true]);
        StaffAttendance::factory()->create(['user_id' => $a->id, 'clock_in_at' => '2026-03-04 08:00:00']); // lupa pulang
        StaffAttendance::factory()->create(['user_id' => $b->id, 'clock_in_at' => '2026-03-02 09:00:00', 'clock_out_at' => '2026-03-02 10:15:00']);
        StaffAttendance::factory()->create(['user_id' => $b->id, 'clock_in_at' => '2026-02-10 09:00:00', 'clock_out_at' => '2026-02-10 10:00:00']); // bulan lain

        $rows = Livewire::actingAs($owner)->test(ListStaffAttendances::class)->instance()->ringkasan();

        $this->assertSame([
            ['nama' => 'Ani', 'hadir' => 3, 'terlambat' => 1, 'lupa_pulang' => 1, 'total' => '12 j 0 m'],
            ['nama' => 'Budi', 'hadir' => 1, 'terlambat' => 0, 'lupa_pulang' => 0, 'total' => '1 j 15 m'],
        ], $rows);
    }

    public function test_foto_absensi_tersimpan_di_folder_private_dan_tidak_bisa_lewat_storage(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $absen = $this->service()->clockIn($pegawai, $this->foto());

        $this->assertStringStartsWith('staff-attendance/', $absen->clock_in_photo_path);
        Storage::disk('local')->assertExists($absen->clock_in_photo_path);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.local'));
        $this->assertNotContains(
            $this->actingAs($pegawai)->get('/storage/'.$absen->clock_in_photo_path)->status(),
            [200],
        );
    }

    public function test_izin_default_role(): void
    {
        $pegawai = $this->user(UserRole::Pegawai);
        $trainer = $this->user(UserRole::Trainer);
        $owner = $this->user(UserRole::Owner);

        foreach ([$pegawai, $trainer] as $user) {
            $this->assertTrue($user->can('View:Absensi'));
            $this->assertTrue($user->can('ViewAny:StaffAttendance'));
            $this->assertTrue($user->can('View:StaffAttendance'));
            $this->assertFalse($user->can('ViewAll:StaffAttendance'));
            $this->assertFalse($user->can('Correct:StaffAttendance'));
        }

        $this->assertTrue($owner->can('ViewAll:StaffAttendance'));
        $this->assertTrue($owner->can('Correct:StaffAttendance'));

        $this->actingAs($pegawai)->get('/admin/staff-attendances')->assertOk();
        $this->actingAs($owner)->get('/admin/staff-attendances')->assertOk();
        $this->actingAs($this->user(UserRole::Member))->get('/admin/staff-attendances')->assertForbidden();
    }

    public function test_jam_kerja_tersimpan_lewat_form_user(): void
    {
        $owner = $this->user(UserRole::Owner);
        $pegawai = $this->user(UserRole::Pegawai);

        Livewire::actingAs($owner)
            ->test(EditUser::class, ['record' => $pegawai->getKey()])
            ->fillForm(['work_start_time' => '08:00:00', 'work_end_time' => '16:00:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $pegawai->refresh();
        $this->assertSame('08:00', $pegawai->work_start_time);
        $this->assertSame('16:00', $pegawai->work_end_time);
    }
}
