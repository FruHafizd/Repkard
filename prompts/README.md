# Prompt Pengerjaan — Repkard

Kumpulan prompt untuk dikerjakan oleh model yang lebih murah (Sonnet / Haiku), satu langkah per sesi.

## Cara pakai

1. Buka sesi baru Claude Code di folder `D:\Developments\Repkard`.
2. Pilih model sesuai kolom **Model** di tabel bawah (lewat pemilih model di aplikasi).
3. Kirim satu kalimat ini, ganti nama filenya:

   ```
   Kerjakan prompts/fase-1/01-role-izin.md
   ```

4. Model membuat **branch baru**, commit di branch itu, push, lalu membuat **Pull Request** ke `main` (tidak pernah commit langsung ke `main`, tidak pernah merge sendiri).
5. Review PR-nya di GitHub, lalu merge ke `main` sebelum lanjut ke langkah berikutnya.
6. **Jangan jalankan dua langkah sekaligus** — tiap langkah bergantung pada langkah sebelumnya.

## Urutan Fase 1

| # | File | Isi | Model | Butuh langkah |
|---|---|---|---|---|
| 01 | [01-role-izin.md](fase-1/01-role-izin.md) | Role, Shield, akses panel, kelola pegawai | Sonnet | — |
| 02 | [02-paket.md](fase-1/02-paket.md) | Paket membership | Haiku | 01 |
| 03 | [03-member-foto.md](fase-1/03-member-foto.md) | Data member, foto private, daftar oleh pegawai | Sonnet | 02 |
| 04 | [04-webcam.md](fase-1/04-webcam.md) | Ambil foto dari webcam | Sonnet | 03 |
| 05 | [05-landing-registrasi.md](fase-1/05-landing-registrasi.md) | Landing page, daftar mandiri, verifikasi email, wajib ganti password | Sonnet | 04 |
| 06 | [06-aktivasi-pembayaran.md](fase-1/06-aktivasi-pembayaran.md) | Aktivasi tatap muka, membership, pembayaran, kuitansi | Sonnet | 05 |
| 07 | [07-kartu.md](fase-1/07-kartu.md) | Token QR HMAC, kartu PDF, terbit ulang, cabut | Sonnet | 06 |
| 08 | [08-scan-checkin.md](fase-1/08-scan-checkin.md) | Halaman scan, aturan check-in, riwayat | Sonnet | 07 |
| 09 | [09-panel-member.md](fase-1/09-panel-member.md) | Kartu saya, riwayat, profil | Sonnet | 08 |
| 10 | [10-dashboard.md](fase-1/10-dashboard.md) | Widget dashboard admin | Haiku | 09 |
| 11 | [11-review-keamanan.md](fase-1/11-review-keamanan.md) | Review keamanan seluruh Fase 1 | **Opus** (model kuat) | 10 |

Langkah 11 sengaja memakai model kuat: model murah membangun, model kuat memeriksa.

## Fase 2–4

Prompt Fase 2–4 ditulis setelah Fase 1 selesai, supaya isinya sesuai dengan kode yang benar-benar ada (nama class, tabel, service).

## Kalau model berhenti dan bertanya

Itu disengaja ([ATURAN.md](ATURAN.md) menyuruh model bertanya kalau ada yang tidak jelas). Jawab pertanyaannya, atau bawa pertanyaannya ke sesi dengan model kuat.
