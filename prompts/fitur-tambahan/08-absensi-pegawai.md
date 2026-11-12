# Fitur Tambahan 08 — Absensi Pegawai

**Model:** Sonnet · **Branch:** `feat/absensi-pegawai` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 7, fitur-list.md bagian 10, `app/Filament/Forms/Components/WebcamPhoto.php`, `app/Services/MemberPhotoService.php`, `app/Http/Controllers/MemberPhotoController.php`

## Tujuan

Pegawai dan trainer absen masuk & pulang di panel admin dengan **swafoto webcam wajib** (supaya tidak bisa titip absen). Owner melihat rekap bulanan dan bisa mengoreksi.

## Yang dikerjakan

### A. Data
1. Migration `users`: tambah `work_start_time` dan `work_end_time` (time, nullable). Tampilkan di form `UserResource` untuk role pegawai & trainer.
2. `config/repkard.php`: `'late_tolerance_minutes' => (int) env('LATE_TOLERANCE_MINUTES', 10)`; tambahkan ke `.env.example`.
3. `StaffAttendance` (`-mf`): `user_id` (FK cascade), `clock_in_at` (timestamp), `clock_in_photo_path`, `clock_out_at` (nullable), `clock_out_photo_path` (nullable), `is_late` (boolean default false), `corrected_by` (FK nullable), `correction_reason` (text nullable), timestamps. Index (`user_id`, `clock_in_at`).

### B. Foto
`MemberPhotoService::store()` dan `storeFromDataUrl()`: tambah parameter terakhir `string $directory = 'member-photos'` (default tetap, pemanggil lama tidak berubah). Swafoto absensi disimpan di `staff-attendance/` disk `local`.

### C. `app/Services/AttendanceService.php`
1. `clockIn(User $user, string $photoDataUrl): StaffAttendance`
   - user aktif dan ber-role `pegawai` atau `trainer` (owner tidak absen),
   - tolak jika sudah ada absensi **hari ini** yang belum pulang: "Anda sudah absen masuk hari ini",
   - absensi hari sebelumnya yang belum pulang **tidak** menghalangi (nanti tampil "Lupa absen pulang" di rekap),
   - `is_late` = jam masuk > `work_start_time` + toleransi (false bila `work_start_time` kosong).
2. `clockOut(User $user, string $photoDataUrl): StaffAttendance` — absensi hari ini yang belum pulang; jika tidak ada: "Anda belum absen masuk hari ini".
3. `correct(StaffAttendance $a, Carbon $in, ?Carbon $out, string $reason, User $by)` — owner saja; `out` > `in`; alasan wajib; hitung ulang `is_late`.

### D. UI panel admin
1. **Page "Absensi"** (untuk pegawai & trainer; owner tidak melihat menu ini):
   - status hari ini (belum masuk / sudah masuk jam X / sudah pulang),
   - komponen `WebcamPhoto` + tombol besar **"Absen Masuk"** atau **"Absen Pulang"** (tombol nonaktif sampai foto diambil),
   - riwayat bulan ini milik sendiri (tanggal, masuk, pulang, durasi, terlambat).
   - Permission page diberikan ke pegawai & trainer di `RoleSeeder`.
2. **`StaffAttendanceResource`** (label "Rekap Absensi"):
   - kolom: nama, tanggal, masuk (+ foto kecil), pulang (+ foto kecil), durasi, badge Terlambat / Lupa pulang, dikoreksi;
   - filter bulan (default bulan ini) & orang;
   - aksi **"Koreksi"** (owner, `Correct:StaffAttendance`), alasan wajib;
   - ringkasan per orang untuk bulan terpilih (header widget / tabel kecil): hari hadir, terlambat, lupa pulang, total jam;
   - owner melihat semua (`ViewAll:StaffAttendance`); pegawai & trainer hanya miliknya.
3. Route foto `GET /media/absensi/{attendance}/{jenis}` (`jenis` = `masuk`|`pulang`), auth + Policy `view` (owner semua, user hanya miliknya), sama pola dengan `MemberPhotoController`.

## Kriteria selesai

Test PHPUnit (pakai `Carbon::setTestNow()`):
- [ ] Jam kerja 08:00, toleransi 10 menit: masuk 08:10 → tidak terlambat; 08:11 → terlambat.
- [ ] Absen masuk dua kali di hari yang sama → ditolak.
- [ ] Absen pulang tanpa absen masuk → ditolak.
- [ ] Kemarin lupa pulang → hari ini tetap bisa absen masuk; rekap menandai "Lupa pulang".
- [ ] Tanpa foto / data URL bukan gambar → ditolak.
- [ ] Owner tidak bisa absen (ditolak service).
- [ ] Pegawai A tidak bisa melihat absensi & foto pegawai B (403); owner bisa.
- [ ] Koreksi oleh pegawai → 403; oleh owner tanpa alasan → validasi gagal.
- [ ] Foto absensi tersimpan di `storage/app/private/staff-attendance/`, tidak bisa diakses lewat `/storage`.

Test Playwright (`e2e/absensi.spec.ts`): pegawai login → Absensi → ambil foto (kamera palsu Chromium khusus di file ini: `test.use({ permissions: ['camera'], launchOptions: { args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] } })`) → Absen Masuk → status berubah.

Tandai ✅ di fitur-list.md: 2 baris absensi.

## Commit / PR

`feat: absensi pegawai dengan swafoto`
