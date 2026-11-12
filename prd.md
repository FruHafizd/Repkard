# PRD — Repkard

> **Repkard** = *Rep* (repetisi latihan) + *Kard* (kartu).
> Sistem membership gym berbasis web untuk menggantikan buku catatan member.

| | |
|---|---|
| Versi dokumen | 0.1 (draft) |
| Tanggal | 11 November 2026 |
| Stack | Laravel 12 · Filament 5 · PHP 8.4 · MySQL 8 |
| Status | Instalasi selesai, fitur belum dikerjakan |

---

## 1. Latar belakang

Banyak gym kecil–menengah di Indonesia masih mencatat member di buku atau Excel: nama, tanggal bayar, tanggal habis, dan absen harian. Akibatnya:

- Sulit tahu siapa yang masa aktifnya sudah habis tapi masih datang.
- Kartu member (kalau ada) mudah dipinjamkan ke teman, karena tidak ada yang mencocokkan wajah.
- Pemilik tidak punya angka jelas: berapa member aktif, berapa pemasukan bulan ini, jam berapa gym paling ramai.
- Catatan bisa hilang, rusak, atau diubah tanpa jejak.

## 2. Tujuan

1. Memindahkan pencatatan member, pembayaran, dan absensi dari kertas ke sistem.
2. Setiap member punya **kartu member berfoto** yang dibuat otomatis dan bisa dicetak sendiri.
3. Setiap kedatangan dicatat dengan **scan kartu**, dan sistem membantu memastikan **yang datang adalah pemilik kartu**.
4. Tiga role dengan hak akses yang jelas: **Owner**, **Pegawai**, **Member**.
5. Calon member bisa **melihat paket di landing page dan membuat akun sendiri** sebelum datang ke gym.

### Ukuran keberhasilan

- Check-in satu member di meja depan selesai dalam **< 5 detik**.
- **0** check-in diterima untuk member yang masa aktifnya habis.
- Data member lama dari buku/Excel bisa dipindahkan dalam **1 hari kerja** lewat fitur impor.

### Di luar cakupan versi pertama

- Pembayaran online (payment gateway) — pembayaran dicatat manual dulu.
- Aplikasi mobile native — member memakai web yang ramah HP.
- Multi-cabang — satu instalasi untuk satu gym.
- Pintu/turnstile otomatis — check-in diverifikasi pegawai.

---

## 3. Pengguna & role

| Role | Siapa | Masuk lewat |
|---|---|---|
| **Owner / Manajer** | Pemilik atau manajer gym. Akses penuh, termasuk keuangan dan pengaturan. | `/admin` |
| **Pegawai** | Resepsionis / staf meja depan. Daftarkan member, terima pembayaran, scan kartu. | `/admin` |
| **Member** | Anggota gym. Mendaftar sendiri lewat website, melihat data sendiri, mencetak kartu, menampilkan QR. Sebelum diaktifkan pegawai, statusnya **Menunggu aktivasi** dan belum punya kartu. | `/member` |
| *(Pengunjung)* | Belum punya akun. Hanya bisa melihat landing page dan daftar paket. | `/` |

Prinsip hak akses:

- Setiap role hanya bisa **mengubah** data di wilayahnya.
- Di luar wilayahnya, role bisa mendapat akses **lihat saja (read-only)** atau **tidak ada akses sama sekali**. Detail per fitur ada di [fitur-list.md](fitur-list.md).
- Izin dicek di server (Policy + permission), bukan hanya disembunyikan dari menu.
- Owner bisa mengatur ulang izin pegawai lewat halaman Role (Filament Shield), tanpa ubah kode.

---

## 4. Alur utama

### 4.1 Pendaftaran mandiri lewat website (alur utama)

1. Calon member membuka **landing page** dan melihat daftar paket beserta harga.
2. Klik **Daftar** pada paket yang diminati → buat akun: nama, email, nomor HP, password. Email diverifikasi.
3. Lengkapi profil: jenis kelamin, tanggal lahir, alamat, kontak darurat, dan **unggah foto wajah**. Sistem memotong ke rasio pas foto dan mengecilkan ukurannya.
4. Status akun: **Menunggu aktivasi**. Panel member menampilkan paket yang dipilih, total yang harus dibayar, dan petunjuk untuk datang ke gym.
5. Member datang ke gym. Pegawai mencari akunnya, **mencocokkan wajah dengan foto yang diunggah** (atau mengambil ulang foto dari webcam bila tidak jelas), lalu mencatat **pembayaran** dan menekan **Aktifkan**.
6. Sistem otomatis:
   - membuat **nomor member** (contoh `RPK-2611-0001`),
   - membuat membership sesuai paket,
   - **menerbitkan kartu member** berisi foto + QR.
