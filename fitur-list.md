# Daftar Fitur — Repkard

**Keterangan akses**

| Simbol | Arti |
|---|---|
| ✅ | Akses penuh (lihat + ubah) |
| 👁️ | Lihat saja (read-only) |
| 🙋 | Hanya data miliknya sendiri |
| ❌ | Tidak ada akses |

**Keterangan status:** ⬜ belum dikerjakan · 🟨 sedang dikerjakan · ✅ selesai

Fase: **1** = MVP · **2** = anti-curang · **3** = operasional · **4** = lanjutan (lihat prd.md bagian 11)

---

## 0. Landing Page & Pendaftaran

Kolom **Pengunjung** = orang yang belum punya akun.

| Fitur | Owner | Pegawai | Member | Pengunjung | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Landing page (profil gym) | 👁️ | 👁️ | 👁️ | 👁️ | 1 | ✅ |
| Daftar paket & harga di landing page | 👁️ | 👁️ | 👁️ | 👁️ | 1 | ✅ |
| Buat akun sendiri (pilih paket, isi data, unggah foto) | — | — | — | ✅ | 1 | ✅ |
| Verifikasi email setelah daftar | — | — | ✅ | — | 1 | ✅ |
| Lihat status "Menunggu aktivasi" + total bayar + petunjuk ke gym | — | — | 🙋 | — | 1 | ✅ |
| Ganti paket pilihan sebelum diaktifkan | — | — | 🙋 | — | 1 | ✅ |
| Antrean akun menunggu aktivasi | ✅ | ✅ | ❌ | ❌ | 1 | ✅ |
| Aktivasi tatap muka (cocokkan wajah, catat bayar, terbitkan kartu) | ✅ | ✅ | ❌ | ❌ | 1 | ✅ |
| Tolak / hapus pendaftaran palsu | ✅ | ✅ | ❌ | ❌ | 1 | ✅ |
| Hapus otomatis akun yang tidak diaktifkan (mis. 30 hari) | ✅ | ❌ | ❌ | ❌ | 3 | ⬜ |

## 1. Login & Akun

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Login panel admin (`/admin`) | ✅ | ✅ | ❌ | 1 | ✅ |
| Login panel member (`/member`) | ❌ | ❌ | ✅ | 1 | ✅ |
| Lupa password | ✅ | ✅ | ✅ | 1 | ⬜ |
| Wajib ganti password saat login pertama | ✅ | ✅ | ✅ | 1 | ✅ |
| Ubah profil & password sendiri | ✅ | ✅ | ✅ | 1 | ⬜ |
| Hanya 1 sesi login aktif per member | — | — | ✅ | 2 | ⬜ |

## 2. Pegawai & Hak Akses

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Tambah / ubah / nonaktifkan akun pegawai | ✅ | ❌ | ❌ | 1 | ✅ |
| Atur izin per role (Filament Shield) | ✅ | ❌ | ❌ | 1 | ✅ |
| Lihat daftar pegawai | ✅ | 👁️ | ❌ | 1 | ✅ |

## 3. Data Member

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Daftarkan member langsung di meja depan (tanpa HP / data lama) | ✅ | ✅ | ❌ | 1 | ✅ |
| Ambil foto dari webcam / unggah foto | ✅ | ✅ | ❌ | 1 | ✅ |
| Lihat & cari member (nama / HP / nomor) | ✅ | ✅ | 🙋 | 1 | ✅ |
| Ubah data member | ✅ | ✅ | 🙋 (data kontak saja) | 1 | ✅ |
| Hapus member | ✅ | ❌ | ❌ | 1 | ✅ |
| Nonaktifkan member | ✅ | ✅ | ❌ | 1 | ✅ |
| Ajukan ganti foto | — | — | 🙋 | 2 | ⬜ |
| Setujui / tolak ganti foto | ✅ | ✅ | ❌ | 2 | ⬜ |
| Impor member dari Excel / CSV (data buku lama) | ✅ | ❌ | ❌ | 3 | ⬜ |

## 4. Paket Membership

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Buat / ubah / nonaktifkan paket & harga | ✅ | 👁️ | ❌ | 1 | ✅ |
| Paket per kunjungan dengan kuota (tetap pakai kartu) | ✅ | 👁️ | 👁️ | 1 | ⬜ |
| Lihat daftar paket | ✅ | 👁️ | 👁️ | 1 | ⬜ |

