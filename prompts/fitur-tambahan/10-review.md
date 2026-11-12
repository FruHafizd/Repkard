# Fitur Tambahan 10 — Review Keamanan & Hitungan Uang

**Model:** **Opus** (model kuat) · **Branch:** `fix/review-fitur-tambahan` · **Wajib baca dulu:** `prompts/ATURAN.md`, `CLAUDE.md`, prd.md bagian 13

## Tujuan

Memeriksa hasil langkah 01–09 (dikerjakan model yang lebih murah) sebelum dipakai gym sungguhan. Fokus: **uang** dan **hak akses**. Perbaiki yang jelas, laporkan sisanya.

## Yang diperiksa (urut prioritas)

1. **Integritas uang**
   - Harga selalu dari database (kasir, paket, paket PT, promo), tidak dari input browser / state Livewire.
   - Pembulatan diskon & prorata konsisten (selalu ke bawah, tidak pernah negatif).
   - Race condition: kuota promo (`max_uses`), stok kasir, sisa sesi PT, nomor transaksi `TRX-…` — dua request bersamaan tidak boleh melampaui batas. Pastikan `lockForUpdate` ada di dalam transaction yang benar.
   - Laporan keuangan = jumlah semua sumber (membership, upgrade, PT, pendatang, kasir) − refund − pengeluaran; semua yang dibatalkan tidak terhitung; batas bulan memakai `Asia/Makassar`. Bandingkan `FinanceReportService` dengan `AdminStatsOverview` "Pemasukan hari ini" — sumbernya harus sama.
   - Refund tidak bisa melebihi nilai; upgrade tidak bisa dipakai untuk "mencuci" kredit (mis. upgrade → refund berulang menghasilkan uang lebih).
2. **Role trainer (least privilege)** — login sebagai trainer dan coba buka setiap resource, page, widget, action, dan route custom. Trainer hanya boleh melihat klien & sesi miliknya, paket PT, komisinya, dan absensinya.
3. **IDOR pada route & resource baru** — struk kasir, bukti pengeluaran, foto absensi, kuitansi PT, sesi PT, penjualan, absensi, pengeluaran: user A → data user B.
4. **XSS** — isi pengumuman (panel member & landing), nama produk, catatan tindak lanjut, keterangan pengeluaran.
5. **Mass assignment** — `$fillable` model baru: `stock`, `sessions_remaining`, `commission_amount`, `discount_amount`, `credit_amount`, `is_late`, `recorded_by`, `cancelled_*`.
6. **Izin pegawai default** di `RoleSeeder` sesuai fitur-list.md bagian 10 (tidak lebih).

## Cara kerja

1. Pakai skill **`security-audit`** dalam **guidance mode** (bukan full audit, jangan buat folder laporan).
2. Setiap temuan yang terbukti: tulis **test yang gagal dulu**, perbaiki, test hijau.
3. Jangan ubah keputusan bisnis di prd.md bagian 13. Temuan yang butuh keputusan pemilik → masukkan ke laporan saja.
4. Jalankan `php artisan test`, `npx playwright test`, `composer audit`, `npm audit --omit=dev`.

## Laporan (tambahkan di akhir laporan standar)

```
## Temuan
| # | Tingkat | Lokasi | Masalah | Status (diperbaiki / perlu keputusan) |
```

## Commit / PR

`fix: perbaikan hasil review fitur tambahan`