7. Member langsung bisa mencetak kartu atau menampilkan QR dari HP.

**Kenapa harus aktivasi tatap muka?** Foto diunggah sendiri oleh calon member, jadi bisa saja foto orang lain. Kartu baru terbit setelah pegawai melihat orangnya langsung. Ini sekaligus tempat pembayaran dicatat, karena pembayaran online belum ada di versi pertama.

### 4.1b Pendaftaran oleh pegawai (alternatif)

Untuk orang yang tidak bisa daftar sendiri (tidak punya HP/email) atau member lama dari buku catatan: pegawai membuka **Member → Tambah**, mengisi data, mengambil foto dari webcam, mencatat pembayaran, dan sistem membuat akun dengan password sementara (wajib diganti saat login pertama). Kartu langsung terbit.

### 4.2 Check-in harian

1. Member datang dan menunjukkan **kartu cetak** atau **QR digital** di HP-nya.
2. Pegawai memindai lewat halaman **Scan Check-in** (kamera laptop/tablet/HP, atau scanner USB yang mengetik seperti keyboard). Bisa juga memindai **foto kartu** dari galeri.
3. Layar menampilkan **foto member berukuran besar**, nama, status, dan sisa masa aktif.
4. Sistem memeriksa otomatis: kartu valid? belum dicabut? membership aktif? kuota kunjungan masih ada (paket per kunjungan)? tidak sedang dibekukan? tidak sedang check-in di tempat lain?
5. Hasil:
   - **Hijau — Diterima**: pegawai mencocokkan wajah di layar dengan orang di depannya, lalu konfirmasi.
   - **Merah — Ditolak**: alasan ditampilkan (habis masa aktif, kartu dicabut, sudah check-in, dll).
6. Kamera mengambil **snapshot** wajah yang datang dan menyimpannya bersama catatan check-in sebagai bukti.

### 4.3 Perpanjangan

Pegawai membuka data member → **Perpanjang** → pilih paket → catat pembayaran. Masa aktif baru dimulai dari tanggal habis yang lama (kalau masih aktif) atau dari hari ini (kalau sudah habis).

### 4.4 Kartu hilang

Pegawai/Owner → **Terbitkan ulang kartu**. Kartu lama otomatis **tidak berlaku**; QR lama ditolak saat discan.

---

## 5. Kartu member

### Isi kartu

- Logo & nama gym
- **Foto member**
- Nama lengkap dan nomor member
- **QR code** berisi token bertanda tangan (bukan sekadar ID)
- Tanggal terbit

Tanggal habis **tidak dicetak** di kartu karena berubah setiap perpanjangan; status selalu dicek dari sistem saat scan.

### Format

- Ukuran kartu ID standar **CR80 (85,6 × 54 mm)**, bisa cetak depan-belakang.
- Diunduh sebagai **PDF** (dompdf) — siap dicetak di printer biasa lalu dilaminasi, atau di printer kartu PVC.
- Desain/warna kartu mengikuti desain Figma (menyusul). Versi awal memakai template polos.

### Dibuat otomatis

Kartu diterbitkan otomatis saat member pertama kali aktif dan sudah punya foto. Kartu dibuat ulang otomatis saat foto atau nama member berubah.

---

## 6. Anti penyalahgunaan kartu (1 kartu = 1 orang)

Tidak ada satu teknik yang 100% mencegah kartu dipinjamkan, jadi Repkard memakai beberapa lapis:

