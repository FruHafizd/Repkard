# Fitur Tambahan 07 — Personal Trainer: Jadwal Sesi & Komisi

**Model:** Sonnet · **Branch:** `feat/pt-sesi-komisi` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 6, fitur-list.md bagian 10, `app/Services/PtService.php` dan `app/Models/PtSubscription.php` (dari langkah 06)

## Tujuan

Sesi PT dijadwalkan, lalu ditandai selesai / tidak datang / batal. Sesi berkurang otomatis. Owner melihat komisi tiap trainer per bulan; trainer melihat komisinya sendiri.

## Aturan bisnis

| Status akhir | Potong sesi? | Dapat komisi? |
|---|:-:|:-:|
| Selesai | Ya | Ya |
| Tidak datang (*no-show*) | Ya | Ya |
| Dibatalkan | Tidak | Tidak |

Komisi = `trainer_profiles.commission_per_session` **saat sesi ditandai** (disalin ke sesi, supaya perubahan tarif tidak mengubah riwayat).

## Yang dikerjakan

### A. Tabel
`PtSession` (`-mf`): `pt_subscription_id` (FK cascade), `trainer_id` (FK users), `scheduled_at` (datetime), `duration_minutes` (unsignedSmallInteger default 60), `status` string — enum `App\Enums\PtSessionStatus`: `scheduled` "Terjadwal", `completed` "Selesai", `no_show` "Tidak datang", `cancelled` "Dibatalkan" (dengan warna badge bawaan Filament), `commission_amount` (unsignedBigInteger nullable), `finished_at`, `finished_by` (FK nullable), `cancel_reason` nullable, `note` nullable, `created_by`, timestamps.

### B. `PtService` (tambah method)
1. `schedule(PtSubscription $sub, Carbon $at, int $duration, User $by): PtSession`
   - `$sub->isUsable()`, dan tanggal `$at` ≤ `expires_on`, dan `$at` tidak di masa lalu,
   - **tidak overbook**: `sessions_remaining` − jumlah sesi `scheduled` yang belum ditandai > 0 ("Sisa sesi sudah terjadwal semua"),
   - **tidak bentrok**: trainer tidak punya sesi `scheduled`/`completed` lain yang waktunya tumpang tindih; begitu juga member yang sama,
   - `trainer_id` disalin dari subscription.
2. `finish(PtSession $s, PtSessionStatus $status, User $by, ?string $note)` — hanya dari `scheduled` ke `completed`/`no_show`; transaction + `lockForUpdate` subscription; `sessions_remaining` − 1 (tolak jika sudah 0); `commission_amount` = komisi trainer saat ini; `finished_at`/`finished_by`.
3. `cancel(PtSession $s, User $by, string $reason)` — hanya dari `scheduled`; tidak memotong sesi.

### C. UI panel admin
1. **`PtSessionResource`** (label "Jadwal PT"; untuk trainer label navigasi "Sesi Saya"):
   - Tabel dikelompokkan per tanggal, default filter "Hari ini & akan datang"; filter trainer, status, rentang tanggal.
   - Kolom: jam, durasi, member, trainer, sisa sesi, status.
   - Create: pilih klien (hanya subscription `isUsable()`; label "Nama member — sisa N sesi"), tanggal & jam, durasi.
   - Aksi baris: **Selesai**, **Tidak datang**, **Batalkan** (alasan wajib) — hanya untuk status `scheduled`.
   - Izin: owner & pegawai semua (`ViewAll:PtSession` + `Create/Update:PtSession` untuk pegawai di `RoleSeeder`). Trainer: `ViewAny/View/Create/Update:PtSession` tetapi **hanya sesi miliknya** (query + Policy) dan hanya bisa membuat sesi untuk kliennya sendiri.
2. **Page "Komisi Trainer"**: pilih bulan (default bulan ini). Per trainer: jumlah sesi selesai, tidak datang, total komisi (jumlah `commission_amount` sesi `completed`+`no_show` yang `finished_at` di bulan itu). Owner melihat semua trainer; trainer hanya baris dirinya (permission page diberikan ke trainer; filter di query). Pegawai tidak diberi akses.

### D. Panel member
Page "Personal Trainer" (dari langkah 06): tambah **jadwal akan datang** dan **riwayat sesi** (tanggal, trainer, status). Hanya milik member login.

## Kriteria selesai

Test PHPUnit (pakai `Carbon::setTestNow()`):
- [ ] Sesi selesai → `sessions_remaining` −1, `commission_amount` = tarif saat itu; tarif diubah setelahnya → komisi sesi lama tidak berubah.
- [ ] Tidak datang → sesi terpotong + komisi; dibatalkan → tidak keduanya.
- [ ] Menandai sesi yang sudah selesai sekali lagi → ditolak.
- [ ] Paket 2 sesi dengan 2 sesi terjadwal → jadwal ke-3 ditolak.
- [ ] Trainer bentrok jam (10:00–11:00 vs 10:30) → ditolak; jam 11:00 tepat → boleh.
- [ ] Jadwal setelah `expires_on` atau di masa lalu → ditolak.
- [ ] Trainer A tidak bisa melihat/menandai sesi trainer B (403), dan tidak bisa menjadwalkan klien trainer B.
- [ ] Laporan komisi bulan November hanya menghitung sesi yang ditandai di November (sesi 30 Nov 23:30 WITA masuk November).
- [ ] Pegawai tidak bisa membuka Komisi Trainer (403).

Test Playwright (`e2e/pt.spec.ts`): pegawai menjadwalkan sesi → trainer login → tandai Selesai → sisa sesi berkurang di panel member.

Tandai ✅ di fitur-list.md: "Jadwalkan sesi PT", "Tandai sesi selesai / tidak datang / batal", "Lihat sisa sesi & jadwal PT", "Laporan komisi trainer".

## Commit / PR

`feat: jadwal sesi PT dan komisi trainer`
