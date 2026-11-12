// Ambil screenshot area utama untuk review tampilan.
// Pakai: jalankan server E2E di :8001 (lihat playwright.config.ts), lalu
//   node e2e/screenshots.mjs <folder-tujuan> [light|dark] [lebar,lebar]
import { chromium } from '@playwright/test';
import fs from 'node:fs';

const base = 'http://127.0.0.1:8001';
const out = process.argv[2] ?? 'docs/screenshots/redesain/sesudah';
const scheme = process.argv[3] ?? 'light';
const lebar = (process.argv[4] ?? '390,1280').split(',').map(Number);
fs.mkdirSync(out, { recursive: true });
const tokens = JSON.parse(fs.readFileSync('test-results/checkin-tokens.json', 'utf8'));

const browser = await chromium.launch();

// Login sekali per akun (login dibatasi 5x/menit); sesinya disimpan dan dipakai ulang di tiap lebar layar & tiap run.
const cacheFile = 'test-results/screenshot-sessions.json';
const cache = fs.existsSync(cacheFile) ? JSON.parse(fs.readFileSync(cacheFile, 'utf8')) : {};

async function login(panel, email) {
    if (cache[email]) return cache[email];
    const ctx = await browser.newContext({ locale: 'id-ID' });
    const page = await ctx.newPage();
    await page.goto(`${base}/${panel}/login`);
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await page.waitForURL(new RegExp(`/${panel}$`));
    const state = await ctx.storageState();
    await ctx.close();
    cache[email] = state;
    fs.writeFileSync(cacheFile, JSON.stringify(cache));
    return state;
}

async function sesi(width, storageState, fn) {
    const ctx = await browser.newContext({ viewport: { width, height: 800 }, locale: 'id-ID', colorScheme: scheme, storageState });
    const page = await ctx.newPage();
    const shot = async (name) => {
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(400);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow > 0) console.log(`!! scroll horizontal ${overflow}px: ${name}-${width}`);
        await page.screenshot({ path: `${out}/${name}-${width}.png`, fullPage: true });
    };
    await fn(page, shot);
    await ctx.close();
}

const aktif = await login('member', 'pm-aktif@example.com');
const pending = await login('member', 'pm-pending@example.com');
const owner = await login('admin', 'owner@repkard.test');

for (const w of lebar) {
    await sesi(w, undefined, async (page, shot) => {
        await page.goto(base);
        await shot('landing');
        await page.goto(`${base}/member/login`);
        await shot('login-member');
        await page.goto(`${base}/member/register`);
        await shot('daftar-member');
        await page.goto(`${base}/admin/login`);
        await shot('login-admin');
    });
    await sesi(w, aktif, async (page, shot) => {
        await page.goto(`${base}/member`);
        await shot('dashboard-member');
        await page.goto(`${base}/member/kartu-saya`);
        await shot('kartu-saya');
        await page.goto(`${base}/member/payments`);
        await shot('riwayat-pembayaran');
    });
    await sesi(w, pending, async (page, shot) => {
        await page.goto(`${base}/member`);
        await shot('dashboard-member-pending');
    });
    await sesi(w, owner, async (page, shot) => {
        await page.goto(`${base}/admin`);
        await shot('dashboard-admin');
        await page.goto(`${base}/admin/members`);
        await shot('list-member');
        await page.goto(`${base}/admin/payments`);
        await shot('list-pembayaran');
    });
}

for (const w of [...new Set([...lebar, 1024])]) {
    await sesi(w, owner, async (page, shot) => {
        await page.goto(`${base}/admin/scan-check-in`);
        await shot('scan-siap');
        const input = page.getByPlaceholder(/Scanner USB/);
        await input.fill(tokens.manual);
        await input.press('Enter');
        await page.getByAltText('Foto member').waitFor();
        await shot('scan-diterima');
        await input.fill(tokens.habis);
        await input.press('Enter');
        await page.getByText('Ditolak').first().waitFor();
        await shot('scan-ditolak');
    });
}

await browser.close();
