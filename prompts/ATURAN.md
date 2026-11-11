# Aturan Umum untuk Setiap Prompt

Baca file ini sampai habis sebelum mengerjakan prompt langkah mana pun. Aturan di sini berlaku untuk semua langkah.

## 1. Sebelum mulai

1. Baca `CLAUDE.md`, lalu bagian `prd.md` dan `fitur-list.md` yang disebut di prompt langkah.
2. Pastikan working tree bersih: `git status`. Kalau tidak bersih, **berhenti dan laporkan**.
3. Buat branch dari `main`: `git switch main && git switch -c <nama-branch-di-prompt>`.
4. Jalankan `php artisan test` — harus hijau sebelum kamu mengubah apa pun.

## 2. Saat mengerjakan

- **Kerjakan hanya yang ada di prompt langkah ini.** Jangan mengerjakan langkah berikutnya, jangan menambah fitur "sekalian", jangan refactor kode yang tidak diminta.
- **Jangan menebak API Filament.** Proyek ini memakai **Filament 5**, yang berbeda dari Filament 2/3 yang mungkin kamu ingat:
  - Form/infolist memakai `Filament\Schemas\Schema` (bukan `Filament\Forms\Form`).
  - Action memakai `Filament\Actions\Action` (bukan `Filament\Tables\Actions\Action`).
  - Halaman auth ada di `Filament\Auth\Pages\*`.
  - Kalau ragu, **buka kode di `vendor/filament/`** dan ikuti yang ada di sana.
- **Pakai generator artisan**, jangan menulis boilerplate dengan tangan:
  `php artisan make:model Nama -mf`, `php artisan make:filament-resource Nama --panel=admin`, `php artisan make:policy`, `php artisan make:test`.
- **Tampilan:** pakai komponen default Filament dan Tailwind polos. Jangan menambah warna, font, CSS custom, atau ikon dekoratif — desain menyusul dari Figma.
- **Teks UI dalam Bahasa Indonesia** (label, tombol, notifikasi, pesan validasi).
- Kalau ada yang tidak jelas, bertentangan, atau API yang diminta tidak ada: **berhenti dan tanya**. Jangan memilih sendiri diam-diam.

## 3. Konvensi bersama (wajib sama di semua langkah)

| Hal | Aturan |
|---|---|
| Nama role (spatie) | `owner`, `pegawai`, `member` — pakai enum `App\Enums\UserRole` |
| Super admin Shield | role `owner` |
| Status member (`members.status`) | `pending` (Menunggu aktivasi), `active`, `inactive` — enum `App\Enums\MemberStatus`. "Habis" dan "Beku" **dihitung** dari tabel `memberships`, tidak disimpan di `members`. |
| Uang | integer rupiah (`unsignedBigInteger`), tanpa desimal. Tampilkan dengan format `Rp 200.000`. |
| Tanggal membership | kolom `date` (`starts_on`, `ends_on`), inklusif |
| File pribadi (foto, bukti bayar) | disk `local` (`storage/app/private`). **Jangan** disk `public`. Sajikan lewat route yang dicek dengan Policy. |
| Izin | Setiap Resource punya Policy. Format nama permission Shield: `ViewAny:Member`, `Create:Member`, dst. **Policy mengecek permission** (`$user->can('Update:Member')`), **bukan nama role** — supaya Owner bisa mengubah izin pegawai dari halaman Role tanpa ubah kode. |
| Arti "pegawai boleh X" di prompt | Berikan permission X ke role `pegawai` di `RoleSeeder` (izin default). Owner = super admin Shield, otomatis punya semua permission. |
| Aturan yang tidak boleh diubah lewat halaman Role | Ditulis keras di Policy: kepemilikan data (member hanya data sendiri), dan aksi yang disebut "tidak ada yang bisa" (mis. hapus pegawai) → selalu `false`. |
| Akses read-only | Role yang hanya boleh melihat tetap bisa membuka halaman list/view, tetapi Policy menolak create/update/delete (bukan sekadar tombol disembunyikan). |
| Rahasia | Hanya dari `.env` lewat `config()`. Jangan hardcode. Tambahkan key kosongnya ke `.env.example`. |

## 4. Test

- Setiap "Kriteria selesai" di prompt harus punya **Feature test** (PHPUnit, folder `tests/Feature`).
- Test hak akses wajib menguji **request langsung ke URL/aksi** dengan user tiap role, bukan hanya cek tombol.
- Pakai `RefreshDatabase`. Buat role lewat seeder di `setUp()` bila perlu.
- Sebelum commit: `php artisan test` hijau dan `vendor/bin/pint --dirty` sudah dijalankan.

## 5. Selesai

1. Ubah status baris yang selesai di `fitur-list.md` dari ⬜ menjadi ✅ (hanya baris yang benar-benar selesai).
2. Commit dengan pesan yang ada di prompt langkah. **Jangan push, jangan merge.**
3. Laporkan dalam format ini:

```
## Hasil langkah NN
Branch: ...
File baru / diubah: (daftar)
Test: X passed
Cara uji manual: (langkah singkat di browser)
Tidak selesai / ragu: (kosongkan kalau tidak ada)
```
