# Langkah 06 — Aktivasi Tatap Muka, Membership & Pembayaran

**Model:** Sonnet · **Branch:** `fase-1/06-aktivasi-pembayaran` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 4.1, 4.3, 12 (keputusan 2 & 4), fitur-list.md bagian 0 dan 5

## Tujuan

Pegawai mengaktifkan member yang datang ke gym (cocokkan wajah + catat bayar), memperpanjang, dan membekukan membership. Pegawai hanya melihat transaksi yang ia catat sendiri.

## Yang dikerjakan

### A. Tabel
1. `Membership` (`-mf`):
   `member_id` (FK cascade), `membership_plan_id` (FK restrict), `starts_on` date, `ends_on` date, `visits_remaining` unsignedInteger nullable (null = tanpa batas), `frozen_from` date nullable, `frozen_until` date nullable, `cancelled_at` timestamp nullable, timestamps.
2. `Payment` (`-mf`):
   `member_id` (FK cascade), `membership_id` (FK nullable, nullOnDelete), `amount` unsignedBigInteger, `method` string (`cash` / `transfer` / `qris` — enum `App\Enums\PaymentMethod` dengan label "Tunai", "Transfer", "QRIS"), `proof_path` nullable (disk `local`), `note` nullable, `recorded_by` (FK users), `paid_at` timestamp, `cancelled_at` nullable, `cancelled_by` nullable FK users, `cancel_reason` nullable, timestamps.
3. Di `Member`: relasi `memberships()`, `payments()`, dan accessor `currentMembership()` → membership yang `cancelled_at` null dan `starts_on <= today <= ends_on` (urut `ends_on` desc).

### B. `app/Services/MembershipService.php`
Semua method dalam **satu DB transaction**.

1. `activate(Member $m, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by): Membership`
   - hanya untuk status `pending` (else throw),
   - `member_seq` = `max(member_seq)` + 1 dengan `lockForUpdate()`; `member_number` = `sprintf('RPK-%s-%04d', now()->format('ym'), $seq)` — contoh `RPK-2611-0001`; nomor urut **tidak** di-reset per bulan,
   - status `active`, `activated_at`, `activated_by`,
   - membership: `starts_on` = hari ini, `ends_on` = hari ini + `duration_days` − 1, `visits_remaining` = `visit_quota` paket,
   - payment tercatat,
   - dispatch event `App\Events\MemberActivated($member)` (listener-nya dibuat di langkah 07).
2. `renew(Member $m, MembershipPlan $plan, PaymentMethod $method, int $amount, User $by): Membership`
   - hanya untuk status `active`,
   - `starts_on` = hari setelah `ends_on` membership terakhir bila masih berlaku, else hari ini.
3. `freeze(Membership $ms, int $days, User $by): void` — `frozen_from` = hari ini, `frozen_until` = hari ini + days − 1, `ends_on` += days. Maks 30 hari. Tolak bila sedang beku.

Pakai `now()` dari Carbon (zona `Asia/Makassar` dari config) supaya bisa di-test dengan `Carbon::setTestNow()`.

### C. UI panel admin
1. Di `MemberResource`: tab/filter **"Menunggu aktivasi"** (status `pending`) sebagai antrean.
2. Aksi tabel/view **"Aktifkan"** (hanya status `pending`) — modal:
   - foto member besar (route `member.photo`) + nama + HP,
   - checkbox wajib **"Saya sudah mencocokkan wajah dengan foto"**,
   - opsi ganti foto (komponen `WebcamPhoto` dari langkah 04),
   - paket (default `selected_plan_id`), metode bayar, jumlah (default harga paket), bukti transfer (opsional, disk `local`).
3. Aksi **"Tolak pendaftaran"** (status `pending`): wajib alasan → hapus foto, force delete member + user.
4. Aksi **"Perpanjang"** (status `active`) dan **"Bekukan"** (membership aktif).
5. `PaymentResource` (panel admin, label "Pembayaran"):
   - buat permission custom **`ViewAll:Payment`** (owner punya otomatis, pegawai **tidak** diberi). User tanpa `ViewAll:Payment` hanya melihat `recorded_by = dirinya` (filter di `getEloquentQuery()` **dan** di Policy `view`). Dengan begitu owner bisa membuka akses semua transaksi ke pegawai dari halaman Role (keputusan prd.md no. 2),
   - kolom: tanggal, nomor member, nama, jumlah, metode, dicatat oleh, status (Dibatalkan bila `cancelled_at`),
   - aksi **"Batalkan"** hanya owner, wajib alasan (tidak menghapus baris),
   - tanpa create manual di resource ini (pembayaran hanya lewat aktivasi/perpanjang).
6. Kuitansi PDF: route `GET /pembayaran/{payment}/kuitansi` (auth + Policy `view`) → dompdf, A6, isi: nama gym, nomor kuitansi (`id`), tanggal, member, paket, jumlah, metode, dicatat oleh. Template Blade polos.

### D. Policy
- `PaymentPolicy`: owner semua kecuali delete; pegawai `viewAny` + `view` (hanya miliknya); member `view` hanya pembayaran miliknya.
- Aksi Aktifkan/Perpanjang/Bekukan/Tolak: owner & pegawai (cek dengan `authorize()` di action, berdasarkan permission `Update:Member`).
- `shield:generate` untuk resource baru, lalu berikan ke pegawai di `RoleSeeder`: `ViewAny:Payment`, `View:Payment` (tanpa `ViewAll:Payment`).

## Kriteria selesai (tiap poin = test)

- [ ] Aktivasi pertama → `RPK-YYMM-0001`; aktivasi kedua → `...-0002`; aktivasi di bulan berikutnya tetap melanjutkan urutan (`RPK-2612-0003`).
- [ ] Aktivasi member yang sudah `active` → ditolak.
- [ ] Paket 30 hari diaktifkan 11 Nov → `ends_on` 10 Des.
- [ ] Perpanjang saat masih aktif → mulai sehari setelah `ends_on` lama; saat sudah habis → mulai hari ini.
- [ ] Bekukan 7 hari → `ends_on` mundur 7 hari; bekukan 31 hari → ditolak.
- [ ] Event `MemberActivated` ter-dispatch saat aktivasi.
- [ ] Pegawai A tidak melihat pembayaran yang dicatat pegawai B (list dan URL langsung → 403).
- [ ] Setelah role pegawai diberi `ViewAll:Payment`, pegawai A melihat semua pembayaran.
- [ ] Pegawai tidak bisa membatalkan pembayaran; owner bisa.
- [ ] Member A tidak bisa mengunduh kuitansi member B (403).
- [ ] Tolak pendaftaran menghapus user, member, dan file fotonya.

## Commit

`feat: aktivasi member, membership, pembayaran, kuitansi`
