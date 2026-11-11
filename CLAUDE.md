# CLAUDE.md — Repkard

Repkard adalah aplikasi membership gym (Laravel 12 + Filament 5) untuk membantu gym yang masih mencatat member secara manual beralih ke sistem digital.

- Spesifikasi produk: [prd.md](prd.md)
- Daftar fitur & status: [fitur-list.md](fitur-list.md)

## Stack

| Bagian | Paket |
|---|---|
| Framework | Laravel 12, PHP 8.4 |
| Dashboard | Filament 5 — panel `admin` (`/admin`, Owner + Pegawai) dan panel `member` (`/member`) |
| Role & izin | `spatie/laravel-permission` + `bezhansalleh/filament-shield` (belum di-setup, lihat prd.md) |
| QR code | `chillerlan/php-qrcode` (server), `html5-qrcode` (scanner kamera di browser) |
| Kartu PDF | `barryvdh/laravel-dompdf` |
| Foto member | `intervention/image-laravel` (crop & resize) |
| Database | SQLite saat development, MySQL 8 (Laragon) untuk produksi |

## Perintah

```bash
composer dev            # server + queue + log + vite sekaligus
php artisan serve       # http://localhost:8000
npm run build
php artisan test
php artisan migrate
```

## Alur Git (wajib)

- **Setiap penambahan fitur = branch baru dari `main` + Pull Request ke `main`.** Nama branch: `fase-N/NN-nama-singkat` (untuk langkah di `prompts/`) atau `feat/nama-singkat`.
- **Jangan pernah commit atau push langsung ke `main`.**
- **Jangan merge PR sendiri** — pemilik proyek yang review dan merge.
- Jangan commit sama sekali kalau pemilik proyek belum meminta atau prompt langkah tidak menyuruh.

## Aturan proyek

- **Tampilan belum final.** Desain akan dibuat di Figma oleh pemilik proyek. Pakai tampilan default Filament; jangan menambah warna, font, atau styling custom sampai desain diberikan.
- **Hak akses selalu dicek di server** (Policy / permission), bukan hanya disembunyikan di UI. Role yang hanya boleh melihat harus mendapat akses read-only, bukan tombol yang tersembunyi.
- **Isi QR kartu tidak boleh berupa ID mentah.** Selalu token yang ditandatangani (HMAC) dan bisa dicabut — lihat bagian "Anti penyalahgunaan kartu" di prd.md.
- Foto member dan snapshot check-in adalah data pribadi: simpan di disk `local` (private), sajikan lewat route yang dicek izinnya, bukan di `public/`.
- Bahasa UI: Bahasa Indonesia. Zona waktu dari `APP_TIMEZONE` di `.env`.

## Skill

- `security-audit` (dari cloudflare/security-audit-skill) ada di `.claude/skills/security-audit`. Pakai untuk review keamanan fitur login, role, dan scan kartu.

---

# Pedoman perilaku (Andrej Karpathy guidelines)

Sumber: https://github.com/multica-ai/andrej-karpathy-skills

Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.
