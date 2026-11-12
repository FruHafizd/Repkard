# Token Desain Repkard

Arah desain: **Lintasan** — sporty dan bersih: biru kobalt, jingga untuk aksi, judul tebal miring.

## Mengganti warna, font, atau radius

**Ubah nilainya hanya di [`config/design.php`](../config/design.php)**, lalu jalankan `php artisan config:clear` (bila config di-cache). Tidak perlu `npm run build` untuk perubahan nilai.

Kalau desain Figma sudah ada, nilai dari Figma menggantikan nilai di file itu.

Alur token:

```
config/design.php                               ← NILAI (satu-satunya tempat)
 ├─ PanelProvider admin & member                → ->colors(), ->font()  (komponen Filament)
 ├─ partials/design-tokens.blade.php            → variabel CSS --rk-*   (landing + kedua panel)
 │    └─ resources/css/tokens.css               → nama Tailwind: bg-brand-600, text-ink, font-display, …
 └─ cards/pdf.blade.php, receipts/payment.blade.php → warna PDF (dompdf tidak mendukung variabel CSS)
```

## Warna

| Token (`config/design.php`) | Nilai | Variabel CSS | Dipakai di |
|---|---|---|---|
| `primary.600` | `#1F3FD6` | `--rk-primary-600`, Tailwind `brand-600`, Filament `primary` | Warna brand: tombol utama, tautan, hero landing, latar halaman login, kartu member, pita kartu & kuitansi PDF |
| `primary.50`–`950` | lihat file | `--rk-primary-*` | Turunan terang/gelap (hover, latar lembut, mode gelap). Latar login mode gelap = `950` |
| `success.600` | `#0B7A40` | `--rk-success-600`, Tailwind `ok`, Filament `success` | Panel **DITERIMA** di Scan Check-in, status "Aktif" di dashboard member, badge Diterima/Lunas/Aktif |
| `danger.600` | `#D01F2E` | `--rk-danger-600`, Tailwind `no`, Filament `danger` | Panel **DITOLAK**, status "Habis", badge Ditolak/Dibatalkan, cap "DIBATALKAN" di kuitansi |
| `accent` | `#FF6A13` | `--rk-accent`, Tailwind `accent` | Tombol ajakan di landing, titik pada wordmark, garis bawah kartu, garis atas kartu login. Teks di atasnya selalu `ink` |
| `ink` | `#0E1326` | `--rk-ink`, Tailwind `ink` | Teks utama landing & PDF, latar bagian gelap landing, tombol "Wajah tidak cocok", status "Dibekukan" |
| `muted` | `#535B73` | `--rk-muted`, Tailwind `muted` | Teks sekunder landing & PDF |
| `line` | `#D5D9E3` | `--rk-line`, Tailwind `line` | Garis & border landing & PDF |
| `paper` | `#F6F7F9` | `--rk-paper`, Tailwind `paper` | Latar halaman landing |
| `surface` | `#FFFFFF` | `--rk-surface`, Tailwind `surface` | Latar kartu paket & header landing |

Warna yang tidak ada di file token:

- **Abu-abu panel** memakai `Color::Slate` bawaan Filament (diatur di kedua PanelProvider).
- **Peringatan** (`warning`) memakai kuning bawaan Filament.
- **QR** selalu hitam di atas putih, juga di mode gelap, supaya terbaca scanner.

## Font

| Token | Nilai | Dipakai di |
|---|---|---|
| `font.sans` | Public Sans (400/500/600/700) | Semua teks; `->font()` di kedua panel, `font-sans` di landing |
| `font.display` | Archivo (800, 800 miring) | Judul halaman (miring), wordmark, angka statistik, status scan; `font-display` |
| `font.url` | URL Bunny Fonts | Satu `<link>` untuk kedua font. Bila font diganti, ganti juga nama keluarga di URL ini |

PDF (kartu & kuitansi) memakai DejaVu Sans bawaan dompdf, karena dompdf tidak memuat web font.

## Radius

| Token | Nilai | Dipakai di |
|---|---|---|
| `radius` | `6px` | `rounded-lg` (tombol, input). `rounded-md` = radius − 2px, `rounded-xl` = radius + 2px (section, kartu) — berlaku di landing dan komponen Filament |

## Logo

Wordmark teks "Repkard." (`resources/views/partials/wordmark.blade.php`): Archivo 800 miring, titik berwarna `accent`. Dipakai di topbar kedua panel, halaman login, landing, dan pratinjau kartu.

## Kontras (WCAG AA)

| Pasangan | Rasio |
|---|---|
| Putih di atas `primary.600` | 7,7 : 1 |
| Putih di atas `success.600` | 5,4 : 1 |
| Putih di atas `danger.600` | 5,3 : 1 |
| `ink` di atas `accent` | 6,4 : 1 |
| `muted` di atas `paper` | 6,3 : 1 |
| `ink` di atas `paper` | 17 : 1 |

Bila nilai token diganti, cek ulang pasangan di atas (minimal 4,5 : 1 untuk teks biasa, 3 : 1 untuk teks besar).

## Screenshot

`docs/screenshots/redesain/sebelum` dan `sesudah` dibuat dengan `node e2e/screenshots.mjs <folder> [light|dark] [lebar,lebar]` saat server E2E berjalan di port 8001 (perintah server ada di `playwright.config.ts`).
