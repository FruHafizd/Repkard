import fs from 'node:fs';
import path from 'node:path';

const cacheDir = 'storage/framework/cache/data';

/**
 * Login dibatasi 5x/menit per IP, dan semua test datang dari IP yang sama.
 * Hitungan itu disimpan di cache file; kosongkan sebelum tiap test supaya
 * hasil test tidak bergantung pada seberapa cepat test sebelumnya selesai.
 */
export function resetLoginThrottle(): void {
    for (const entry of fs.readdirSync(cacheDir)) {
        if (entry !== '.gitignore') {
            fs.rmSync(path.join(cacheDir, entry), { recursive: true, force: true });
        }
    }
}
