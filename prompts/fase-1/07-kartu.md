# Langkah 07 — Kartu Member (Token QR HMAC + PDF)

**Model:** Sonnet · **Branch:** `fase-1/07-kartu` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 5 dan 6 (lapisan 1–2), fitur-list.md bagian 6

## Tujuan

Kartu terbit otomatis setelah aktivasi. Isi QR adalah token bertanda tangan yang bisa dicabut. Kartu bisa diunduh sebagai PDF ukuran kartu ID.

## Yang dikerjakan

### A. Konfigurasi rahasia
1. `config/repkard.php`: `'card_secret' => env('CARD_SECRET')`.
2. `.env.example`: tambah `CARD_SECRET=` (kosong) dengan komentar cara membuat: `php -r "echo bin2hex(random_bytes(32));"`.
3. Isi `CARD_SECRET` di `.env` lokal kamu (jangan commit `.env`). Di `phpunit.xml` tambahkan `<env name="CARD_SECRET" value="test-secret-only-for-tests"/>`.

### B. Tabel `member_cards` (`MemberCard -mf`)
`member_id` (FK cascade), `version` unsignedInteger, `issued_at` timestamp, `issued_by` FK users nullable, `revoked_at` nullable, `revoked_by` nullable, `revoke_reason` nullable, timestamps. Unique (`member_id`, `version`).
Relasi `Member hasMany cards` + `activeCard()` (yang `revoked_at` null).

### C. `app/Services/CardTokenService.php`
- `make(MemberCard $card): string` → `RPK1.{card_id}.{version}.{sig}` dengan `sig` = base64url (tanpa `=`) dari `hash_hmac('sha256', "RPK1.{card_id}.{version}", secret, true)`.
- `resolve(string $token): ?MemberCard`:
  - trim, panjang maks 200, cocokkan regex `^RPK1\.(\d+)\.(\d+)\.([A-Za-z0-9_-]{43})$`,
  - hitung ulang tanda tangan, bandingkan dengan **`hash_equals`**,
  - ambil kartu dengan `id` **dan** `version` cocok **dan** `revoked_at` null,
  - selain itu kembalikan `null` (jangan lempar exception, jangan bocorkan alasan).
- Lempar `RuntimeException` jika `card_secret` kosong.

### D. `app/Services/MemberCardService.php`
- `issue(Member $m, ?User $by): MemberCard` — cabut kartu aktif lama (alasan "Diganti kartu baru"), buat versi baru = versi terakhir + 1. Satu transaction.
- `revoke(MemberCard $c, User $by, string $reason): void`.
- Listener `IssueCardOnActivation` untuk event `MemberActivated` → `issue()` jika member punya foto.

### E. QR & PDF
1. QR: `chillerlan/php-qrcode` → data URI PNG/SVG dari token (ECC level M).
2. View `resources/views/cards/pdf.blade.php`, dompdf, ukuran **CR80 85,6 × 54 mm** (`setPaper([0, 0, 242.65, 153.07])`), 2 halaman:
   - depan: nama gym, foto (base64 dari disk `local`), nama, nomor member, tanggal terbit,
   - belakang: QR besar + teks "Kartu ini hanya berlaku untuk pemilik yang namanya tertera."
   - **Jangan cetak tanggal habis.** Template polos (hitam-putih).
3. Route `GET /kartu/{member}/pdf` (nama `member.card.pdf`), auth + Policy `downloadCard`: owner & pegawai semua; member hanya miliknya; 404 jika tidak ada kartu aktif.
4. PDF dibuat saat diminta (tidak disimpan), jadi perubahan nama/foto otomatis ikut di cetakan berikutnya tanpa versi baru.

### F. UI panel admin (MemberResource)
- Aksi **"Cetak kartu"** (status active + punya kartu aktif) → unduh PDF.
- Aksi **"Terbitkan ulang kartu"** (owner & pegawai) — wajib alasan (mis. "Kartu hilang"). Notifikasi: "Kartu lama tidak berlaku lagi."
- Aksi **"Cabut kartu"** (**owner saja**) — wajib alasan.
- Di view member: riwayat kartu (versi, terbit, dicabut, alasan).

## Kriteria selesai (tiap poin = test)

- [ ] Aktivasi member berfoto → otomatis punya kartu versi 1.
- [ ] `resolve(make($card))` mengembalikan kartu yang sama.
- [ ] Token dengan 1 karakter tanda tangan diubah → `null`.
- [ ] Token dengan ID diubah (tanda tangan lama) → `null`.
- [ ] Setelah terbit ulang: token versi 1 → `null`, token versi 2 → kartu.
- [ ] Kartu dicabut → `null`.
- [ ] Token sampah (`"1"`, string 500 karakter, `"RPK1..."`) → `null` tanpa exception.
- [ ] Pegawai tidak bisa mencabut kartu (403); owner bisa.
- [ ] Member A tidak bisa mengunduh PDF kartu member B (403); miliknya sendiri → 200 `application/pdf`.

## Commit

`feat: kartu member dengan token QR HMAC dan PDF`