| # | Lapisan | Mencegah |
|---|---|---|
| 1 | **QR bertanda tangan (HMAC)** berisi ID kartu + versi. QR palsu/diedit langsung ditolak. | Kartu palsu |
| 2 | **Versi kartu**: terbit ulang = versi naik, kartu lama mati. | Kartu hilang dipakai orang lain |
| 3 | **Foto besar di layar scan** — pegawai wajib mencocokkan wajah sebelum konfirmasi. | Kartu dipinjam |
| 4 | **Snapshot wajah saat check-in** disimpan. Owner bisa membandingkan foto terdaftar vs foto saat datang. | Pegawai yang lalai / kongkalikong |
| 5 | **Anti-passback**: kartu yang sudah check-in tidak bisa dipakai check-in lagi sampai check-out atau lewat jeda waktu (default 3 jam). | Kartu dioper ke teman yang menunggu di luar |
| 6 | **QR dinamis di HP member**: QR di panel member berganti tiap 30 detik dan hanya muncul setelah login. Screenshot cepat kedaluwarsa. | Foto QR dikirim lewat WA |
| 7 | **Satu sesi login aktif** per akun member. Login di HP lain mengeluarkan sesi lama. | Akun dipakai bersama |
| 8 | **Ganti foto harus disetujui** pegawai/owner. Member tidak bisa langsung mengganti foto sendiri. | Foto kartu diganti ke wajah orang lain |
| 9 | **Aktivasi tatap muka**: akun hasil daftar sendiri baru mendapat kartu setelah pegawai mencocokkan wajah dengan foto yang diunggah. | Daftar memakai foto orang lain |
| 10 | **Laporan kecurigaan**: tanda otomatis untuk pola aneh (check-in ditolak berulang, kartu discan di luar jam buka, dll). | Penyalahgunaan berulang |

**Fase berikutnya (opsional):** pencocokan wajah otomatis di browser (mis. face-api.js) yang membandingkan snapshot dengan foto terdaftar dan memberi skor kemiripan. Hasilnya hanya peringatan untuk pegawai, bukan penentu tunggal.

---

## 7. Kebutuhan fungsional

Ringkasan per modul. Daftar lengkap + hak akses per role ada di [fitur-list.md](fitur-list.md).

0. **Landing page** — profil gym, daftar paket & harga (diambil dari data paket), tombol daftar. Tampilan mengikuti desain Figma.
1. **Autentikasi** — registrasi mandiri member + verifikasi email, login per panel, lupa password, wajib ganti password pertama kali (akun buatan pegawai), satu sesi aktif untuk member.
2. **Role & izin** — Owner, Pegawai, Member; Owner bisa mengatur izin pegawai.
3. **Member** — CRUD, foto, pencarian cepat (nama/HP/nomor member), status (menunggu aktivasi, aktif, habis, beku, nonaktif), antrean aktivasi untuk pegawai.
4. **Paket membership** — bulanan, 3 bulan, 6 bulan, tahunan, harian/per kunjungan (pakai kartu juga, dengan kuota kunjungan); harga, durasi, dan kuota diatur Owner.
5. **Membership & perpanjangan** — riwayat paket per member, bekukan (cuti) dengan batas hari.
6. **Pembayaran** — catat manual (tunai, transfer, QRIS manual), bukti transfer opsional, cetak kuitansi.
7. **Kartu member** — terbit otomatis, cetak PDF, terbit ulang, cabut.
8. **Check-in** — scan QR (kamera, scanner USB, unggah foto), snapshot, check-out, anti-passback, input manual dengan alasan (kartu ketinggalan).
9. **Panel member** — profil, kartu + cetak, QR dinamis, riwayat kehadiran, riwayat pembayaran, masa aktif.
10. **Dashboard & laporan** — member aktif, yang akan habis 7 hari lagi, kehadiran harian, jam ramai, pemasukan.
11. **Impor data lama** — unggah Excel/CSV dari pencatatan manual.
12. **Log aktivitas** — siapa mengubah apa dan kapan.
13. **Pengaturan gym** — nama, logo, alamat, jam buka, jeda anti-passback, template kartu.

## 8. Kebutuhan non-fungsional

- **Keamanan**: password di-hash (bcrypt/argon), CSRF, rate limit login & endpoint scan, izin dicek di server, rahasia HMAC di `.env`.
- **Privasi**: foto & snapshot disimpan di disk private, diakses lewat route yang dicek izinnya. Snapshot check-in dihapus otomatis setelah periode tertentu (default 90 hari, bisa diatur).
- **Performa**: halaman scan merespons < 1 detik setelah QR terbaca.
- **Ramah HP**: panel member dan halaman scan nyaman dipakai di layar HP/tablet.
- **Bahasa**: Bahasa Indonesia; zona waktu WITA (`APP_TIMEZONE=Asia/Makassar`).
- **Notifikasi**: email dan Telegram (tidak ada WhatsApp).
- **Cadangan**: backup database harian (fase berikutnya).

