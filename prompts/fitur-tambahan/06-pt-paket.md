# Fitur Tambahan 06 — Personal Trainer: Role Trainer & Paket PT

**Model:** Sonnet · **Branch:** `feat/pt-paket` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 6, fitur-list.md bagian 10, `app/Models/User.php` (`canAccessPanel`), `database/seeders/RoleSeeder.php`

## Tujuan

Ada role baru **Trainer**. Owner mengatur trainer & paket PT. Pegawai menjual paket PT ke member aktif dan pembayarannya tercatat seperti pembayaran lain. (Jadwal sesi & komisi = langkah 07.)

## Yang dikerjakan

### A. Role trainer
1. `App\Enums\UserRole`: tambah `Trainer = 'trainer'` (label "Trainer").
2. `User::canAccessPanel()`: panel `admin` juga untuk role `trainer`.
3. `RoleSeeder`: buat role `trainer` dengan izin **minimal**: `ViewAny/View:PtPackage`, `ViewAny/View:PtSubscription` (hanya miliknya, lihat C).
4. **Hak akses trainer sempit.** Trainer tidak boleh melihat menu/data lain. Pastikan:
   - widget dashboard yang ada (`AdminStatsOverview`, `AkanHabisTable`, `CheckInHariIniTable`) punya `canView()` yang mensyaratkan permission yang tidak dimiliki trainer (mis. `ViewAny:Member`),
   - resource & page lain sudah dijaga Policy/permission (cek, jangan asumsikan).
5. "Pegawai" (`UserResource`): pilihan role ditambah `trainer`, dan `getEloquentQuery()` (sekarang hanya `owner`+`pegawai`) ikut menampilkan `trainer`. Tambah filter role.

### B. Profil trainer & paket
1. `TrainerProfile` (`-mf`): `user_id` (FK unique cascade), `specialties` (string nullable, mis. "Fat loss, strength"), `bio` (text nullable), `commission_per_session` (unsignedBigInteger default 0), timestamps. Relasi `User hasOne trainerProfile`.
   Field profil tampil di form `UserResource` hanya bila role = trainer (`visible()`); simpan lewat relasi. **Komisi hanya bisa diubah owner.**
2. `PtPackage` (`-mf`): `name`, `description` nullable, `sessions_count` (unsignedInteger ≥ 1), `price` (unsignedBigInteger), `validity_days` (unsignedInteger ≥ 1), `is_active` (default true), `sort_order`.
   **`PtPackageResource`** (label "Paket PT"): owner CRUD (tanpa hapus bila sudah terjual → nonaktifkan); pegawai & trainer lihat saja.
3. `PtSubscription` (`-mf`) — paket PT milik member: `member_id` (FK cascade), `pt_package_id` (FK restrict), `trainer_id` (FK users), `sessions_total`, `sessions_remaining`, `starts_on` (date), `expires_on` (date), `cancelled_at` nullable, `cancel_reason` nullable, `created_by` (FK users), timestamps. Method `isUsable()`: tidak dibatalkan, hari ini ≤ `expires_on`, `sessions_remaining > 0`.

### C. Penjualan
1. `App\Enums\PaymentType`: tambah `Pt = 'pt'` "Paket PT". Migration `payments`: tambah `pt_subscription_id` (FK nullable, nullOnDelete); tambahkan ke `$fillable` + relasi.
2. `app/Services/PtService.php` → `sell(Member $member, PtPackage $package, User $trainer, PaymentMethod $method, int $amount, User $by): PtSubscription` — satu transaction:
   - member wajib `active` dan punya `currentMembership()` ("PT hanya untuk member dengan membership aktif"),
   - paket aktif; `$trainer` aktif dan ber-role `trainer`,
   - subscription: mulai hari ini, `expires_on` = hari ini + `validity_days` − 1, sesi = `sessions_count`,
   - payment: `type = pt`, `membership_id` null, `pt_subscription_id`, `original_amount` = harga paket, `amount` = `$amount`.
3. Aksi **"Jual paket PT"** di `MemberActions` (owner & pegawai, permission custom `SellPt:Member`): pilih paket, trainer, metode, jumlah (default harga).
4. **`PtSubscriptionResource`** (label "Klien PT"): member, nomor, paket, trainer, sisa/total sesi, berlaku sampai, status (Aktif / Habis / Kedaluwarsa / Dibatalkan).
   - Pegawai & owner melihat semua (`ViewAll:PtSubscription` diberikan ke pegawai di `RoleSeeder`).
   - Trainer tanpa `ViewAll:PtSubscription` → hanya `trainer_id` = dirinya (di `getEloquentQuery()` **dan** Policy).
   - Owner bisa ganti trainer (aksi, alasan wajib) dan membatalkan (alasan wajib; tidak otomatis refund).
5. Kuitansi PDF & riwayat pembayaran (admin + panel member) harus tetap jalan untuk payment tanpa membership: tampilkan "Paket PT: {nama} ({n} sesi) — Trainer {nama}". Kolom **jenis** di tabel pembayaran menampilkan "Paket PT".
6. Panel member: page **"Personal Trainer"** — paket PT miliknya: trainer, sisa sesi, berlaku sampai. (Jadwal ditambah di langkah 07.) Kalau tidak punya paket: teks singkat "Tanyakan paket personal trainer ke petugas."

## Kriteria selesai (tiap poin = test)

- [ ] Trainer bisa login `/admin`, membuka Klien PT (hanya kliennya), tetapi `MemberResource`, `PaymentResource`, Scan Check-in, Kasir, dan widget dashboard → 403 / tidak tampil.
- [ ] Trainer membuka URL Klien PT milik trainer lain → 403.
- [ ] Jual paket PT ke member tanpa membership berlaku → ditolak.
- [ ] Memilih user non-trainer sebagai trainer → ditolak oleh service.
- [ ] Penjualan membuat subscription + payment `type = pt` dengan jumlah benar.
- [ ] Kuitansi payment PT bisa diunduh (200, `application/pdf`).
- [ ] Pegawai tidak bisa mengubah komisi trainer / membuat paket PT (403).
- [ ] Member melihat paket PT miliknya saja di panel member.
- [ ] Semua test lama tetap hijau.

Tandai ✅ di fitur-list.md: "Kelola trainer & komisi per sesi", "Kelola paket PT", "Jual paket PT ke member".

## Commit / PR

`feat: role trainer, paket PT, dan penjualan paket PT`
