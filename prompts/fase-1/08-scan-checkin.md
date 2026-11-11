# Langkah 08 — Scan Check-in

**Model:** Sonnet · **Branch:** `fase-1/08-scan-checkin` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 4.2 dan 6, fitur-list.md bagian 7

## Tujuan

Pegawai memindai kartu (kamera, scanner USB, atau foto kartu), melihat foto member besar, mencocokkan wajah, lalu mengonfirmasi. Semua percobaan scan tercatat.

## Yang dikerjakan

### A. Tabel `check_ins` (`CheckIn -mf`)
`member_id` FK nullable (nullOnDelete), `member_card_id` FK nullable, `membership_id` FK nullable, `method` string (`card` / `manual`), `result` string (`accepted` / `rejected`), `reject_reason` string nullable, `manual_reason` string nullable, `scanned_by` FK users, `checked_in_at` timestamp, timestamps.
Kolom check-out & snapshot **tidak** dibuat sekarang (Fase 2).

### B. `app/Services/CheckInService.php`
1. `inspect(string $token): CheckInResult` — **hanya membaca, tidak menyimpan apa pun**. Urutan pengecekan, berhenti di alasan pertama:
   1. `CardTokenService::resolve()` null → "Kartu tidak valid atau sudah tidak berlaku"
   2. member status bukan `active` → "Akun member tidak aktif"
   3. `currentMembership()` null → "Masa aktif sudah habis"
   4. hari ini di antara `frozen_from`–`frozen_until` → "Membership sedang dibekukan"
   5. `visits_remaining === 0` → "Kuota kunjungan habis"
   6. lolos → boleh diterima
   `CheckInResult` (class biasa / readonly): `allowed` bool, `reason` ?string, `member` ?Member, `card` ?MemberCard, `membership` ?Membership.
2. `confirm(CheckInResult $r, User $staff, string $method = 'card', ?string $manualReason = null): CheckIn` — **ulangi** pengecekan (jangan percaya hasil lama), lalu dalam transaction: kunci membership (`lockForUpdate`), kurangi `visits_remaining` bila tidak null, simpan check-in `accepted`.
3. `reject(CheckInResult $r, User $staff, string $reason): CheckIn` — simpan check-in `rejected`. Dipakai untuk: alasan otomatis dari `inspect()`, dan "Wajah tidak cocok" dari pegawai.
4. Scan yang ditolak otomatis **langsung disimpan** sebagai `rejected` (termasuk token tidak valid, dengan `member_id` null).

### C. Halaman scan (panel admin)
1. Page Filament `app/Filament/Pages/ScanCheckIn.php` (label "Scan Check-in", ikon bawaan heroicon) — hanya owner & pegawai. Generate permission page lewat `shield:generate`, berikan ke pegawai di `RoleSeeder` (juga `ViewAny:CheckIn` + `View:CheckIn`).
2. Scanner kamera: entry Vite `resources/js/scanner.js` yang memakai `html5-qrcode` (`Html5QrcodeScanner`, mendukung kamera **dan** pilih file gambar → ini untuk "scan dari foto kartu"). Tambahkan ke `vite.config.js` input, muat di view page dengan `@vite`. Saat terbaca → panggil `$wire.scan(decodedText)`; jeda 2 detik agar tidak dobel scan.
3. Input teks untuk **scanner USB** (scanner mengetik seperti keyboard + Enter): autofocus, `wire:keydown.enter="scan($event.target.value)"`, kosongkan setelah submit.
4. Method Livewire `scan(string $token)`:
   - rate limit 60 per menit per user (`RateLimiter`), lebih dari itu tampilkan notifikasi error,
   - panggil `inspect()`; bila ditolak → `reject()` langsung + tampilkan panel merah dengan alasan;
   - bila boleh → tampilkan panel hasil: **foto member besar** (≥ 300px, route `member.photo`), nama, nomor member, paket, berlaku sampai, sisa hari, sisa kuota; tombol **"Wajah cocok — Check-in"** dan **"Wajah tidak cocok — Tolak"**.
   - simpan hanya **token** di state Livewire, lalu `inspect()` lagi saat konfirmasi (jangan simpan ID member di state yang bisa diubah dari browser tanpa dicek ulang).
5. Warna hasil memakai warna bawaan Filament (`success` / `danger`), tanpa CSS custom.
6. Aksi **"Check-in manual"** di page: cari member (nama/HP/nomor) + alasan wajib (mis. "Kartu ketinggalan") → jalankan pengecekan 2–5 untuk member tsb, tampilkan foto, lalu konfirmasi dengan `method = manual`.

### D. Riwayat check-in
`CheckInResource` (panel admin, label "Riwayat Check-in"): kolom waktu, member, nomor, metode, hasil (badge), alasan, dicatat oleh. Filter tanggal (default hari ini), hasil, metode.
`CheckInPolicy`: owner semua (ubah/hapus); pegawai `viewAny` + `view` saja; member `view` hanya miliknya (dipakai langkah 09).

## Kriteria selesai (tiap poin = test)

- [ ] Masing-masing 5 alasan tolak di B.1 punya test dan tersimpan sebagai `rejected`.
- [ ] `inspect()` tidak menulis ke database (hitung jumlah baris sebelum/sesudah).
- [ ] Konfirmasi paket kuota 10 → `visits_remaining` jadi 9; paket tanpa batas tetap null.
- [ ] Kuota 1 dikonfirmasi dua kali berturut-turut → yang kedua ditolak "Kuota kunjungan habis".
- [ ] Kartu dicabut di antara `inspect()` dan `confirm()` → `confirm()` menolak.
- [ ] Token kartu versi lama (setelah terbit ulang) → ditolak.
- [ ] "Wajah tidak cocok" tersimpan `rejected` dengan alasan tsb.
- [ ] Check-in manual tanpa alasan → validasi gagal.
- [ ] Pegawai tidak bisa menghapus/mengubah riwayat check-in (403); owner bisa.
- [ ] Member tidak bisa membuka halaman scan.

## Commit

`feat: scan check-in dengan verifikasi wajah oleh pegawai`
