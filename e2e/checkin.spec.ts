import { expect, Page, test } from '@playwright/test';
import fs from 'node:fs';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/checkin';
const tokens = () => JSON.parse(fs.readFileSync('test-results/checkin-tokens.json', 'utf8'));
const sesi = new Map<string, any>();

test.beforeEach(() => resetLoginThrottle());

async function login(page: Page, email: string) {
    const cookies = sesi.get(email);
    if (cookies) {
        await page.context().addCookies(cookies);
        return;
    }
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
    sesi.set(email, await page.context().cookies());
}

async function bukaScan(page: Page) {
    await page.goto('/admin/scan-check-in');
    await expect(page.getByPlaceholder(/Scanner USB/)).toBeVisible();
}

async function scanUsb(page: Page, token: string) {
    const input = page.getByPlaceholder(/Scanner USB/);
    await input.fill(token);
    await input.press('Enter');
}

test.describe.serial('scan check-in', () => {
    test('scanner USB: diterima, foto besar, konfirmasi', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        await page.screenshot({ path: `${SS}/1-halaman-scan.png`, fullPage: true });

        await scanUsb(page, tokens().valid);
        await expect(page.getByText('Budi Valid')).toBeVisible();
        const foto = page.getByAltText('Foto member');
        await expect(foto).toBeVisible();
        expect((await foto.boundingBox())!.width).toBeGreaterThanOrEqual(300);
        await expect(page.getByText(/RPK-2611/).first()).toBeVisible();
        await page.screenshot({ path: `${SS}/2-diterima-cocokkan-wajah.png`, fullPage: true });

        await page.getByRole('button', { name: /Wajah cocok/ }).click();
        await expect(page.getByText('Check-in berhasil dicatat')).toBeVisible();
        await page.screenshot({ path: `${SS}/3-check-in-berhasil.png`, fullPage: true });
    });

    test('kuota 2: check-in kedua berhasil, ketiga ditolak', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        await scanUsb(page, tokens().valid);
        await expect(page.getByText('Sisa kuota')).toBeVisible();
        await page.getByRole('button', { name: /Wajah cocok/ }).click();
        await expect(page.getByText('Check-in berhasil dicatat')).toBeVisible();

        await scanUsb(page, tokens().valid);
        await expect(page.getByText('Kuota kunjungan habis')).toBeVisible();
        await page.screenshot({ path: `${SS}/4-ditolak-kuota-habis.png`, fullPage: true });
    });

    test('alasan tolak otomatis: habis, beku, kuota 0, token palsu', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        const kasus: [string, string, string][] = [
            [tokens().habis, 'Masa aktif sudah habis', '5-ditolak-habis'],
            [tokens().beku, 'Membership sedang dibekukan', '6-ditolak-beku'],
            [tokens().kuota0, 'Kuota kunjungan habis', '7-ditolak-kuota-nol'],
            ['RPK1.1.1.palsupalsupalsupalsupalsupalsupalsupalsup', 'Kartu tidak valid atau sudah tidak berlaku', '8-ditolak-token-palsu'],
        ];
        for (const [token, alasan, nama] of kasus) {
            await scanUsb(page, token);
            await expect(page.getByText(alasan).first()).toBeVisible();
            await page.screenshot({ path: `${SS}/${nama}.png`, fullPage: true });
        }
    });

    test('wajah tidak cocok: ditolak dan tercatat', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        await scanUsb(page, tokens().foto);
        await expect(page.getByText('Foto Kartu')).toBeVisible();
        await page.getByRole('button', { name: /Wajah tidak cocok/ }).click();
        await expect(page.getByText('Wajah tidak cocok').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/9-wajah-tidak-cocok.png`, fullPage: true });
    });

    test('scan dari foto kartu (unggah gambar QR)', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        const link = page.getByText(/Scan an Image File|Pindai file gambar/i);
        if (await link.count()) await link.first().click();
        await page.locator('#reader input[type=file]').setInputFiles('test-results/kartu-foto.png');
        await expect(page.getByText('Foto Kartu')).toBeVisible({ timeout: 15000 });
        await page.screenshot({ path: `${SS}/10-scan-dari-foto-kartu.png`, fullPage: true });
    });

    test('check-in manual: alasan wajib, lalu berhasil', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaScan(page);
        await page.getByRole('button', { name: 'Check-in manual' }).click();
        const modal = page.locator('[role=dialog]');
        await modal.getByRole('button', { name: /^Member/ }).click();
        await page.keyboard.type('Manual');
        await page.getByRole('option', { name: /Manual Mawar/ }).click();
        await modal.getByRole('button', { name: 'Kirim' }).click();
        expect(await modal.getByLabel(/Alasan check-in manual/).evaluate((el: HTMLTextAreaElement) => el.validity.valueMissing)).toBe(true);
        await expect(modal.getByLabel(/Alasan check-in manual/)).toBeVisible();
        await page.screenshot({ path: `${SS}/11-manual-tanpa-alasan.png` });

        await modal.getByLabel(/Alasan check-in manual/).fill('Kartu ketinggalan');
        await modal.getByRole('button', { name: 'Kirim' }).click();
        await expect(page.getByAltText('Foto member')).toBeVisible();
        await expect(page.getByRole('button', { name: /Wajah cocok/ })).toBeVisible();
        await page.screenshot({ path: `${SS}/12-manual-foto.png`, fullPage: true });
        await page.getByRole('button', { name: /Wajah cocok/ }).click();
        await expect(page.getByText('Check-in berhasil dicatat')).toBeVisible();
    });

    test('riwayat check-in memuat semua percobaan', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/check-ins');
        await expect(page.getByText('Budi Valid').first()).toBeVisible();
        const tabel = page.locator('table');
        await expect(tabel.getByText('Diterima').first()).toBeVisible();
        await expect(tabel.getByText('Ditolak').first()).toBeVisible();
        await expect(tabel.getByText('Manual', { exact: true }).first()).toBeVisible();
        await expect(tabel.getByText('Kuota kunjungan habis').first()).toBeVisible();
        await expect(tabel.getByText('Wajah tidak cocok').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/13-riwayat.png`, fullPage: true });
    });

    test('hak akses: member tidak bisa membuka halaman scan', async ({ page }) => {
        await page.goto('/member/login');
        await page.getByRole('textbox', { name: /^Alamat email/ }).fill('checkin1@example.com');
        await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
        await page.getByRole('button', { name: /masuk/i }).click();
        await expect(page).toHaveURL(/\/member/);
        await page.goto('/admin/scan-check-in').catch(() => {});
        await page.waitForLoadState();
        await expect(page.getByPlaceholder(/Scanner USB/)).toHaveCount(0);
        await expect(page.getByText('Pindai kartu')).toHaveCount(0);
        await page.screenshot({ path: `${SS}/14-member-ditolak.png` });
    });
});