## 9. Model data (rancangan awal)

| Tabel | Isi penting |
|---|---|
| `users` | nama, email/HP, password, role (via spatie), `must_change_password`, `email_verified_at`, `telegram_chat_id` |
| `members` | `user_id`, nomor member (diisi saat aktivasi), foto, HP, jenis kelamin, tgl lahir, alamat, kontak darurat, status, paket pilihan saat daftar, diaktifkan oleh + kapan |
| `membership_plans` | nama, durasi (hari), kuota kunjungan (kosong = tanpa batas), harga, aktif/nonaktif |
| `memberships` | `member_id`, `plan_id`, mulai, berakhir, sisa kunjungan, status, info pembekuan |
| `payments` | `membership_id`, jumlah, metode, bukti, dicatat oleh, tanggal |
| `member_cards` | `member_id`, nomor kartu, versi, diterbitkan, dicabut + alasan |
| `check_ins` | `member_id`, `card_id`, metode (kartu/QR dinamis/manual), hasil, alasan tolak, snapshot, discan oleh, masuk, keluar |
| `photo_change_requests` | `member_id`, foto baru, status, ditinjau oleh |
| `settings` | pengaturan gym (key–value) |
| `activity_log` | log perubahan (paket `spatie/laravel-activitylog`, dipasang saat fitur dikerjakan) |

## 10. Teknologi terpasang

| Kebutuhan | Paket | Versi |
|---|---|---|
| Framework | `laravel/framework` | 12.x |
| Dashboard | `filament/filament` | 5.x (panel `admin` & `member`) |
| Role & izin | `spatie/laravel-permission` | 8.x (migrasi sudah jalan) |
| UI izin di Filament | `bezhansalleh/filament-shield` | 4.x (terpasang, belum di-setup) |
| Generate QR | `chillerlan/php-qrcode` | 5.x |
| PDF kartu | `barryvdh/laravel-dompdf` | 3.x |
| Olah foto | `intervention/image-laravel` | 4.x |
| Scanner QR di browser | `html5-qrcode` (npm) | terbaru |

Akan ditambah saat fiturnya dikerjakan: `spatie/laravel-activitylog` (log), paket impor Excel, `laravel-notification-channels/telegram` (pengingat Telegram), dan library pencocokan wajah (opsional).

## 11. Tahapan

| Fase | Isi |
|---|---|
| **0 — Fondasi** ✅ | Instal Laravel, Filament, paket pendukung, dokumen |
| **1 — MVP** | Role & izin, landing page + daftar paket, registrasi mandiri + aktivasi tatap muka, member + foto, paket, pembayaran, kartu PDF, scan check-in, panel member dasar |
| **2 — Anti-curang** | QR dinamis, anti-passback, snapshot, persetujuan ganti foto, satu sesi login, laporan kecurigaan |
| **3 — Operasional** | Dashboard & laporan, impor Excel, pengingat masa habis (email + Telegram), hapus otomatis akun tak diaktifkan, log aktivitas, backup |
| **4 — Lanjutan** | Pencocokan wajah otomatis, pembayaran online (aktivasi tanpa ke kasir) |

## 12. Keputusan (11 November 2026)

| # | Topik | Keputusan |
|---|---|---|
| 1 | Zona waktu | **WITA** — `APP_TIMEZONE=Asia/Makassar` di `.env` |
| 2 | Pemasukan | Hanya **Owner/Manajer** yang melihat total pemasukan. Pegawai hanya melihat transaksi yang ia catat sendiri. Owner bisa membuka akses ini ke pegawai lewat pengaturan izin role (Filament Shield), tanpa ubah kode. |
| 3 | Paket per kunjungan | **Tetap pakai kartu.** Paket punya kuota kunjungan (mis. 1x, 10x); setiap check-in mengurangi kuota. Untuk tamu harian, kartu bisa dipakai dalam bentuk QR di HP tanpa perlu dicetak. |
| 4 | Nomor member | **`RPK-YYMM-NNNN`** — `YYMM` = tahun & bulan aktivasi, `NNNN` = nomor urut berjalan yang tidak di-reset. Contoh: `RPK-2611-0001`. |
| 5 | Printer kartu | Urusan gym. Sistem menyediakan PDF ukuran CR80 yang bisa dipakai di printer biasa (lalu laminasi) maupun printer kartu PVC. |
| 6 | Pengingat | **Email + Telegram**, tanpa WhatsApp. Member menghubungkan Telegram sendiri lewat tombol di panel member (bot Telegram milik gym). Owner membuat bot lewat @BotFather dan mengisi token-nya di pengaturan. |
| 7 | Verifikasi akun | **Email** |
| 8 | Akun tidak diaktifkan | **Dihapus otomatis setelah 30 hari** (beserta foto yang diunggah) |

