# Fitur Tambahan 03 — Promo & Kode Diskon

**Model:** Sonnet · **Branch:** `feat/promo` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 3, fitur-list.md bagian 10, `app/Services/MembershipService.php`, `app/Filament/Resources/Members/Actions/MemberActions.php`

## Tujuan

Owner membuat kode promo. Pegawai memakainya saat aktivasi atau perpanjangan. Calon member boleh mengisi kode saat daftar. Setiap pembayaran mencatat harga asli, diskon, dan kode yang dipakai.

## Yang dikerjakan

### A. Tabel
1. `php artisan make:model PromoCode -mf`:

   | Kolom | Tipe | Catatan |
   |---|---|---|
   | `code` | string(30) unique | disimpan HURUF BESAR, hanya `A-Z 0-9 -` |
   | `name` | string | mis. "Promo Tahun Baru" |
   | `discount_type` | string | enum `App\Enums\DiscountType`: `percent` "Persen", `fixed` "Potongan nominal" |
   | `discount_value` | unsignedInteger | persen 1–100, atau rupiah |
   | `starts_on` / `ends_on` | date nullable | kosong = tanpa batas |
   | `max_uses` | unsignedInteger nullable | kosong = tanpa batas |
   | `max_uses_per_member` | unsignedInteger default 1 | |
   | `new_members_only` | boolean default false | hanya untuk aktivasi pertama |
   | `is_active` | boolean default true | |
   | `created_by` | FK users | |

2. Pivot `membership_plan_promo_code` (`membership_plan_id`, `promo_code_id`). **Kosong = berlaku untuk semua paket.**
3. Migration tabel `payments`: tambah `original_amount` (unsignedBigInteger nullable), `discount_amount` (unsignedBigInteger default 0), `promo_code_id` (FK nullable, nullOnDelete). Tambahkan ke `$fillable` `Payment`.
4. Migration tabel `members`: tambah `requested_promo_code` (string(30) nullable).

### B. `app/Services/PromoService.php`
`check(string $code, MembershipPlan $plan, Member $member, bool $isActivation): PromoResult` — readonly class: `valid`, `message`, `promo`, `discount`, `finalPrice`.

Urutan pengecekan (berhenti di alasan pertama, pesan Bahasa Indonesia):
1. kode tidak ada / `is_active` false → "Kode promo tidak ditemukan"
2. di luar `starts_on`–`ends_on` → "Kode promo belum berlaku / sudah berakhir"
3. pivot tidak kosong dan paket tidak termasuk → "Kode promo tidak berlaku untuk paket ini"
4. `new_members_only` dan bukan aktivasi → "Kode promo khusus member baru"
5. jumlah pemakaian (pembayaran **tidak dibatalkan** dengan kode ini) ≥ `max_uses` → "Kuota kode promo sudah habis"
6. pemakaian oleh member ini ≥ `max_uses_per_member` → "Anda sudah memakai kode promo ini"

Hitung diskon dari `plan->price`:
- `percent`: `intdiv($price * $value, 100)` (dibulatkan ke bawah),
- `fixed`: `min($value, $price)`.
`finalPrice = price - discount` (tidak pernah negatif). Pencarian kode tidak peka huruf besar/kecil.

### C. Integrasi ke `MembershipService`
1. `activate()` dan `renew()` mendapat parameter opsional `?PromoCode $promo = null` (taruh **setelah** parameter yang sudah ada supaya pemanggil lama tetap jalan).
2. Di dalam transaction: `lockForUpdate()` baris promo, lalu **cek ulang** dengan `PromoService` (mencegah kuota terlampaui saat dua pegawai memakai bersamaan). Gagal → lempar `InvalidArgumentException` dengan pesan dari `PromoResult`.
3. Simpan di payment: `original_amount` = harga paket, `discount_amount`, `promo_code_id`. Tanpa promo: `original_amount` = harga paket, `discount_amount` = 0.
4. `amount` tetap jumlah yang benar-benar dibayar (diisi pegawai, default = `finalPrice`).

### D. UI
1. **Modal Aktifkan & Perpanjang** (`MemberActions`): field "Kode promo" (opsional). Saat diisi (`live(onBlur: true)`), jalankan `PromoService::check()` dan tampilkan hasil sebagai helper text: "Diskon Rp 50.000 → bayar Rp 150.000" atau pesan error. Field jumlah bayar ikut terisi `finalPrice`. Saat aktivasi, isi awal dari `requested_promo_code`.
2. **Registrasi** (`app/Filament/Member/Pages/Auth/Register.php`): field opsional "Kode promo". Jika diisi, validasi dengan `PromoService::check(..., isActivation: true)` terhadap paket yang dipilih; gagal → error validasi. Simpan ke `members.requested_promo_code`. Widget pending (`PendingActivationWidget`) menampilkan perkiraan total setelah diskon.
3. **`PromoCodeResource`** (panel admin, label "Promo"): form semua kolom + pilih paket (kosong = semua). Tabel: kode, nama, diskon (mis. "20%" / "Rp 50.000"), periode, terpakai/maks, status. Hapus hanya bila belum pernah dipakai; selain itu nonaktifkan.
4. Policy: owner semua; pegawai `ViewAny` + `View` (di `RoleSeeder`).
5. Tampilkan harga asli, diskon, dan kode di: infolist & tabel pembayaran (`PaymentResource`), dan **kuitansi PDF** (`resources/views/receipts/payment.blade.php`).

## Kriteria selesai (tiap poin = test)

- [ ] Promo 20% pada paket Rp 200.000 → diskon 40.000, bayar 160.000.
- [ ] Promo 15% pada Rp 333.333 → diskon 49.999 (dibulatkan ke bawah).
- [ ] Potongan Rp 300.000 pada paket Rp 200.000 → bayar 0, tidak negatif.
- [ ] Masing-masing 6 alasan tolak di B punya test.
- [ ] Pembayaran yang dibatalkan tidak dihitung sebagai pemakaian.
- [ ] `max_uses = 1`: aktivasi kedua dengan kode sama ditolak oleh service (bukan hanya oleh UI).
- [ ] Payment menyimpan `original_amount`, `discount_amount`, `promo_code_id`.
- [ ] Registrasi dengan kode tidak valid → error; dengan kode valid → tersimpan di `requested_promo_code`.
- [ ] Pegawai tidak bisa membuat / mengubah promo (403).
- [ ] Test lama `MembershipServiceTest` tetap hijau tanpa diubah.

Tandai ✅ di fitur-list.md: 3 baris promo.

## Commit / PR

`feat: kode promo dan diskon`
