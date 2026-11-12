# Fitur Tambahan 05 — Kasir / POS Sederhana

**Model:** Sonnet · **Branch:** `feat/kasir` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 5, fitur-list.md bagian 10, `app/Models/WalkIn.php` (contoh pola "hanya catatan sendiri"), `resources/views/receipts/payment.blade.php` (contoh PDF)

## Tujuan

Pegawai menjual produk (minuman, suplemen, sewa handuk) dari halaman Kasir di tablet/laptop. Stok berkurang otomatis. Owner mengatur produk & stok, dan bisa membatalkan transaksi.

## Yang dikerjakan

### A. Tabel
1. `Product` (`-mf`): `name`, `sku` (string nullable unique), `price` (unsignedBigInteger), `track_stock` (boolean default true — matikan untuk jasa seperti sewa handuk), `stock` (integer default 0), `is_active` (boolean default true), `sort_order` (unsignedInteger default 0), timestamps.
2. `StockMovement` (`-m`): `product_id` (FK cascade), `quantity` (integer, + masuk / − keluar), `type` string — enum `App\Enums\StockMovementType`: `restock` "Barang masuk", `sale` "Terjual", `void` "Pembatalan", `adjustment` "Koreksi", `note` nullable, `sale_id` (FK nullable), `user_id` (FK users), timestamps.
3. `Sale` (`-mf`): `number` (string unique, format `TRX-YYMMDD-NNNN`, urut per hari), `member_id` (FK nullable, nullOnDelete), `customer_name` (nullable), `total` (unsignedBigInteger), `method` (cast `PaymentMethod`), `cash_received` (unsignedBigInteger nullable), `recorded_by`, `sold_at`, `cancelled_at`, `cancelled_by`, `cancel_reason`, timestamps.
4. `SaleItem` (`-m`): `sale_id` (FK cascade), `product_id` (FK restrict), `product_name` (salinan nama saat dijual), `unit_price` (salinan harga), `quantity` (unsignedInteger ≥ 1), `subtotal`.

### B. `app/Services/SaleService.php`
1. `checkout(array $items, PaymentMethod $method, ?int $cashReceived, ?Member $member, ?string $customerName, User $by): Sale`
   - `$items` = `[product_id => quantity]` saja. **Harga selalu diambil dari database**, bukan dari browser.
   - Satu transaction: `lockForUpdate()` produk; produk harus aktif; bila `track_stock` maka stok ≥ jumlah (pesan: "Stok {nama} tinggal {n}"); hitung subtotal & total; untuk tunai `cash_received ≥ total`.
   - Kurangi stok + `StockMovement` (`sale`, jumlah negatif) untuk produk yang `track_stock`.
   - Keranjang kosong / jumlah < 1 → tolak.
2. `void(Sale $sale, User $by, string $reason)`: belum dibatalkan; kembalikan stok + movement `void`; isi kolom batal.
3. `restock(Product $p, int $qty, ?string $note, User $by)` (qty ≥ 1) dan `adjust(Product $p, int $newStock, string $reason, User $by)` (newStock ≥ 0, movement selisihnya).

### C. Halaman **Kasir** (Filament Page, panel admin)
- Kiri/atas: pencarian + grid tombol produk aktif (nama, harga, sisa stok; produk stok habis tidak bisa dipilih).
- Kanan/bawah: keranjang (tambah/kurang/hapus), total, pilih member (opsional, cari nama/nomor) atau nama pembeli, metode bayar, **uang diterima** & **kembalian** (hanya tunai), tombol **"Bayar"**.
- State Livewire hanya menyimpan `product_id => qty`. Total yang ditampilkan dihitung ulang dari database setiap render.
- Setelah bayar: notifikasi + tombol "Cetak struk", keranjang dikosongkan.
- Responsif: di HP keranjang di bawah grid; di tablet landscape dua kolom. Tombol produk ≥ 44px.
- Rate limit `checkout` 30/menit per user.

### D. Struk
Route `GET /kasir/struk/{sale}` (auth + Policy `view`) → dompdf lebar **80 mm** (226.77pt), tinggi menyesuaikan jumlah item. Isi: nama gym, nomor transaksi, tanggal, kasir, item (nama × qty, subtotal), total, metode, uang diterima & kembalian, cap "DIBATALKAN" bila batal. Ikuti cara warna dari `config/design.php` seperti di kuitansi.

### E. Resource
1. **`ProductResource`** (label "Produk"): owner CRUD (tanpa hapus jika pernah terjual → nonaktifkan); pegawai `ViewAny` + `View`. Aksi **"Tambah stok"** dan **"Koreksi stok"** (permission custom `ManageStock:Product`, hanya owner). Badge `warning` bila `track_stock` dan stok ≤ 5. Riwayat stok di halaman view.
2. **`SaleResource`** (label "Penjualan", tanpa create — penjualan hanya dari Kasir): pegawai melihat miliknya saja; owner semua lewat permission **`ViewAll:Sale`** (pola sama dengan `WalkInResource`). Aksi **"Batalkan"** hanya owner (`Cancel:Sale`), alasan wajib.
3. `RoleSeeder` pegawai: `ViewAny/View:Product`, `ViewAny/View:Sale`, permission page Kasir.
4. `AdminStatsOverview` "Pemasukan hari ini": tambah penjualan tidak dibatalkan hari ini (milik sendiri bila tanpa `ViewAll:Sale`).
5. `ProductSeeder` (local saja): Air mineral 600ml Rp 5.000, Minuman isotonik Rp 10.000, Protein shake Rp 25.000, Sewa handuk Rp 5.000 (`track_stock` false).

## Kriteria selesai

Test PHPUnit:
- [ ] Checkout 2 produk → total benar, stok berkurang, movement tercatat, nomor `TRX-YYMMDD-0001`; transaksi kedua di hari sama `-0002`, hari berikutnya kembali `-0001`.
- [ ] Harga produk diubah setelah transaksi → `sale_items` tetap harga lama.
- [ ] Stok kurang → ditolak, stok tidak berubah.
- [ ] Produk `track_stock` false bisa dijual tanpa stok.
- [ ] Produk nonaktif → ditolak.
- [ ] Tunai dengan uang diterima < total → ditolak.
- [ ] Void mengembalikan stok; void dua kali ditolak.
- [ ] Pegawai A tidak melihat penjualan pegawai B (list & URL struk → 403); owner melihat semua.
- [ ] Pegawai tidak bisa void / tambah stok (403).

Test Playwright (`e2e/kasir.spec.ts`, ikuti pola file `e2e/` yang ada): pegawai login → Kasir → tambah 2 produk → bayar tunai → kembalian tampil benar → struk bisa dibuka.

Tandai ✅ di fitur-list.md: 4 baris kasir.

## Commit / PR

`feat: kasir sederhana dengan stok dan struk`
