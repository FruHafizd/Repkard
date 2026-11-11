# Repkard

Aplikasi membership gym berbasis web untuk membantu gym yang masih mencatat member di buku beralih ke sistem digital: kartu member berfoto dengan QR, scan check-in, dan tiga role (Owner, Pegawai, Member).

> Status: fondasi terpasang, fitur belum dikerjakan.

- Spesifikasi produk: [prd.md](prd.md)
- Daftar fitur & hak akses per role: [fitur-list.md](fitur-list.md)

## Stack

Laravel 12 · Filament 5 · PHP 8.4 · SQLite (dev) / MySQL 8 (produksi)

## Menjalankan di lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

Buka http://localhost:8000/admin (Owner & Pegawai) atau http://localhost:8000/member (Member).
