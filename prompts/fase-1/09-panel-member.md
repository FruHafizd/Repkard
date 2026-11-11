# Langkah 09 — Panel Member

**Model:** Sonnet · **Branch:** `fase-1/09-panel-member` · **Wajib baca dulu:** `prompts/ATURAN.md`, prd.md bagian 3, fitur-list.md kolom **Member** (🙋)

## Tujuan

Member yang aktif bisa melihat & mencetak kartunya, melihat masa aktif, riwayat kehadiran dan pembayaran, serta mengubah data kontaknya. Semua data **hanya miliknya sendiri**.

## Yang dikerjakan

Semua di panel `member` (`app/Filament/Member/...`).

1. **Dashboard** — widget (hanya untuk status `active`):
   - status membership: Aktif / Habis / Dibekukan (dihitung dari `currentMembership()`),
   - nama paket, berlaku sampai, sisa hari, sisa kuota (bila ada),
   - jumlah kehadiran bulan ini.
   Widget `PendingActivationWidget` dari langkah 05 tetap untuk status `pending`.
2. **Page "Kartu Saya"** (hanya status `active` dengan kartu aktif; selain itu tampilkan pesan):
   - pratinjau kartu: foto, nama, nomor member, QR (token dari `CardTokenService`),
   - tombol **"Unduh / Cetak PDF"** → route `member.card.pdf` dari langkah 07.
   - QR di sini masih statis (QR dinamis = Fase 2).
3. **Resource "Riwayat Kehadiran"** (read-only, `CheckInResource` versi panel member): hanya check-in `accepted` milik member login; kolom tanggal & jam. Tanpa create/edit/delete.
4. **Resource "Riwayat Pembayaran"** (read-only): pembayaran milik member login; kolom tanggal, paket, jumlah, metode, status; aksi unduh kuitansi (route langkah 06).
5. **Profil** — aktifkan `->profile(EditProfile::class)` di panel member dengan page custom extends `Filament\Auth\Pages\EditProfile`:
   - boleh diubah: nomor HP, alamat, kontak darurat, password,
   - **tidak boleh**: nama, email, foto, tanggal lahir, jenis kelamin (tampilkan read-only / disabled + `dehydrated(false)`, **dan** abaikan field ini di server walau dikirim).
6. Scoping: setiap query di panel member wajib `where member_id = auth()->user()->member->id` di `getEloquentQuery()` **dan** dicek Policy.

## Kriteria selesai (tiap poin = test)

- [ ] Member A membuka URL view check-in / pembayaran milik member B → 403 atau 404.
- [ ] List riwayat hanya berisi data member login (buat data 2 member, cek isi).
- [ ] Mengirim `name` atau `photo_path` lewat form profil tidak mengubah data.
- [ ] Member `pending` membuka "Kartu Saya" → pesan "Kartu tersedia setelah akun diaktifkan", tanpa QR.
- [ ] Member `active` → "Kartu Saya" menampilkan QR yang bisa di-`resolve()` ke kartunya.
- [ ] Dashboard menampilkan "Dibekukan" saat hari ini dalam rentang beku.

## Commit

`feat: panel member - kartu, riwayat, profil`