## 5. Membership & Pembayaran

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Aktifkan / perpanjang membership | ✅ | ✅ | ❌ | 1 | ✅ |
| Catat pembayaran (tunai / transfer / QRIS) | ✅ | ✅ | ❌ | 1 | ✅ |
| Cetak kuitansi | ✅ | ✅ | 🙋 | 1 | ✅ |
| Batalkan / koreksi pembayaran | ✅ | ❌ | ❌ | 1 | ✅ |
| Bekukan (cuti) membership | ✅ | ✅ | ❌ | 1 | ✅ |
| Lihat riwayat membership & pembayaran | ✅ | 👁️ | 🙋 | 1 | ⬜ |
| Lihat masa aktif & sisa hari | ✅ | ✅ | 🙋 | 1 | ✅ |

## 6. Kartu Member

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Kartu dibuat otomatis setelah aktivasi (foto + QR) | ✅ | ✅ | 🙋 | 1 | ✅ |
| Lihat kartu | ✅ | ✅ | 🙋 | 1 | ✅ |
| Cetak / unduh kartu PDF (ukuran ID card) | ✅ | ✅ | 🙋 | 1 | ✅ |
| Terbitkan ulang kartu (kartu lama mati) | ✅ | ✅ | ❌ | 1 | ✅ |
| Cabut kartu | ✅ | ❌ | ❌ | 1 | ✅ |
| QR dinamis di HP (berganti tiap 30 detik) | — | — | 🙋 | 2 | ⬜ |
| Atur template kartu | ✅ | ❌ | ❌ | 3 | ⬜ |

## 7. Check-in (Scan Kartu)

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Halaman scan (kamera / scanner USB) | ✅ | ✅ | ❌ | 1 | ✅ |
| Scan dari foto kartu (unggah gambar) | ✅ | ✅ | ❌ | 1 | ✅ |
| Tampil foto besar + status untuk dicocokkan | ✅ | ✅ | ❌ | 1 | ✅ |
| Tolak otomatis jika habis / kuota habis / beku / kartu dicabut | ✅ | ✅ | ❌ | 1 | ✅ |
| Check-in manual (kartu ketinggalan, wajib alasan) | ✅ | ✅ | ❌ | 1 | ✅ |
| Check-out | ✅ | ✅ | ❌ | 2 | ⬜ |
| Anti-passback (tidak bisa check-in 2x) | ✅ | ✅ | ❌ | 2 | ⬜ |
| Snapshot wajah saat check-in | ✅ | ✅ | ❌ | 2 | ⬜ |
| Lihat snapshot vs foto terdaftar | ✅ | 👁️ | ❌ | 2 | ⬜ |
| Riwayat kehadiran | ✅ | 👁️ | 🙋 | 1 | ✅ |
| Hapus / ubah catatan check-in | ✅ | ❌ | ❌ | 1 | ✅ |
| Pencocokan wajah otomatis (peringatan) | ✅ | ✅ | ❌ | 4 | ⬜ |

## 8. Dashboard & Laporan

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Jumlah member aktif / habis / beku | ✅ | 👁️ | ❌ | 1 | ⬜ |
| Member yang habis dalam 7 hari | ✅ | ✅ | ❌ | 1 | ✅ |
| Kehadiran hari ini | ✅ | ✅ | ❌ | 1 | ✅ |
| Laporan & total pemasukan (harian / bulanan) | ✅ | ❌ * | ❌ | 3 | ⬜ |
| Transaksi yang dicatat sendiri hari ini | ✅ | 🙋 | ❌ | 1 | ⬜ |
| Catat pendatang (tamu harian tanpa akun) + pembayarannya | ✅ | ✅ | ❌ | 1 | ✅ |
| Pemasukan hari ini (member + pendatang) di dashboard | ✅ | 🙋 | ❌ | 1 | ✅ |
| Batalkan catatan pendatang | ✅ | ❌ | ❌ | 1 | ✅ |
| Grafik jam ramai | ✅ | 👁️ | ❌ | 3 | ⬜ |
| Laporan kecurigaan kartu | ✅ | ❌ | ❌ | 2 | ⬜ |
| Ekspor laporan ke Excel / PDF | ✅ | ❌ | ❌ | 3 | ⬜ |
| Ringkasan pribadi (kehadiran bulan ini, sisa hari) | — | — | 🙋 | 1 | ✅ |

\* Owner bisa membuka akses ini untuk pegawai lewat pengaturan izin role.

## 9. Pengaturan & Lainnya

