# Langkah 01 — Role, Izin & Akses Panel

**Model:** Sonnet · **Branch:** `fase-1/01-role-izin` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 3, fitur-list.md bagian 1–2

## Tujuan

Tiga role (`owner`, `pegawai`, `member`) berfungsi. Setiap role hanya bisa masuk panel miliknya. Owner bisa mengelola akun pegawai dan izin role.

## Yang dikerjakan

### A. Enum & User model
1. Buat `app/Enums/UserRole.php` (backed enum string): `Owner = 'owner'`, `Pegawai = 'pegawai'`, `Member = 'member'`, dengan method `label()` → "Owner", "Pegawai", "Member".
2. Migration baru di tabel `users`: tambah `phone` (string, nullable), `is_active` (boolean, default true), `must_change_password` (boolean, default false).
3. `App\Models\User`:
   - pakai trait `Spatie\Permission\Traits\HasRoles`,
   - implement `Filament\Models\Contracts\FilamentUser`,
   - `canAccessPanel(Panel $panel)`:
     - harus `is_active === true`, lalu
     - panel `admin` → punya role `owner` atau `pegawai`,
     - panel `member` → punya role `member`,
     - selain itu `false`.
   - cast `is_active` dan `must_change_password` ke boolean.

### B. Filament Shield
1. Publish config Shield (`php artisan vendor:publish --tag=filament-shield-config`; cek nama tag di `vendor/bezhansalleh/filament-shield` bila gagal).
2. Di `config/filament-shield.php`:
   - `super_admin.name` → `'owner'`,
   - `panel_user.enabled` → `false`.
3. Daftarkan plugin Shield **hanya** di `AdminPanelProvider` (`BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()`). Ikuti `php artisan shield:install admin` bila perintah itu yang melakukannya; kalau perintah interaktif, jawab sesuai poin ini.
4. Halaman Role (Shield) hanya boleh dibuka owner.

### C. Resource "Pegawai" (panel admin)
1. `php artisan make:filament-resource User --panel=admin --view` lalu ubah:
   - label navigasi & model: "Pegawai"; query hanya user dengan role `owner` atau `pegawai`.
   - Form: nama, email, nomor HP, role (pilih `owner`/`pegawai`), status aktif (toggle), password (hanya saat create; isi otomatis password acak 12 karakter dan tampilkan sekali di notifikasi setelah simpan; set `must_change_password = true`).
   - Table: nama, email, role, status aktif. Search nama/email.
   - **Tidak ada aksi hapus** — pegawai dinonaktifkan lewat toggle.
2. `UserPolicy`:
   - owner: semua kecuali `delete`/`forceDelete` (selalu `false`),
   - pegawai: hanya `viewAny` dan `view`,
   - member: semua `false`.
3. Owner tidak boleh menonaktifkan dirinya sendiri (validasi di form + policy `update` untuk field itu).

### D. Seeder
1. `RoleSeeder`: buat role `owner`, `pegawai`, `member` (pakai `firstOrCreate`).
2. Jalankan `php artisan shield:generate --all --panel=admin --option=permissions`, lalu di `RoleSeeder` berikan role `pegawai` permission `ViewAny:User` dan `View:User` saja.
3. `DatabaseSeeder`: panggil `RoleSeeder`; **hanya jika** `app()->environment('local')` buat:
   - `owner@repkard.test` (role owner),
   - `pegawai@repkard.test` (role pegawai),
   - password keduanya `password`, `email_verified_at` terisi.
4. Hapus pembuatan "Test User" bawaan dari `DatabaseSeeder`.

## Kriteria selesai (tiap poin = test)

- [ ] Owner bisa membuka `/admin`, tidak bisa membuka `/member` (403).
- [ ] Pegawai bisa membuka `/admin`, tidak bisa membuka `/member`.
- [ ] Member bisa membuka `/member`, tidak bisa membuka `/admin`.
- [ ] User `is_active = false` ditolak di kedua panel.
- [ ] Pegawai bisa membuka list Pegawai, tetapi halaman create dan edit memberi 403.
- [ ] Owner bisa membuat pegawai baru; user baru punya `must_change_password = true`.
- [ ] Tidak ada user yang bisa menghapus akun pegawai.
- [ ] Pegawai tidak bisa membuka halaman Role (Shield).
- [ ] `php artisan migrate:fresh --seed` berjalan tanpa error.

## Di luar langkah ini

Halaman "wajib ganti password" dikerjakan di langkah 05. Di langkah ini cukup kolomnya.

## Commit

`feat: role owner/pegawai/member, akses panel, kelola pegawai`
