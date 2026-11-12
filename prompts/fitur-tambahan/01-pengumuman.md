# Fitur Tambahan 01 — Pengumuman

**Model:** Haiku · **Branch:** `feat/pengumuman` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 1, fitur-list.md bagian 10

## Tujuan

Owner dan pegawai bisa membuat pengumuman (mis. "Gym tutup saat Lebaran", "Alat baru datang"). Member melihatnya di panel member, dan pengumuman tertentu tampil di landing page.

## Yang dikerjakan

### A. Tabel & model
`php artisan make:model Announcement -mf`:

| Kolom | Tipe | Catatan |
|---|---|---|
| `title` | string(150) | |
| `body` | text | teks biasa, maks 5000 karakter |
| `starts_at` | timestamp | default saat dibuat |
| `ends_at` | timestamp nullable | kosong = tidak berakhir |
| `is_pinned` | boolean default false | disematkan di atas |
| `show_on_landing` | boolean default false | |
| `created_by` | foreignId → users | |

Scope `published()`: `starts_at <= now()` dan (`ends_at` null atau `ends_at > now()`), urut `is_pinned` desc lalu `starts_at` desc.

### B. Panel admin
1. `php artisan make:filament-resource Announcement --panel=admin --view` — label "Pengumuman", ikon heroicon megaphone.
2. Form: judul, isi (`Textarea`, maks 5000), mulai tayang (`DateTimePicker`, default sekarang), berakhir (opsional, harus setelah mulai), sematkan, tampilkan di landing page.
3. Table: judul, status (Tayang / Terjadwal / Berakhir — dihitung dari tanggal), disematkan, di landing, dibuat oleh, tanggal. Filter status.
4. Policy + permission:
   - Buat permission custom **`ManageAll:Announcement`** (owner punya otomatis; pegawai tidak diberi).
   - Pegawai diberi `ViewAny`, `View`, `Create`, `Update:Announcement` di `RoleSeeder`.
   - `update`: punya `Update:Announcement` **dan** (`created_by` = dirinya **atau** punya `ManageAll:Announcement`).
   - `delete`: hanya yang punya `Delete:Announcement` (pegawai tidak diberi).

### C. Tampil untuk member
1. Widget `AnnouncementsWidget` di dashboard panel member, paling atas: maks 3 pengumuman `published()`; tampil untuk member `pending` maupun `active`. Kalau kosong, widget tidak tampil.
2. Page "Pengumuman" di panel member: semua pengumuman `published()`, terbaru dulu, dengan paginasi.
3. Isi pengumuman **selalu di-escape**: tampilkan dengan `{!! nl2br(e($body)) !!}`. Jangan `{!! $body !!}`.

### D. Landing page
Di `resources/views/landing.blade.php`, di atas bagian paket: tampilkan pengumuman `published()` yang `show_on_landing = true` (maks 2). Ikuti token desain yang sudah dipakai di landing.

## Kriteria selesai (tiap poin = test)

- [ ] Pengumuman yang belum mulai atau sudah berakhir tidak tampil di panel member maupun landing.
- [ ] Pengumuman disematkan tampil paling atas.
- [ ] Isi `<script>alert(1)</script>` tampil sebagai teks, bukan dijalankan (cek HTML ter-escape).
- [ ] Pegawai bisa membuat pengumuman dan mengubah miliknya sendiri, tetapi mengubah milik orang lain → 403, menghapus → 403.
- [ ] Owner bisa mengubah dan menghapus semua pengumuman.
- [ ] Member tidak bisa membuka resource Pengumuman di `/admin`.
- [ ] Hanya pengumuman `show_on_landing` yang muncul di `/`.

Tandai ✅ di fitur-list.md: 3 baris pengumuman.

## Commit / PR

`feat: pengumuman untuk member dan landing page`