| Fitur | Owner | Pegawai | Member | Fase | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Profil gym (nama, logo, alamat, jam buka) | ✅ | 👁️ | 👁️ | 1 | ⬜ |
| Aturan check-in (jeda anti-passback, dll) | ✅ | 👁️ | ❌ | 2 | ⬜ |
| Log aktivitas (siapa ubah apa) | ✅ | ❌ | ❌ | 3 | ⬜ |
| Pengingat masa habis via email | ✅ | ❌ | 🙋 (menerima) | 3 | ⬜ |
| Pengingat masa habis via Telegram | ✅ | ❌ | 🙋 (menerima) | 3 | ⬜ |
| Hubungkan akun Telegram | — | — | 🙋 | 3 | ⬜ |
| Pengaturan bot Telegram (token) | ✅ | ❌ | ❌ | 3 | ⬜ |
| Backup database otomatis | ✅ | ❌ | ❌ | 3 | ⬜ |
| Pembayaran online (aktivasi tanpa ke kasir) | ✅ | ❌ | 🙋 | 4 | ⬜ |

## 10. Fitur Tambahan

Rencana & keputusan: prd.md bagian 13. Prompt: `prompts/fitur-tambahan/`. Kolom **Trainer** = role baru di fitur Personal Trainer.

| Fitur | Owner | Pegawai | Trainer | Member | Status |
|---|:-:|:-:|:-:|:-:|:-:|
| Buat / ubah pengumuman | ✅ | ✅ | ❌ | ❌ | ✅ |
| Lihat pengumuman (dashboard + halaman) | ✅ | ✅ | ✅ | 👁️ | ✅ |
| Pengumuman di landing page | ✅ | ✅ | ❌ | 👁️ | ✅ |
| Daftar member mulai menghilang (≥ 14 / 30 hari) | ✅ | ✅ | ❌ | ❌ | ✅ |
| Catat tindak lanjut member menghilang | ✅ | ✅ | ❌ | ❌ | ✅ |
| Buat / ubah kode promo | ✅ | 👁️ | ❌ | ❌ | ✅ |
| Pakai kode promo saat aktivasi / perpanjang | ✅ | ✅ | ❌ | ❌ | ✅ |
| Isi kode promo saat daftar | — | — | — | 🙋 | ✅ |
| Upgrade paket (prorata) | ✅ | ✅ | ❌ | ❌ | ⬜ |
| Pindah paket untuk periode berikutnya | ✅ | ✅ | ❌ | ❌ | ⬜ |
| Refund membership | ✅ | ❌ | ❌ | ❌ | ⬜ |
| Kelola produk & harga | ✅ | 👁️ | ❌ | ❌ | ⬜ |
| Tambah / koreksi stok | ✅ | ❌ | ❌ | ❌ | ⬜ |
| Kasir: jual produk + struk | ✅ | ✅ | ❌ | ❌ | ⬜ |
| Batalkan transaksi kasir | ✅ | ❌ | ❌ | ❌ | ⬜ |
| Kelola trainer & komisi per sesi | ✅ | 👁️ | ❌ | ❌ | ⬜ |
| Kelola paket PT | ✅ | 👁️ | 👁️ | ❌ | ⬜ |
| Jual paket PT ke member | ✅ | ✅ | ❌ | ❌ | ⬜ |
| Jadwalkan sesi PT | ✅ | ✅ | 🙋 | ❌ | ⬜ |
| Tandai sesi selesai / tidak datang / batal | ✅ | ✅ | 🙋 | ❌ | ⬜ |
| Lihat sisa sesi & jadwal PT | ✅ | ✅ | 🙋 | 🙋 | ⬜ |
| Laporan komisi trainer | ✅ | ❌ | 🙋 | ❌ | ⬜ |
| Absen masuk / pulang dengan swafoto | — | ✅ | ✅ | ❌ | ⬜ |
| Rekap & koreksi absensi | ✅ | 🙋 | 🙋 | ❌ | ⬜ |
| Catat pengeluaran | ✅ | ✅ | ❌ | ❌ | ⬜ |
| Lihat pengeluaran | ✅ | 🙋 | ❌ | ❌ | ⬜ |
| Kategori pengeluaran | ✅ | ❌ | ❌ | ❌ | ⬜ |
| Laporan keuangan & laba bulanan | ✅ | ❌ * | ❌ | ❌ | ⬜ |

\* Owner bisa membuka akses ini untuk pegawai lewat pengaturan izin role.

---

## Fondasi (Fase 0) — sudah selesai

- ✅ Laravel 12 terpasang
- ✅ Filament 5 dengan 2 panel: `/admin` dan `/member`
- ✅ spatie/laravel-permission (tabel role & izin sudah dibuat)
- ✅ Filament Shield terpasang (belum di-setup)
- ✅ Paket QR, PDF, olah foto, scanner QR browser
- ✅ Bahasa Indonesia & zona waktu WITA (`Asia/Makassar`)
- ✅ Skill `security-audit` dan pedoman Karpathy di CLAUDE.md
