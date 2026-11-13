import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/absensi';

// Kamera palsu Chromium khusus file ini supaya swafoto bisa diambil tanpa perangkat nyata.
test.use({
    permissions: ['camera'],
    launchOptions: { args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] },
});

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

async function ambilFoto(page: Page) {
    await page.getByRole('button', { name: 'Nyalakan kamera' }).click();
    // Tunggu video siap supaya frame tidak kosong.
    await page.waitForFunction(() => (document.querySelector('video')?.videoWidth ?? 0) > 0);
    await page.getByRole('button', { name: 'Ambil foto' }).click();
    await expect(page.getByAltText('Hasil foto')).toBeVisible();
}

test.describe.serial('absensi pegawai', () => {
    test('pegawai absen masuk lalu pulang dengan swafoto', async ({ page }) => {
        await loginAdmin(page, 'pegawai-absen@repkard.test');
        await page.getByRole('link', { name: 'Absensi', exact: true }).click();
        await expect(page).toHaveURL(/\/admin\/absensi$/);

        const status = page.getByTestId('status-absensi');
        await expect(status).toContainText('Belum absen masuk');

        // Tombol nonaktif sampai foto diambil.
        const masuk = page.getByRole('button', { name: 'Absen Masuk' });
        await expect(masuk).toBeDisabled();
        await ambilFoto(page);
        await expect(masuk).toBeEnabled();
        await page.screenshot({ path: `${SS}/1-siap-masuk.png`, fullPage: true });

        await masuk.click();
        await expect(status).toContainText('Sudah absen masuk pukul');
        await expect(page.getByRole('button', { name: 'Absen Pulang' })).toBeDisabled();

        await ambilFoto(page);
        await page.getByRole('button', { name: 'Absen Pulang' }).click();
        await expect(status).toContainText('Sudah absen pulang pukul');
        await expect(page.getByRole('button', { name: 'Absen Pulang' })).toHaveCount(0);
        await page.screenshot({ path: `${SS}/2-sudah-pulang.png`, fullPage: true });
    });

    test('pegawai hanya melihat rekap dan foto miliknya', async ({ page }) => {
        await loginAdmin(page, 'pegawai-absen@repkard.test');
        await page.goto('/admin/staff-attendances');
        await expect(page.getByRole('heading', { name: 'Rekap Absensi' })).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Pegawai Absen' }).first()).toBeVisible();
        await expect(page.getByText('Pegawai Lain')).toHaveCount(0);

        // Absensi seed "Pegawai Lain" adalah id 1: foto miliknya tidak bisa dibuka pegawai lain.
        expect((await page.request.get('/media/absensi/1/masuk')).status()).toBe(403);
        // Absensi sendiri (id 2) bisa.
        expect((await page.request.get('/media/absensi/2/masuk')).status()).toBe(200);
    });

    test('owner melihat rekap semua orang, lupa pulang, dan bisa mengoreksi', async ({ page }) => {
        await loginAdmin(page, 'owner@repkard.test');
        await expect(page.getByRole('link', { name: 'Absensi', exact: true })).toHaveCount(0);
        await page.goto('/admin/absensi');
        await expect(page.getByText('403').first()).toBeVisible();

        await page.goto('/admin/staff-attendances');
        await expect(page.getByRole('row').filter({ hasText: 'Pegawai Lain' }).first()).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Pegawai Absen' }).first()).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Lupa pulang' }).first()).toBeVisible();
        await expect(page.getByTestId('ringkasan-absensi')).toContainText('Pegawai Lain');
        expect((await page.request.get('/media/absensi/1/masuk')).status()).toBe(200);
        await page.screenshot({ path: `${SS}/3-rekap-owner.png`, fullPage: true });

        // Koreksi tanpa alasan ditolak, dengan alasan berhasil.
        const barisLain = page.getByRole('row').filter({ hasText: 'Pegawai Lain' });
        await barisLain.getByRole('button', { name: 'Koreksi' }).click();
        const modal = page.getByRole('dialog', { name: 'Koreksi absensi' });
        await modal.getByRole('button', { name: 'Simpan koreksi' }).click();
        await expect(modal.getByRole('heading', { name: 'Koreksi absensi' })).toBeVisible();
        await expect(page.getByText('Absensi dikoreksi')).toHaveCount(0);
        await modal.getByRole('textbox', { name: /^Alasan koreksi/ }).fill('Lupa absen pulang, sudah dikonfirmasi');
        await modal.getByRole('button', { name: 'Simpan koreksi' }).click();
        await expect(page.getByText('Absensi dikoreksi')).toBeVisible();
    });

    test('tampilan responsif di lebar 390 px', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAdmin(page, 'pegawai-lain@repkard.test');
        await page.goto('/admin/absensi');
        await expect(page.getByRole('button', { name: 'Nyalakan kamera' })).toBeVisible();
        const lebar = await page.evaluate(() => document.documentElement.scrollWidth);
        expect(lebar).toBeLessThanOrEqual(391);
        await page.screenshot({ path: `${SS}/4-mobile.png`, fullPage: true });
    });
});
