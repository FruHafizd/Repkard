# Langkah 02 — Paket Membership

**Model:** Haiku · **Branch:** `fase-1/02-paket` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 7 poin 4, fitur-list.md bagian 4

## Tujuan

Owner bisa mengatur paket membership (termasuk paket per kunjungan dengan kuota). Pegawai hanya bisa melihat.

## Yang dikerjakan

1. `php artisan make:model MembershipPlan -mf` dengan kolom:

   | Kolom | Tipe | Catatan |
   |---|---|---|
   | `name` | string | contoh "Bulanan" |
   | `description` | text, nullable | |
   | `duration_days` | unsignedInteger | masa berlaku, min 1 |
   | `visit_quota` | unsignedInteger, nullable | kosong = tanpa batas kunjungan |
   | `price` | unsignedBigInteger | rupiah |
   | `is_active` | boolean, default true | paket nonaktif tidak tampil untuk dipilih |
   | `sort_order` | unsignedInteger, default 0 | urutan tampil |

2. Model: casts (`is_active` boolean), scope `active()` (is_active = true, urut `sort_order`).
3. Resource panel admin: `php artisan make:filament-resource MembershipPlan --panel=admin --view`.
   - Label: "Paket".
   - Form: semua kolom di atas. `price` input angka dengan prefix "Rp". `visit_quota` dengan helper text "Kosongkan untuk tanpa batas kunjungan".
   - Table: nama, durasi (mis. "30 hari"), kuota ("Tanpa batas" bila null), harga (format `Rp 200.000`), status aktif. Default urut `sort_order`.
   - **Tidak ada aksi hapus** — paket dinonaktifkan (agar riwayat membership lama tetap utuh).
4. `MembershipPlanPolicy`: owner semua kecuali delete; pegawai `viewAny` + `view`; member semua false.
5. Generate permission Shield untuk resource ini dan berikan `ViewAny:MembershipPlan` + `View:MembershipPlan` ke pegawai di `RoleSeeder`.
6. `MembershipPlanSeeder` (dipanggil `DatabaseSeeder` hanya di local):
   - Harian — 1 hari, kuota 1, Rp 25.000
   - Bulanan — 30 hari, tanpa batas, Rp 200.000
   - 3 Bulan — 90 hari, tanpa batas, Rp 550.000
   - Tahunan — 365 hari, tanpa batas, Rp 2.000.000
   - 10x Kunjungan — 60 hari, kuota 10, Rp 220.000

## Kriteria selesai (tiap poin = test)

- [ ] Owner bisa membuat dan mengubah paket.
- [ ] Pegawai bisa membuka list & view paket, tetapi create/edit memberi 403.
- [ ] Validasi: `duration_days` ≥ 1, `price` ≥ 0, `visit_quota` null atau ≥ 1.
- [ ] Scope `active()` tidak mengembalikan paket nonaktif.

## Commit

`feat: paket membership`
