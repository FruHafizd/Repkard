# Langkah 05 — Landing Page, Daftar Mandiri, Verifikasi Email, Wajib Ganti Password

**Model:** Sonnet · **Branch:** `fase-1/05-landing-registrasi` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 4.1 dan 12, fitur-list.md bagian 0 dan 1

## Tujuan

Pengunjung melihat paket di landing page, membuat akun sendiri, memverifikasi email, melengkapi profil + foto, lalu melihat status "Menunggu aktivasi". Akun buatan pegawai/owner wajib mengganti password saat login pertama.

## Yang dikerjakan

### A. Landing page (`/`)
1. Ganti route `/` di `routes/web.php` ke controller `LandingController@index` → view `resources/views/landing.blade.php`.
2. Isi: nama gym (`config('app.name')`), daftar paket `MembershipPlan::active()` — nama, deskripsi, durasi, kuota, harga — dan tombol **"Daftar"** per paket ke `/member/register?plan={id}`. Tombol "Masuk" ke `/member/login`.
3. Tampilan **polos**: HTML semantik + kelas Tailwind dasar dari `resources/css/app.css` (via `@vite`). Tanpa warna/gambar custom — desain menyusul dari Figma.

### B. Registrasi member (panel member)
1. `App\Models\User` implement `Illuminate\Contracts\Auth\MustVerifyEmail`.
2. `MemberPanelProvider`: tambahkan `->registration(Register::class)`, `->emailVerification()`, `->passwordReset()`.
3. `app/Filament/Member/Pages/Auth/Register.php` extends `Filament\Auth\Pages\Register`:
   - override `form(Schema $schema)`: nama, email, nomor HP, password, konfirmasi password, paket (select dari paket aktif; default dari query `?plan=`; wajib).
   - override `handleRegistration(array $data): Model` — dalam satu DB transaction: buat User, beri role `member`, buat `Member` (status `pending`, `phone`, `selected_plan_id`). Gender/foto diisi nanti.
   - Biarkan rate limit bawaan Filament aktif.
4. Di local, email terkirim ke log (`MAIL_MAILER=log` sudah diset). Jangan ubah mailer.

### C. Lengkapi profil (panel member)
1. Page `app/Filament/Member/Pages/LengkapiProfil.php`: jenis kelamin, tanggal lahir, alamat, kontak darurat, **foto wajah** (`FileUpload` image, maks 5 MB, proses lewat `MemberPhotoService::store`). Tampilkan teks: "Foto ini akan dicocokkan dengan wajah Anda saat aktivasi di gym."
2. Middleware `EnsureMemberProfileComplete` (daftarkan di `authMiddleware` panel member): bila `gender` atau `photo_path` kosong → redirect ke `LengkapiProfil` (kecuali sedang di halaman itu / logout).
3. Setelah profil lengkap, member **tidak bisa** mengganti foto sendiri lagi (ganti foto lewat persetujuan = Fase 2).

### D. Dashboard member saat `pending`
Widget `PendingActivationWidget` (hanya tampil jika status `pending`): "Akun Anda menunggu aktivasi", nama paket pilihan, total bayar (`Rp ...`), dan petunjuk: "Datang ke gym, tunjukkan wajah Anda ke petugas, dan lakukan pembayaran. Kartu member akan langsung terbit." Tombol ganti paket (select paket aktif → update `selected_plan_id`, hanya selama `pending`).

### E. Wajib ganti password (kedua panel)
1. Middleware `EnsurePasswordChanged` di `authMiddleware` **kedua** panel: bila `must_change_password` → redirect ke page `GantiPassword` di panel tersebut.
2. Page `GantiPassword` (buat di kedua panel, atau satu class dipakai keduanya): password lama, password baru (min 8, confirmed). Setelah sukses: `must_change_password = false`, notifikasi, redirect ke dashboard.

## Kriteria selesai (tiap poin = test)

- [ ] `/` menampilkan paket aktif saja, tidak menampilkan paket nonaktif.
- [ ] Registrasi membuat User role `member` + Member `pending` dengan `selected_plan_id` sesuai `?plan=`.
- [ ] User belum verifikasi email → diarahkan ke halaman verifikasi.
- [ ] Member terverifikasi tanpa foto → diarahkan ke Lengkapi Profil; setelah diisi → dashboard menampilkan widget pending.
- [ ] Member `pending` bisa ganti paket; member `active` tidak bisa.
- [ ] User dengan `must_change_password = true` (pegawai maupun member) diarahkan ke Ganti Password dan tidak bisa membuka halaman lain sampai selesai.
- [ ] Registrasi tidak bisa memilih paket nonaktif (validasi server).

## Commit

`feat: landing page, registrasi member, verifikasi email, wajib ganti password`