## 13. Fitur tambahan (rencana, 13 November 2026)

Dipilih dari perbandingan dengan software gym lain (GymMaster, PushPress, Zen Planner, Glofox, Gymdesk). Prompt pengerjaannya ada di `prompts/fitur-tambahan/`. Keputusan default di bawah boleh diubah pemilik proyek **sebelum** prompt dijalankan.

| # | Fitur | Keputusan default |
|---|---|---|
| 1 | **Pengumuman** | Owner & pegawai membuat pengumuman (judul, isi teks biasa, tanggal tayang & berakhir, sematkan). Tampil di dashboard + halaman Pengumuman panel member; bisa ditandai "tampil di landing page" (mis. jam libur Lebaran). Kirim ke Telegram menyusul setelah integrasi Telegram (Fase 3) ada. |
| 2 | **Member mulai menghilang** | Member aktif (tidak beku) yang **tidak check-in ≥ 14 hari** masuk daftar tindak lanjut; ≥ 30 hari ditandai merah. Ambang diatur di `.env`. Pegawai mencatat tindak lanjut (cara, hasil, catatan, tanggal hubungi lagi). |
| 3 | **Promo & kode diskon** | Kode promo (persen atau nominal), periode berlaku, batas pemakaian total & per member, bisa dibatasi ke paket tertentu atau member baru saja. Dipakai saat aktivasi & perpanjangan; calon member boleh mengisi kode saat daftar (dicek ulang saat aktivasi). Pembayaran menyimpan harga asli, diskon, dan kode yang dipakai. |
| 4 | **Upgrade, pindah paket, refund** | **Upgrade** (ke paket lebih mahal) berlaku hari ini: sisa nilai membership lama dihitung proporsional (sisa hari, atau sisa kunjungan untuk paket kuota) menjadi kredit; member membayar selisihnya. **Pindah ke paket lebih murah** hanya untuk periode berikutnya (lewat Perpanjang). **Refund** hanya owner, wajib alasan; membership dibatalkan. Refund dicatat terpisah dari "batalkan pembayaran" (yang artinya salah catat). |
| 5 | **Kasir / POS** | Produk (harga, stok opsional untuk jasa seperti sewa handuk), halaman Kasir dengan keranjang, metode bayar, uang diterima & kembalian, struk. Stok tidak boleh minus. Pegawai menjual; owner mengatur produk, stok, dan membatalkan transaksi (stok kembali). |
| 6 | **Personal Trainer** | Role baru **`trainer`** (masuk panel admin, hanya melihat klien & sesinya sendiri). Paket PT (jumlah sesi, harga, masa berlaku) dijual ke member aktif dan dicatat sebagai pembayaran. Sesi dijadwalkan pegawai/trainer; selesai atau tidak datang (*no-show*) memotong 1 sesi, dibatalkan tidak. Komisi = nominal tetap per sesi per trainer. Member melihat sisa sesi & jadwal di panel member. |
| 7 | **Absensi pegawai** | Pegawai & trainer absen masuk/pulang di panel admin dengan **swafoto webcam wajib** (mencegah titip absen). Jam kerja per orang untuk menandai terlambat (toleransi 10 menit). Owner melihat rekap bulanan dan bisa mengoreksi dengan alasan. |
| 8 | **Pengeluaran & laporan laba** | Pengeluaran per kategori dengan bukti foto (private). Pegawai boleh mencatat (kas kecil) dan hanya melihat miliknya. Halaman **Laporan Keuangan** (owner): pemasukan per sumber (membership, PT, pendatang, kasir) − refund − pengeluaran = laba bersih per bulan. |
