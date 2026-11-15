# Alur Demo Repkard — Testing dari Nol

Panduan untuk mencoba **semua fitur** Repkard dari database kosong, sekaligus naskah urutan untuk video penjelasan. Semua langkah dilakukan **di aplikasi** (browser). Terminal hanya dipakai sekali di bagian Persiapan.

Urutannya mengikuti cerita sehari-hari di gym: **Owner menyiapkan gym → calon member daftar → pegawai mengaktifkan → member datang & scan → kasir → personal trainer → perubahan paket → laporan keuangan.**

---

## 0. Persiapan (sekali, ±5 menit)

### 0.1 Mulai dari data kosong

Jalankan di terminal folder project:

```bash
php artisan migrate:fresh --seed
```

⚠️ Ini **menghapus semua data** di database lokal, lalu mengisi data awal:

| Data awal | Isi |
|---|---|
| Akun | `owner@repkard.test` dan `pegawai@repkard.test`, password `password` |
| Paket membership | Harian, Bulanan, 3 Bulan, Tahunan, 10x Kunjungan |
| Produk kasir | Air mineral, minuman isotonik, protein shake, sewa handuk |
| Kategori pengeluaran | Listrik & air, Gaji, Sewa tempat, dll. |

Belum ada member, trainer, promo, paket PT, maupun pengumuman. Semuanya akan dibuat di dalam demo.

### 0.2 Email verifikasi (pakai Mailpit dari Laragon)

Member yang daftar sendiri harus memverifikasi email. Supaya emailnya bisa ditunjukkan di video, ubah 2 baris di `.env`:

```
MAIL_MAILER=smtp
MAIL_PORT=1025
```

Lalu jalankan `php artisan config:clear`. Semua email yang dikirim aplikasi akan masuk ke **http://localhost:8025** (kotak masuk Mailpit; Mailpit harus menyala di Laragon).

### 0.3 Jalankan aplikasi

```bash
composer dev
```

Buka **http://localhost:8000**.

### 0.4 Siapkan 4 jendela browser (penting untuk multi-user)

Satu browser hanya bisa login **satu akun** sekaligus. Supaya bisa pindah-pindah peran tanpa logout, pakai jendela terpisah:

| Jendela | Peran | Alamat |
|---|---|---|
| Chrome biasa | **Owner** | http://localhost:8000/admin |
| Edge biasa | **Pegawai** | http://localhost:8000/admin |
| Chrome Incognito | **Member** | http://localhost:8000 |
| Edge InPrivate | **Trainer** | http://localhost:8000/admin |

Beri nama/warna profil berbeda supaya penonton video tidak bingung.

### 0.5 Siapkan

- 2 foto wajah berbeda (mis. foto Anda dan teman) untuk member.
- Webcam laptop (untuk foto dari kamera & scan QR).
- Opsional: HP untuk menampilkan QR, atau scanner barcode USB.

---

## 1. Owner menyiapkan gym — jendela **Owner**

**Pesan video:** "Owner mengatur semua aturan main: harga, staf, promo, dan siapa boleh apa."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 1.1 | Login `owner@repkard.test` / `password` | Dashboard: pemasukan hari ini, member aktif, menunggu aktivasi, akan habis, check-in hari ini (masih 0 semua) |
| 1.2 | Menu **Paket** → buka "Bulanan" | Harga, durasi, kuota kunjungan. Jelaskan paket "10x Kunjungan" (kuota berkurang tiap datang) |
| 1.3 | Menu **Pegawai** → Tambah → nama "Rina", role **Pegawai** → Simpan | Muncul notifikasi **Password sementara** — catat, hanya tampil sekali |
| 1.4 | Menu **Pegawai** → Tambah → nama "Andi", role **Trainer**, isi spesialisasi & **komisi per sesi** Rp 50.000 → Simpan | Catat password sementaranya |
| 1.5 | Menu **Paket PT** → Tambah: "PT 8 Sesi", 8 sesi, Rp 1.200.000, berlaku 60 hari | |
| 1.6 | Menu **Promo** → Tambah: kode `BARU20`, 20%, khusus member baru, berlaku bulan ini | Jelaskan batas pemakaian & bisa dibatasi ke paket tertentu |
| 1.7 | Menu **Pengumuman** → Tambah: "Jam buka libur nasional", centang **tampil di landing page** dan **sematkan** | |
| 1.8 | Menu **Peran** (grup Filament Shield) → buka role **pegawai** | Daftar izin pegawai. Jelaskan: owner bisa membuka/menutup akses pegawai di sini tanpa programmer |

