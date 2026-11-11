# Langkah 10 — Dashboard Admin

**Model:** Haiku · **Branch:** `fase-1/10-dashboard` · **Wajib baca dulu:** `prompts/ATURAN.md`, fitur-list.md bagian 8

## Tujuan

Owner dan pegawai melihat ringkasan operasional begitu masuk `/admin`. **Tidak ada angka pemasukan** di langkah ini (laporan pemasukan = Fase 3, hanya owner).

## Yang dikerjakan

Widget di panel admin (`php artisan make:filament-widget ... --panel=admin`), tampil untuk owner & pegawai:

1. **StatsOverview** — 4 angka:
   - Member aktif (status `active` dan punya `currentMembership()`),
   - Menunggu aktivasi (status `pending`),
   - Habis dalam 7 hari (`ends_on` antara hari ini dan +7 hari),
   - Check-in hari ini (`accepted`, `checked_in_at` hari ini).
   Gunakan query `count()` di database, bukan load semua data ke PHP.
2. **Tabel "Akan habis 7 hari lagi"** — nama, HP, nomor member, paket, berlaku sampai. Urut `ends_on` naik. Klik baris → view member.
3. **Tabel "Check-in hari ini"** — jam, nama, nomor member, metode. Maks 20 terbaru.
4. Hapus widget bawaan `FilamentInfoWidget` dari `AdminPanelProvider` (biarkan `AccountWidget`).

## Kriteria selesai (tiap poin = test)

- [ ] Dengan data uji (2 aktif, 1 pending, 1 habis 3 hari lagi, 3 check-in hari ini + 1 kemarin) angka di StatsOverview tepat.
- [ ] Member yang `ends_on`-nya 8 hari lagi tidak muncul di tabel "Akan habis".
- [ ] Pegawai dan owner bisa membuka dashboard; tidak ada angka rupiah di dashboard.

## Commit

`feat: dashboard admin`
