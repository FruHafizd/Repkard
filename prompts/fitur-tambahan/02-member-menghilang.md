# Fitur Tambahan 02 — Member Mulai Menghilang

**Model:** Sonnet · **Branch:** `feat/member-menghilang` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 2, fitur-list.md bagian 10

## Tujuan

Pegawai melihat daftar member aktif yang sudah lama tidak datang, lalu menghubungi mereka dan mencatat hasilnya, sebelum mereka berhenti jadi member.

## Definisi "mulai menghilang"

Member masuk daftar jika **semua** benar:
1. `members.status = active`,
2. punya membership berlaku hari ini (`currentMembership()` di `App\Models\Member`: tidak dibatalkan, `starts_on <= hari ini <= ends_on`),
3. membership itu **tidak sedang dibekukan** (`Membership::isFrozen()`),
4. `visits_remaining` bukan `0` (kuota habis = kasus "habis", bukan "menghilang"),
5. **hari tanpa kunjungan ≥ ambang peringatan**. Hari tanpa kunjungan dihitung dari check-in **`accepted`** terakhir (`CheckIn::RESULT_ACCEPTED`). Jika belum pernah check-in, dihitung dari `members.activated_at`. Check-in yang ditolak tidak dihitung sebagai kunjungan.

Ambang:
- `config/repkard.php` → `'inactive_warning_days' => (int) env('INACTIVE_WARNING_DAYS', 14)` dan `'inactive_danger_days' => (int) env('INACTIVE_DANGER_DAYS', 30)`.
- Tambahkan kedua key ke `.env.example`.

## Yang dikerjakan

### A. Query
Scope di `Member`: `scopeMulaiMenghilang(Builder $q, int $days)` sesuai definisi di atas. Hitung tanggal kunjungan terakhir dengan **subquery** (`addSelect` / `withMax` pada check-in accepted) — **jangan** memuat semua check-in ke PHP. Sediakan atribut `last_visit_at` dan `days_absent` untuk tabel.

### B. Tindak lanjut
`php artisan make:model MemberFollowUp -mf`:
`member_id` (FK cascade), `user_id` (FK users, pencatat), `contacted_at` timestamp, `channel` string — enum `App\Enums\FollowUpChannel` (`telepon` "Telepon", `chat` "Chat", `langsung` "Bertemu langsung", `lainnya` "Lainnya"), `outcome` string — enum `App\Enums\FollowUpOutcome` (`akan_datang` "Akan datang lagi", `sibuk` "Sedang sibuk", `sakit` "Sakit / cedera", `pindah` "Pindah kota", `tidak_terhubung` "Tidak bisa dihubungi", `lainnya` "Lainnya"), `note` text nullable, `next_contact_on` date nullable.
Relasi `Member hasMany followUps` + `latestFollowUp()` (`latestOfMany('contacted_at')`).

### C. Halaman "Member Menghilang" (panel admin)
1. **Filament Page** dengan tabel (`HasTable` + `InteractsWithTable`), bukan Resource kedua untuk model `Member` (supaya permission tidak bentrok dengan `MemberResource`). Ikon heroicon user-minus. Permission page dari `shield:generate`, berikan ke pegawai di `RoleSeeder`.
2. Kolom: foto kecil (route `member.photo`), nama, nomor member, HP (tautan `tel:`), terakhir datang, **absen X hari** (badge `warning` ≥ ambang peringatan, `danger` ≥ ambang bahaya), berlaku sampai, tindak lanjut terakhir (tanggal + hasil), hubungi lagi tanggal.
3. Default urut: absen terlama dulu. Filter: "Belum dihubungi 7 hari terakhir" (aktif secara default), "≥ ambang bahaya".
4. Hindari N+1 (eager load `latestFollowUp`, `user`).
5. Aksi baris **"Catat tindak lanjut"**: cara, hasil, catatan, hubungi lagi tanggal (opsional, ≥ hari ini). `contacted_at` = sekarang, `user_id` = user login.
6. Di halaman view member (`MemberResource`): bagian "Riwayat tindak lanjut".

### D. Dashboard
Tambahkan stat **"Mulai menghilang"** di `App\Filament\Widgets\AdminStatsOverview` (jumlah member dengan ambang peringatan), klik → halaman Member Menghilang.

## Kriteria selesai (tiap poin = test, pakai `Carbon::setTestNow()`)

- [ ] Check-in accepted terakhir 15 hari lalu → masuk daftar; 13 hari lalu → tidak.
- [ ] Belum pernah check-in, diaktifkan 20 hari lalu → masuk daftar.
- [ ] Hanya punya check-in **rejected** 2 hari lalu (accepted terakhir 20 hari lalu) → tetap masuk daftar.
- [ ] Membership dibekukan hari ini → tidak masuk.
- [ ] Membership sudah habis → tidak masuk.
- [ ] `visits_remaining = 0` → tidak masuk.
- [ ] Member yang dihubungi 3 hari lalu tidak tampil dengan filter default, tetapi tampil saat filter dimatikan.
- [ ] Mencatat tindak lanjut menyimpan `user_id` pencatat.
- [ ] Pegawai bisa membuka halaman; member → ditolak.
- [ ] Jumlah query untuk 30 member tidak bertambah per baris (uji dengan `DB::enableQueryLog()` atau cukup pastikan eager loading).

Tandai ✅ di fitur-list.md: 2 baris member menghilang.

## Commit / PR

`feat: daftar member mulai menghilang dan tindak lanjut`
