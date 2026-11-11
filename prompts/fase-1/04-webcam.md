# Langkah 04 — Ambil Foto dari Webcam

**Model:** Sonnet · **Branch:** `fase-1/04-webcam` · **Wajib baca dulu:** `prompts/ATURAN.md`, fitur-list.md bagian 3 baris "Ambil foto dari webcam"

## Tujuan

Di form member (panel admin), pegawai bisa mengambil foto langsung dari webcam laptop/tablet, selain unggah file.

## Yang dikerjakan

1. Custom form field Filament: `app/Filament/Forms/Components/WebcamPhoto.php` (extends `Filament\Forms\Components\Field`) + view Blade `resources/views/filament/forms/components/webcam-photo.blade.php`.
   - Lihat contoh field custom di dokumentasi/kode `vendor/filament/forms` (cara `$getStatePath()` dan `x-data` dengan `$wire.$entangle(...)`).
   - Alpine.js (sudah ada di Filament):
     - tombol **"Nyalakan kamera"** → `navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })` ke elemen `<video>`,
     - tombol **"Ambil foto"** → gambar frame ke `<canvas>` 600×800 (crop tengah rasio 3:4) → state = `canvas.toDataURL('image/jpeg', 0.85)`,
     - tombol **"Ulangi"** → hapus state, tampilkan video lagi,
     - matikan semua track kamera saat foto diambil dan saat komponen dihapus.
   - Bila kamera ditolak/tidak ada: tampilkan pesan "Kamera tidak tersedia, silakan unggah foto." (tanpa error JS).
   - Styling hanya kelas Tailwind/Filament bawaan.
2. `MemberPhotoService::storeFromDataUrl(string $dataUrl): string`:
   - hanya terima prefix `data:image/jpeg;base64,` atau `data:image/png;base64,`,
   - `base64_decode(..., strict: true)`, tolak bila gagal atau > 5 MB,
   - proses lewat Intervention (sama seperti `store()`: cover 600×800, JPEG 85) — jangan simpan byte mentah dari browser.
3. Pakai field di form create & edit `MemberResource`, berdampingan dengan `FileUpload` yang sudah ada:
   - label "Foto dari kamera" dan "atau unggah foto",
   - aturan simpan: jika state webcam terisi → pakai webcam; else jika ada upload → pakai upload; minimal salah satu wajib saat create.
   - state webcam **tidak** boleh disimpan ke database (dehydrated false / tangani di `mutateFormDataBeforeCreate/Save`).

## Kriteria selesai

- [ ] Test unit `storeFromDataUrl`: JPEG valid tersimpan; prefix lain (mis. `data:text/html`) ditolak; base64 rusak ditolak; > 5 MB ditolak.
- [ ] Test feature: create member dengan data URL webcam → `photo_path` terisi, file ada di disk `local`.
- [ ] Uji manual di browser (tulis hasilnya di laporan): kamera menyala, ambil foto, ulangi, simpan; kamera mati setelah foto diambil.

## Catatan

`getUserMedia` hanya jalan di `localhost` atau HTTPS. Di `http://localhost:8000` aman.

## Commit

`feat: ambil foto member dari webcam`
