# Langkah 03 — Data Member & Foto Private

**Model:** Sonnet · **Branch:** `fase-1/03-member-foto` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 4.1b dan 9, fitur-list.md bagian 3

## Tujuan

Pegawai bisa mendaftarkan member di meja depan beserta fotonya. Foto disimpan private dan hanya bisa dibuka oleh yang berhak.

## Yang dikerjakan

### A. Enum & tabel
1. `app/Enums/MemberStatus.php`: `Pending = 'pending'` ("Menunggu aktivasi"), `Active = 'active'` ("Aktif"), `Inactive = 'inactive'` ("Nonaktif"), dengan `label()`. Implement `Filament\Support\Contracts\HasLabel` dan `HasColor` (warna bawaan Filament: `warning`, `success`, `gray`).
2. `php artisan make:model Member -mf`:

   | Kolom | Tipe |
   |---|---|
   | `user_id` | foreignId unique, cascadeOnDelete |
   | `member_number` | string nullable unique (diisi saat aktivasi, langkah 06) |
   | `member_seq` | unsignedInteger nullable unique (langkah 06) |
   | `phone` | string |
   | `gender` | string(1) — `L` / `P` |
   | `birth_date` | date nullable |
   | `address` | text nullable |
   | `emergency_contact_name` | string nullable |
   | `emergency_contact_phone` | string nullable |
   | `photo_path` | string nullable |
   | `status` | string, default `pending` (cast ke `MemberStatus`) |
   | `selected_plan_id` | foreignId nullable → `membership_plans`, nullOnDelete |
   | `activated_at` | timestamp nullable |
   | `activated_by` | foreignId nullable → `users`, nullOnDelete |
   | timestamps + softDeletes | |

3. Relasi: `User hasOne Member`, `Member belongsTo User`, `Member belongsTo MembershipPlan (selectedPlan)`.

### B. Foto
1. `app/Services/MemberPhotoService.php`:
   - `store(UploadedFile $file): string` — baca dengan Intervention Image, `cover(600, 800)` (rasio 3:4), simpan JPEG kualitas 85 ke disk `local` path `member-photos/{uuid}.jpg`, kembalikan path.
   - `delete(?string $path): void`.
   - Tolak file bukan gambar dan > 5 MB (validasi juga di form).
2. Route `GET /media/member-photos/{member}` (nama `member.photo`), middleware `auth`, cek `Gate::authorize('viewPhoto', $member)`, kembalikan `Storage::disk('local')->response(...)` dengan header `Cache-Control: private, max-age=300`. 404 bila tidak ada foto.
3. `MemberPolicy::viewPhoto`: owner & pegawai boleh semua; member hanya miliknya sendiri.
4. Saat foto diganti atau member di-force-delete, file lama dihapus.

### C. Resource Member (panel admin)
1. `php artisan make:filament-resource Member --panel=admin --view --soft-deletes`.
2. **Create** (pendaftaran oleh pegawai) — form berisi: nama, email (opsional), nomor HP, jenis kelamin, tanggal lahir, alamat, kontak darurat (nama + HP), foto (`FileUpload` image, disk `local`, maks 5 MB — atau upload lalu proses lewat `MemberPhotoService`), paket pilihan (hanya paket aktif).
   - Dalam satu DB transaction: buat `User` (role `member`, password acak, `must_change_password = true`, `email_verified_at = now()`; bila email kosong pakai `{hp}@member.repkard.local`) lalu buat `Member` status `pending`.
   - Setelah simpan, tampilkan password sementara sekali di notifikasi.
3. **Table**: foto kecil (lewat route `member.photo`), nomor member, nama, HP, status (badge), paket pilihan. Search: nama, HP, nomor member. Filter status.
4. **View**: semua data + foto besar.
5. **Edit**: data kontak & foto. Pegawai/owner bisa ubah status ke `inactive` (nonaktifkan).
6. **Hapus**: hanya owner (soft delete).

### D. Policy & izin
`MemberPolicy`: owner semua; pegawai `viewAny`, `view`, `create`, `update`, `viewPhoto` (tanpa delete/restore/forceDelete); member hanya `view`/`viewPhoto` miliknya sendiri. Tambahkan permission pegawai di `RoleSeeder`.

## Kriteria selesai (tiap poin = test)

- [ ] Pegawai membuat member → ada User role `member` + Member status `pending`, foto tersimpan di `storage/app/private/member-photos/`.
- [ ] Foto **tidak** bisa diakses lewat `/storage/...`.
- [ ] Route `member.photo`: tamu → redirect login; member A → 403 untuk foto member B; member A → 200 untuk fotonya sendiri; pegawai → 200.
- [ ] Pegawai tidak bisa menghapus member (403); owner bisa.
- [ ] Upload file non-gambar ditolak.
- [ ] Pencarian berdasarkan nomor HP menemukan member.

## Di luar langkah ini

Ambil foto dari webcam = langkah 04. Aktivasi & nomor member = langkah 06.

## Commit

`feat: data member, foto private, pendaftaran oleh pegawai`