---

## 2. Calon member daftar sendiri — jendela **Member**

**Pesan video:** "Calon member tidak perlu isi formulir kertas. Daftar dari HP, lalu tinggal datang ke gym."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 2.1 | Buka http://localhost:8000 | Landing: pengumuman dari langkah 1.7, daftar paket & harga, paket PT. Coba juga menu **Pengumuman** dan **Paket PT** di header |
| 2.2 | Klik **Daftar** pada paket **Bulanan** | Form daftar dengan paket sudah terpilih |
| 2.3 | Isi nama, email (mis. `sari@contoh.test`), nomor HP, password, kode promo `BARU20` → Daftar | Kode promo dicek langsung |
| 2.4 | Buka Mailpit http://localhost:8025 → klik tautan verifikasi | Email verifikasi masuk |
| 2.5 | Kembali ke aplikasi → halaman **Lengkapi Profil**: jenis kelamin, tanggal lahir, alamat, kontak darurat, **unggah foto wajah** (foto #1) | Jelaskan: foto ini nanti dicocokkan pegawai saat datang ke gym |
| 2.6 | Dashboard member | Status **Menunggu aktivasi**, paket, total bayar sudah dipotong promo (Rp 160.000), petunjuk datang ke gym |
| 2.7 | Buka **Kartu Saya** | Belum ada kartu — kartu baru terbit setelah diaktifkan |

---

## 3. Pegawai di meja depan — jendela **Pegawai**

**Pesan video:** "Pegawai mengaktifkan member setelah melihat orangnya langsung. Kartu langsung jadi."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 3.1 | Login sebagai **Rina** (email dari 1.3, password sementara) | Langsung diminta **ganti password** dulu sebelum bisa memakai aplikasi |
| 3.2 | Perhatikan menu kiri | Lebih sedikit dari owner: tidak ada Refund, Laporan Keuangan, Peran, dll. |
| 3.3 | Menu **Member** → filter **Menunggu aktivasi** → baris "Sari" → **Aktifkan** | Foto besar, centang **"Saya sudah mencocokkan wajah dengan foto"**, kode promo sudah terisi, metode bayar (mis. QRIS), jumlah Rp 160.000 |
| 3.4 | Simpan | Member aktif, nomor member `RPK-YYMM-0001`, **kartu otomatis terbit** |
| 3.5 | Aksi **Cetak kartu** | PDF ukuran kartu ID (depan: foto & nama; belakang: QR) |
| 3.6 | Menu **Pembayaran** → buka transaksi Sari → kuitansi PDF | Harga asli, diskon, kode promo, total |
| 3.7 | Menu **Member** → **Tambah** (member yang tidak punya HP/email): nama "Budi", **ambil foto dari kamera** (webcam), paket Tahunan → Simpan | Password sementara tampil. Lalu **Aktifkan** Budi seperti 3.3 |
| 3.8 | Menu **Pendatang** → catat tamu harian "Tono", Rp 25.000 tunai | Untuk tamu sekali datang tanpa akun |

Kembali ke jendela **Member** (Sari) → refresh → dashboard sekarang **Aktif**, **Kartu Saya** menampilkan kartu & QR.

---

## 4. Member datang & scan kartu — jendela **Pegawai** + **Member**

**Pesan video:** "Satu kartu hanya untuk satu orang. Sistem menolak kartu palsu atau kartu lama, dan pegawai tetap mencocokkan wajah."

Cara memindai (pilih salah satu di halaman scan):
- **Kamera**: tunjukkan QR dari **Kartu Saya** (layar HP/laptop lain) atau kartu cetak ke webcam.
- **Foto kartu**: screenshot QR dari Kartu Saya / PDF kartu, lalu klik tautan **"Scan an Image File"** di bawah area kamera (label bawaan library scanner, masih bahasa Inggris) dan pilih gambarnya.
- **Scanner USB**: arahkan ke kolom input, scanner mengetik kodenya sendiri.

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 4.1 | Pegawai: menu **Scan Check-in** → pindai QR Sari | Foto besar, nama, nomor, paket, berlaku sampai, sisa hari |
| 4.2 | Tekan tombol **wajah cocok** | Layar hijau **DITERIMA** |
| 4.3 | Pindai lagi, tekan **wajah tidak cocok** | Layar merah **DITOLAK** — "Wajah tidak cocok" (simulasi kartu dipinjam teman) |
| 4.4 | Member → **Terbitkan ulang kartu** untuk Sari (alasan "kartu hilang"), lalu pindai **QR lama** (screenshot sebelumnya) | **DITOLAK** "Kartu tidak valid" — kartu hilang tidak bisa dipakai orang lain |
| 4.5 | Aksi **Check-in manual** di halaman scan → pilih Budi, alasan "kartu ketinggalan" | Tetap harus cocokkan foto |
| 4.6 | Menu **Riwayat Check-in** | Semua percobaan tercatat, termasuk yang ditolak & siapa yang memindai |
| 4.7 | Jendela **Member** → **Riwayat Kehadiran** | Member melihat kehadirannya sendiri |

---

## 5. Kasir — jendela **Pegawai**, lalu **Owner**

**Pesan video:** "Jual minuman & sewa handuk tercatat, stok berkurang otomatis."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 5.1 | Pegawai: menu **Kasir** → klik 2 air mineral + 1 sewa handuk | Keranjang & total |
| 5.2 | Tunai, uang diterima Rp 20.000 → **Bayar** | Kembalian, tombol **Cetak struk** (struk 80 mm) |
| 5.3 | Owner: menu **Produk** → Air mineral | Stok berkurang 2; aksi **Tambah stok** |
| 5.4 | Owner: menu **Penjualan** → **Batalkan** transaksi tadi | Stok kembali. Jelaskan: pegawai tidak bisa membatalkan |

---

## 6. Personal Trainer — jendela **Pegawai**, **Trainer**, **Member**, **Owner**

**Pesan video:** "Paket PT dijual, sesi dijadwalkan, sesi berkurang otomatis, komisi trainer terhitung sendiri."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 6.1 | Pegawai: **Member** → Sari → **Jual paket PT**: PT 8 Sesi, trainer Andi, bayar | Tercatat di Pembayaran sebagai "Paket PT" |
| 6.2 | Pegawai: menu **Jadwal PT** → Tambah: klien Sari, hari ini jam berikutnya | |
| 6.3 | Trainer: login **Andi** (password sementara → ganti password) | Menu sangat terbatas: **Klien PT**, **Sesi Saya**, Paket PT, Komisi Trainer — tidak bisa melihat member lain, pembayaran, atau kasir |
| 6.4 | Trainer: **Sesi Saya** → tandai **Selesai** | |
| 6.5 | Member: menu **Personal Trainer** | Sisa sesi 7 dari 8, riwayat sesi |
| 6.6 | Owner: menu **Komisi Trainer** | Andi: 1 sesi × Rp 50.000 |

Tambahan: jadwalkan sesi lain lalu tandai **Tidak datang** (sesi tetap terpotong + komisi) dan **Batalkan** (tidak terpotong).

---

## 7. Perubahan membership — jendela **Pegawai** + **Owner**

**Pesan video:** "Member bisa upgrade di tengah jalan tanpa rugi, cuti, atau minta refund."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 7.1 | Pegawai: **Member** → Sari → **Upgrade paket** → 3 Bulan | Rincian: sisa nilai paket lama jadi kredit, **harus dibayar** = selisih |
| 7.2 | Pegawai: Budi → **Bekukan** 7 hari, lalu scan kartu Budi | **DITOLAK** "Membership sedang dibekukan"; tanggal berakhir mundur 7 hari |
| 7.3 | Pegawai: **Perpanjang / pindah paket** | Paket baru mulai setelah paket sekarang habis |
| 7.4 | Pegawai: perhatikan tidak ada tombol **Refund** | Hanya owner |
| 7.5 | Owner: Sari → **Refund** → jumlah, alasan, centang "uang sudah dikembalikan" | Membership batal; scan kartu Sari → **DITOLAK** "Masa aktif sudah habis". Menu **Refund** mencatatnya |

---

## 8. Keuangan — jendela **Pegawai** + **Owner**

**Pesan video:** "Owner tahu untung-rugi bulan ini tanpa buka buku."

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 8.1 | Pegawai: menu **Pembayaran** | Hanya transaksi yang **ia catat sendiri** |
| 8.2 | Owner: menu **Pembayaran** | Semua transaksi, dengan kolom jenis (Pembelian / Upgrade / Paket PT) |
| 8.3 | Pegawai: menu **Pengeluaran** → catat "Beli sabun" Rp 35.000, unggah foto nota | Kas kecil oleh pegawai |
| 8.4 | Owner: **Pengeluaran** → catat listrik Rp 750.000 | Owner bisa **Batalkan** catatan yang salah |
| 8.5 | Owner: menu **Laporan Keuangan** | Pemasukan per sumber (membership, PT, pendatang, kasir), dikurangi refund & pengeluaran = **laba bersih**, grafik harian, perbandingan bulan lalu |
| 8.6 | Owner: **Dashboard** | Pemasukan hari ini (member + pendatang + kasir − refund) |

---

## 9. Pengumuman & member menghilang

| # | Langkah | Yang ditunjukkan |
|---|---|---|
| 9.1 | Member: dashboard & menu **Pengumuman** | Pengumuman dari owner |
| 9.2 | Pegawai: menu **Member Menghilang** | Dari data nol daftarnya **kosong** — fitur ini butuh member yang ≥ 14 hari tidak datang (lihat bagian 10) |

---

## 10. (Opsional) Data contoh berbulan-bulan

Beberapa fitur hanya terasa kalau ada riwayat panjang: **Member Menghilang**, **Akan habis 7 hari**, grafik **Laporan Keuangan**, komisi beberapa trainer. Untuk adegan video itu, isi data contoh:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Login: `owner@repkard.test`, `pegawai@repkard.test`, `trainer1@repkard.test`, `budi@repkard.test` — password `password`.

Lalu tunjukkan:
- **Member Menghilang**: badge kuning (≥ 14 hari) & merah (≥ 30 hari) → aksi **Catat tindak lanjut** (telepon, hasil, tanggal hubungi lagi).
- **Dashboard**: widget "Akan habis dalam 7 hari".
- **Laporan Keuangan**: bulan ini vs bulan lalu.

> Catatan: `DemoSeeder.php` saat ini baru ada di komputer Anda (belum di-commit ke repo).

---

## 11. Ringkasan hak akses (untuk penutup video)

| Fitur | Owner | Pegawai | Trainer | Member |
|---|:-:|:-:|:-:|:-:|
| Atur paket, promo, paket PT, harga produk | ✅ | lihat saja | — | — |
| Kelola pegawai & izin (Peran) | ✅ | lihat saja | — | — |
| Daftar & aktivasi member, scan check-in | ✅ | ✅ | — | — |
| Kasir | ✅ | ✅ | — | — |
| Batalkan penjualan / pembayaran, refund | ✅ | — | — | — |
| Jual paket PT & jadwal sesi | ✅ | ✅ | sesi miliknya | lihat miliknya |
| Pembayaran | semua | catatan sendiri | — | miliknya |
| Pengeluaran | semua | catatan sendiri | — | — |
| Laporan Keuangan | ✅ | — | — | — |
| Komisi trainer | semua | — | miliknya | — |
| Kartu, QR, riwayat kehadiran | — | — | — | miliknya |

Untuk membuktikan batas akses di video: di jendela **Member**, ketik http://localhost:8000/admin → ditolak. Di jendela **Trainer**, menu Member/Pembayaran tidak ada.

---

## Belum ada di aplikasi (jangan dijanjikan di video)

Absensi pegawai, QR dinamis di HP, anti-passback & check-out, snapshot wajah saat check-in, pengingat email/Telegram, impor data dari Excel, ekspor laporan, pembayaran online.
