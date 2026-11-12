import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/menghilang';

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

test.describe('Member menghilang', () => {
    test('pegawai: daftar, filter, catat tindak lanjut, riwayat, dan tanpa error', async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        page.on('response', (r) => {
            if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`);
        });

        await loginAdmin(page, 'pegawai@repkard.test');

        // Dashboard: stat tampil.
        await expect(page.getByText('Mulai menghilang').first()).toBeVisible({ timeout: 15_000 });
        await page.screenshot({ path: `${SS}/1-dashboard.png`, fullPage: true });

        await page.goto('/admin/member-menghilang');
        await expect(page.getByRole('heading', { name: 'Member Menghilang' }).first()).toBeVisible();

        // Hanya yang memenuhi definisi; filter default menyembunyikan yang sudah dihubungi.
        await expect(page.getByText('Hilang Peringatan')).toBeVisible();
        await expect(page.getByText('Hilang Bahaya')).toBeVisible();
        for (const nama of ['Hilang Baru Datang', 'Hilang Beku', 'Hilang Kuota Habis', 'Hilang Sudah Dihubungi']) {
            await expect(page.getByText(nama)).toHaveCount(0);
        }
        await expect(page.getByText('20 hari', { exact: true })).toBeVisible();
        await expect(page.getByText('40 hari', { exact: true })).toBeVisible();

        // Urut: absen terlama dulu.
        const rows = page.locator('tbody tr');
        await expect(rows.first()).toContainText('Hilang Bahaya');
        await page.screenshot({ path: `${SS}/2-daftar.png`, fullPage: true });

        // Catat tindak lanjut.
        await rows.filter({ hasText: 'Hilang Peringatan' }).getByRole('button', { name: 'Catat tindak lanjut' }).click();
        const modal = page.locator('.fi-modal-window');
        await expect(modal).toBeVisible();
        await modal.getByLabel('Cara menghubungi').selectOption('telepon');
        await modal.getByLabel('Hasil').selectOption('akan_datang');
        await modal.getByLabel('Catatan').fill('Janji datang Senin');
        await page.screenshot({ path: `${SS}/3-modal.png` });
        await modal.getByRole('button', { name: /kirim|simpan|submit|catat/i }).last().click();
        await expect(page.getByText('Tindak lanjut dicatat')).toBeVisible();

        // Tersembunyi oleh filter default setelah dicatat.
        await expect(page.getByText('Hilang Peringatan')).toHaveCount(0);

        // Riwayat di halaman view member.
        await page.goto('/admin/members');
        await page.getByPlaceholder(/cari/i).fill('Hilang Peringatan');
        await page.getByRole('link', { name: 'Hilang Peringatan' }).first().click();
        await expect(page.getByText('Riwayat tindak lanjut')).toBeVisible();
        await expect(page.getByText('Janji datang Senin')).toBeVisible();
        await expect(page.locator('main').getByText('Pegawai Repkard').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/4-riwayat.png`, fullPage: true });

        expect(errors).toEqual([]);
    });

    test('responsif 390px', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/member-menghilang');
        await expect(page.getByText('Hilang Bahaya')).toBeVisible();
        const lebih = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(lebih).toBe(false);
        await page.screenshot({ path: `${SS}/5-mobile.png`, fullPage: true });
    });

    test('member ditolak', async ({ page }) => {
        await page.goto('/member/login');
        await page.getByRole('textbox', { name: /^Alamat email/ }).fill('pm-aktif@example.com');
        await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
        await page.getByRole('button', { name: /masuk/i }).click();
        await expect(page).toHaveURL(/\/member$/);
        const res = await page.goto('/admin/member-menghilang');
        expect(res?.status()).toBe(403);
    });
});
