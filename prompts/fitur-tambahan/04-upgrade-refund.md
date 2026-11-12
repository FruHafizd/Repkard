# Fitur Tambahan 04 — Upgrade Paket, Pindah Paket & Refund

**Model:** Sonnet · **Branch:** `feat/upgrade-refund` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 13 baris 4, fitur-list.md bagian 10, `app/Services/MembershipService.php`, `app/Models/Membership.php`

## Tujuan

- **Upgrade**: member pindah ke paket lebih mahal mulai hari ini; sisa nilai paket lama jadi kredit, member membayar selisihnya.
- **Pindah paket** ke yang lebih murah: hanya untuk periode berikutnya, lewat Perpanjang yang sudah ada.
- **Refund**: owner mengembalikan uang dan membatalkan membership.

Bedakan dengan aksi lama **"Batalkan pembayaran"** (= salah catat, uang tidak pernah diterima). Refund = uang sungguhan dikembalikan ke member.

## Yang dikerjakan

### A. Tabel
1. Migration `memberships`: tambah
   - `visits_total` unsignedInteger nullable — kuota awal; **isi otomatis untuk baris lama** dari `visit_quota` paketnya (di migration),
   - `credit_amount` unsignedBigInteger default 0 — kredit yang dibawa dari paket lama,
   - `cancel_reason` string nullable,
   - `replaced_by_id` FK nullable → `memberships`.
   `MembershipService::buat()` mengisi `visits_total` = `visit_quota` paket.
2. Migration `payments`: tambah `type` string default `purchase` — enum `App\Enums\PaymentType`: `Purchase = 'purchase'` "Pembelian", `Upgrade = 'upgrade'` "Upgrade". (Langkah 06 akan menambah `Pt`.)
3. `php artisan make:model Refund -mf`: `member_id` (FK cascade), `membership_id` (FK), `amount` unsignedBigInteger, `method` (cast `PaymentMethod`), `reason` text, `recorded_by` (FK users), `refunded_at` timestamp, timestamps.

### B. Hitung sisa nilai — `MembershipService::remainingValue(Membership $m): int`
- `nilai` = jumlah `payments.amount` (tidak dibatalkan) untuk membership ini + `credit_amount`.
- Jika hari ini < `starts_on` (membership belum mulai) → seluruh `nilai`.
- Rasio hari = sisa hari (hari ini s/d `ends_on`, inklusif) ÷ total hari (`starts_on` s/d `ends_on`, inklusif).
- Jika `visits_total` tidak null: rasio kunjungan = `visits_remaining` ÷ `visits_total`. Pakai **rasio terkecil** dari keduanya.
- Hasil = `intdiv(nilai × pembilang, penyebut)` (dibulatkan ke bawah). Membership dibatalkan / sudah habis → 0.

### C. `MembershipService::upgrade(Member $member, MembershipPlan $newPlan, PaymentMethod $method, int $amount, User $by): Membership`
Dalam satu transaction (`lockForUpdate` member & membership):
1. Wajib punya `currentMembership()`, tidak dibekukan.
2. Tolak jika member punya membership lain yang **belum mulai** (hasil perpanjang di muka): "Ada perpanjangan yang belum dimulai. Batalkan dulu atau tunggu sampai berjalan."
3. Tolak jika `newPlan->price <= plan lama->price`: "Untuk paket yang sama atau lebih murah, gunakan Perpanjang (berlaku periode berikutnya)."
4. `kredit = min(remainingValue(lama), newPlan->price)`; `harus dibayar = newPlan->price − kredit`.
5. Membership lama: `cancelled_at` = sekarang, `cancel_reason` = "Diupgrade ke {nama paket}", `replaced_by_id` = membership baru.
6. Membership baru: mulai hari ini, durasi & kuota dari paket baru, `credit_amount` = kredit.
7. Payment `type = upgrade`, `amount` = `$amount` (default di UI = harus dibayar), `original_amount` = harga paket baru, `note` = "Kredit dari paket lama: Rp …".

### D. `MembershipService::refund(Membership $m, int $amount, PaymentMethod $method, string $reason, User $by): Refund`
- Membership belum dibatalkan. `1 ≤ amount ≤ nilai` (lihat B). Alasan wajib.
- Membership: `cancelled_at`, `cancel_reason` = "Refund: {alasan}". Status member **tidak** diubah (check-in otomatis ditolak "Masa aktif sudah habis" karena tidak ada membership berlaku).
- Simpan `Refund`.

### E. UI (panel admin)
1. Aksi **"Upgrade paket"** di `MemberActions` (owner & pegawai, permission `Update:Member`): pilih paket (hanya yang lebih mahal & aktif), tampilkan rincian: sisa nilai paket lama, harga paket baru, **harus dibayar**; metode bayar; jumlah (default harus dibayar).
2. Aksi **"Perpanjang"** yang sudah ada: ganti label menjadi **"Perpanjang / pindah paket"** dan beri helper text "Paket baru berlaku setelah paket sekarang berakhir." Logika tidak diubah.
3. Aksi **"Refund"** (hanya owner — permission custom **`Refund:Member`**, tidak diberikan ke pegawai): tampilkan nilai membership & sisa nilai (sebagai saran jumlah), jumlah, metode, alasan (wajib), checkbox konfirmasi "Uang sudah dikembalikan ke member".
4. **`RefundResource`** (label "Refund", read-only, hanya owner): tanggal, member, paket, jumlah, metode, alasan, dicatat oleh.
5. Tabel pembayaran (`PaymentResource`): kolom & filter **jenis** (Pembelian / Upgrade).
6. Riwayat membership di view member: tampilkan status Dibatalkan + `cancel_reason`.
7. `AdminStatsOverview` "Pemasukan hari ini": kurangi refund hari ini (hanya untuk user yang punya `ViewAll:Payment`; refund tidak pernah dicatat pegawai).

## Kriteria selesai (tiap poin = test, pakai `Carbon::setTestNow()`)

- [ ] Bulanan Rp 200.000 (1–30 Nov), hari ini 11 Nov → sisa nilai **133.333**; upgrade ke 3 Bulan Rp 550.000 → harus bayar **416.667**, membership baru `credit_amount` 133.333, mulai 11 Nov.
- [ ] Paket 10x Rp 220.000 / 60 hari, sisa 6 kunjungan, sisa 50 hari → sisa nilai **132.000** (rasio kunjungan lebih kecil).
- [ ] Membership yang belum mulai → sisa nilai = nilai penuh.
- [ ] Upgrade ke paket lebih murah / sama → ditolak.
- [ ] Upgrade saat ada perpanjangan yang belum mulai → ditolak.
- [ ] Upgrade saat dibekukan → ditolak.
- [ ] Setelah upgrade, `currentMembership()` = membership baru; membership lama dibatalkan dengan `replaced_by_id`.
- [ ] Pembayaran yang dibatalkan tidak dihitung dalam nilai.
- [ ] Refund oleh pegawai → 403; oleh owner → tersimpan dan check-in member berikutnya ditolak "Masa aktif sudah habis".
- [ ] Refund melebihi nilai → ditolak.
- [ ] Migration mengisi `visits_total` untuk membership lama.
- [ ] Pemasukan hari ini di dashboard owner = pembayaran + pendatang − refund hari ini.

Tandai ✅ di fitur-list.md: 3 baris upgrade/pindah/refund.

## Commit / PR

`feat: upgrade paket prorata, pindah paket, dan refund`
