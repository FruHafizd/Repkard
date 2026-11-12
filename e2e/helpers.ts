import fs from 'node:fs';

/**
 * Login dibatasi 5x/menit per IP, dan semua test datang dari IP yang sama.
 * Hitungan itu disimpan di cache file; kosongkan sebelum tiap test supaya
 * hasil test tidak bergantung pada seberapa cepat test sebelumnya selesai.
 */
export function resetLoginThrottle(): void {
    fs.rmSync('storage/framework/cache/data', { recursive: true, force: true });
}
