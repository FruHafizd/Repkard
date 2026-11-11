# Langkah 11 — Review Keamanan Fase 1

**Model:** **Opus** (model kuat) · **Branch:** `fase-1/11-review-keamanan` · **Wajib baca dulu:** `prompts/ATURAN.md`, `CLAUDE.md`, prd.md bagian 6 dan 8

## Tujuan

Memeriksa seluruh hasil Fase 1 (dikerjakan model yang lebih murah) sebelum dipakai gym sungguhan. Temukan celah, perbaiki yang jelas, laporkan sisanya.

## Yang dikerjakan

1. Pakai skill **`security-audit`** dalam **guidance mode** (bukan full audit, jangan buat folder laporan) untuk area berikut, urut prioritas:
   1. **Kartu & check-in** — `CardTokenService`, `CheckInService`, page `ScanCheckIn`: pemalsuan token, timing attack, token lama/dicabut, race condition kuota, state Livewire yang bisa dimanipulasi dari browser.
   2. **Hak akses** — setiap Resource, Page, Action, dan route custom (`member.photo`, `member.card.pdf`, kuitansi): apakah Policy benar-benar dicek di server? Uji IDOR (member A → data member B, pegawai A → pembayaran pegawai B).
   3. **Auth** — registrasi, verifikasi email, wajib ganti password, user nonaktif, rate limit login/registrasi/scan.
   4. **Upload & file** — foto (upload & data URL webcam), bukti transfer: validasi tipe, ukuran, path traversal, file tetap private.
   5. **Mass assignment** — `$fillable`/`$guarded` di semua model, khususnya `status`, `member_number`, `role`, `is_active`, `visits_remaining`.
   6. **Konfigurasi** — `CARD_SECRET`, `APP_DEBUG`, `.env.example` tanpa nilai rahasia.
2. Untuk setiap temuan yang terbukti: tulis **test yang gagal dulu**, lalu perbaiki, lalu test hijau.
3. Jangan mengubah desain/arsitektur besar. Temuan yang perlu keputusan pemilik proyek → masukkan ke laporan, jangan diperbaiki sendiri.
4. Jalankan seluruh test suite + `composer audit` + `npm audit --omit=dev`.

## Laporan (tambahkan di akhir laporan standar)

```
## Temuan keamanan
| # | Tingkat | Lokasi | Masalah | Status (diperbaiki / perlu keputusan) |
```

## Commit

`fix: perbaikan hasil review keamanan fase 1`
