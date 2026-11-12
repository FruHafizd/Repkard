import { defineConfig } from '@playwright/test';
import path from 'node:path';

const port = 8001;
const dbFile = path.resolve('database/e2e.sqlite');

// Database terpisah (SQLite) supaya DB development tidak tersentuh.
export const serverEnv = {
    APP_ENV: 'local',
    APP_URL: `http://127.0.0.1:${port}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbFile,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    LOG_CHANNEL: 'single',
    LOG_LEVEL: 'debug',
};

export default defineConfig({
    testDir: './e2e',
    workers: 1,
    fullyParallel: false,
    timeout: 60_000,
    reporter: [['list']],
    use: { baseURL: `http://127.0.0.1:${port}`, locale: 'id-ID' },
    webServer: {
        command: `php -r "file_put_contents('database/e2e.sqlite','');" && php artisan migrate:fresh --seed --force && php e2e/seed.php && php e2e/seed-checkin.php && php e2e/seed-member.php && php e2e/seed-pengumuman.php && php e2e/seed-menghilang.php && php e2e/seed-promo.php && php e2e/seed-upgrade.php && php e2e/seed-pt.php && php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}`,
        reuseExistingServer: false,
        env: serverEnv,
    },
});
