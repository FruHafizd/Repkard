# Fitur Tambahan 09 — Pengeluaran & Laporan Keuangan

**Model:** Sonnet · **Branch:** `feat/pengeluaran-laporan` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 12 (keputusan 2) dan 13 baris 8, fitur-list.md bagian 8 dan 10

## Tujuan

Owner mencatat pengeluaran gym (pegawai boleh mencatat kas kecil), lalu melihat **laba bersih per bulan**: semua pemasukan − refund − pengeluaran.

## Sumber pemasukan (semua yang **tidak dibatalkan**)

| Sumber | Tabel | Tanggal yang dipakai |
|---|---|---|
| Membership | `payments` dengan `type` `purchase` atau `upgrade` | `paid_at` |
| Personal Trainer | `payments` dengan `type` `pt` | `paid_at` |
| Pendatang | `walk_ins` | `visited_at` |
| Kasir | `sales` | `sold_at` |
| **Dikurangi** refund | `refunds` | `refunded_at` |

Semua rentang bulan memakai zona waktu aplikasi (`Asia/Makassar`): transaksi 30 Nov 23:30 WITA masuk November.

## Yang dikerjakan

### A. Data
1. `ExpenseCategory` (`-mf`): `name` (unique), `is_active` (default true). `ExpenseCategorySeeder` dijalankan **di semua environment** (bukan hanya local; panggil dari `DatabaseSeeder` di luar blok local), `firstOrCreate`: Listrik & air, Gaji & honor, Sewa tempat, Perawatan & servis alat, Stok barang kasir, Kebersihan, Promosi, Lainnya.
2. `Expense` (`-mf`): `expense_category_id` (FK restrict), `amount` (unsignedBigInteger ≥ 1), `spent_on` (date, tidak boleh di masa depan), `description` (string 255), `proof_path` (nullable, disk `local`, folder `expense-proofs/`, gambar atau PDF maks 5 MB), `recorded_by`, `cancelled_at`, `cancelled_by`, `cancel_reason`, timestamps.

### B. Resource
1. **`ExpenseCategoryResource`** (label "Kategori Pengeluaran"): hanya owner. Tanpa hapus bila sudah dipakai → nonaktifkan.
2. **`ExpenseResource`** (label "Pengeluaran"):
   - pegawai: `ViewAny`, `View`, `Create` — hanya melihat miliknya (`ViewAll:Expense` tidak diberikan), tidak bisa mengubah;
   - owner: semua + aksi **"Batalkan"** (alasan wajib; tidak menghapus baris);
   - tabel: tanggal, kategori, keterangan, jumlah, dicatat oleh, ada bukti, status; filter bulan & kategori; total di bawah tabel (summarizer `Sum`).
3. Route bukti `GET /media/bukti-pengeluaran/{expense}` (auth + Policy `view`), pola sama dengan `MemberPhotoController`.

### C. `app/Services/FinanceReportService.php`
`monthly(int $year, int $month): array` mengembalikan:
- `income`: per sumber (membership, pt, walk_in, kasir) + `refunds` + `total_income` (= jumlah sumber − refund),
- `expenses`: per kategori + `total_expenses`,
- `net` = `total_income − total_expenses`,
- `daily`: untuk tiap tanggal di bulan itu → pemasukan bersih & pengeluaran,
- `previous`: `total_income`, `total_expenses`, `net` bulan sebelumnya.
Semua dihitung dengan `SUM` di database per sumber, bukan memuat baris ke PHP.

### D. Page **"Laporan Keuangan"** (panel admin)
- Permission page dari `shield:generate`; **tidak diberikan ke pegawai** (owner bisa membukanya untuk pegawai lewat halaman Role).
- Pilih bulan (default bulan ini).
- 3 stat: Pemasukan, Pengeluaran, **Laba bersih** — masing-masing dengan perbandingan % terhadap bulan lalu (sembunyikan % bila bulan lalu 0).
- Tabel pemasukan per sumber (termasuk baris "Refund" bernilai negatif), tabel pengeluaran per kategori, tabel harian.
- Grafik batang harian pemasukan vs pengeluaran (`ChartWidget` Filament), warna dari token (`config/design.php`).
- Responsif di 390 px (tabel bisa digulir horizontal di dalam kartunya sendiri, halaman tidak).

## Kriteria selesai

Test PHPUnit dengan fixture tetap November 2026:
- membership 200.000 + upgrade 416.667 + PT 1.500.000 + pendatang 2 × 25.000 + kasir 40.000 + **1 pembayaran dibatalkan 300.000** + **1 penjualan dibatalkan 15.000** + refund 100.000;
- pengeluaran listrik 750.000 + gaji 2.000.000 + **1 pengeluaran dibatalkan 500.000**;
- transaksi 1 Des 00:10 WITA dan 31 Okt 23:50 WITA (tidak boleh masuk November).

- [ ] `total_income` = 2.106.667 (200.000 + 416.667 + 1.500.000 + 50.000 + 40.000 − 100.000).
- [ ] `total_expenses` = 2.750.000; `net` = −643.333.
- [ ] Transaksi yang dibatalkan dan transaksi di luar bulan tidak terhitung.
- [ ] `daily` berjumlah sama dengan total bulanan.
- [ ] Pegawai membuka Laporan Keuangan → 403; owner → 200.
- [ ] Pegawai hanya melihat pengeluaran miliknya; tidak bisa mengubah / membatalkan (403).
- [ ] Bukti pengeluaran pegawai A tidak bisa dibuka pegawai B (403); tidak bisa diakses lewat `/storage`.
- [ ] `spent_on` di masa depan → validasi gagal.

Tandai ✅ di fitur-list.md: 4 baris pengeluaran & laporan di bagian 10, dan baris "Laporan & total pemasukan (harian / bulanan)" di bagian 8.

## Commit / PR

`feat: pengeluaran dan laporan keuangan bulanan`
