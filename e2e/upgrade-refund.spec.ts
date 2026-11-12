import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/upgrade-refund';

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

async function bukaMember(page: Page, nama: string) {
    await page.goto('/admin/members');
    await page.getByRole('searchbox', { name: 'Cari', exact: true }).fill(nama);
    const baris = page.getByRole('row').filter({ hasText: nama });
    await expect(baris).toHaveCount(1);
    await baris.getByRole('link', { name: 'Lihat' }).click();
    await expect(page).toHaveURL(/members\/\d+$/);
}

const modal = (page: Page) => page.locator('[role=dialog], [role=alertdialog]');

test.describe.serial('upgrade paket & refund', () => {
    test('pegawai upgrade ke 3 Bulan: rincian prorata benar', async ({ page }) => {
        const errors: string[] = [];
        page.on('response', (r) => {
            if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`);
        });
        await loginAdmin(page, 'pegawai@repkard.test');
        await bukaMember(page, 'Uji Upgrade');

        // Pegawai tidak melihat tombol Refund; Perpanjang berlabel baru.
        await expect(page.getByRole('button', { name: 'Refund' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Perpanjang / pindah paket' })).toBeVisible();

        await page.getByRole('button', { name: 'Upgrade paket' }).click();
        const m = modal(page);
        // Hanya paket lebih mahal dari Bulanan.
        const opsi = (await m.getByRole('combobox', { name: /^Paket baru/ }).locator('option').allTextContents()).join('|');
        expect(opsi).not.toContain('Harian');
        expect(opsi).not.toContain('Bulanan');
        await m.getByRole('combobox', { name: /^Paket baru/ }).selectOption({ label: '3 Bulan' });

        await expect(m.getByText(/Sisa nilai paket lama Rp 133\.333/)).toBeVisible();
        await expect(m.getByText(/harus dibayar Rp 416\.667/)).toBeVisible();
        await expect(m.getByRole('spinbutton', { name: /^Jumlah/ })).toHaveValue('416667');
        await page.screenshot({ path: `${SS}/1-modal-upgrade.png` });

        await m.getByRole('button', { name: 'Upgrade', exact: true }).click();
        await expect(page.getByText('Paket di-upgrade')).toBeVisible();

        await page.reload();
        await expect(page.getByText('Diupgrade ke 3 Bulan')).toBeVisible();
        await expect(page.getByText('Dibatalkan').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/2-riwayat.png`, fullPage: true });
        expect(errors).toEqual([]);
    });

    test('pembayaran upgrade tampil dengan jenis', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/payments');
        const baris = page.getByRole('row').filter({ hasText: 'Uji Upgrade' });
        await expect(baris.filter({ hasText: 'Rp 416.667' })).toContainText('Upgrade');
        await expect(baris.filter({ hasText: 'Pembelian' })).toHaveCount(1);
        await page.screenshot({ path: `${SS}/3-pembayaran.png`, fullPage: true });
    });

    test('pegawai tidak bisa membuka halaman refund (403)', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        expect((await page.goto('/admin/refunds'))?.status()).toBe(403);
    });

    test('owner refund: validasi, jumlah maksimal, lalu tercatat', async ({ page }) => {
        await loginAdmin(page, 'owner@repkard.test');
        await bukaMember(page, 'Uji Refund');
        await page.getByRole('button', { name: 'Refund' }).click();
        const m = modal(page);
        await expect(m.getByText(/Nilai membership Rp 200\.000/)).toBeVisible();
        await expect(m.getByText(/sisa nilai \(saran jumlah\) Rp 133\.333/)).toBeVisible();
        await expect(m.getByRole('spinbutton', { name: /^Jumlah refund/ })).toHaveValue('133333');

        // Alasan kosong ditahan validasi browser; tanpa centang ditolak server.
        await m.getByRole('button', { name: 'Refund', exact: true }).click();
        expect(await m.getByRole('textbox', { name: /^Alasan/ }).evaluate((el: HTMLTextAreaElement) => el.validity.valueMissing)).toBe(true);
        await m.getByRole('textbox', { name: /^Alasan/ }).fill('Pindah kota');
        await m.getByRole('button', { name: 'Refund', exact: true }).click();
        await expect(m.getByText('Centang setelah uang dikembalikan ke member.')).toBeVisible();

        // Melebihi nilai → ditolak service.
        await m.getByRole('spinbutton', { name: /^Jumlah refund/ }).fill('200001');
        await m.getByRole('textbox', { name: /^Alasan/ }).fill('Pindah kota');
        await m.getByLabel(/Uang sudah dikembalikan/).check();
        await m.getByRole('button', { name: 'Refund', exact: true }).click();
        await expect(page.getByText(/Jumlah refund harus antara/).first()).toBeVisible();
        await expect(page.getByText('Refund dicatat dan membership dibatalkan')).toHaveCount(0);

        // Modal tertutup setelah penolakan; buka lagi dan isi dengan jumlah yang sah.
        await page.getByRole('button', { name: 'Refund' }).click();
        await m.getByRole('spinbutton', { name: /^Jumlah refund/ }).fill('133333');
        await m.getByRole('textbox', { name: /^Alasan/ }).fill('Pindah kota');
        await m.getByLabel(/Uang sudah dikembalikan/).check();
        await page.screenshot({ path: `${SS}/4-modal-refund.png` });
        await m.getByRole('button', { name: 'Refund', exact: true }).click();
        await expect(page.getByText('Refund dicatat dan membership dibatalkan')).toBeVisible();

        await page.reload();
        await expect(page.getByText('Refund: Pindah kota')).toBeVisible();

        await page.goto('/admin/refunds');
        const baris = page.getByRole('row').filter({ hasText: 'Uji Refund' });
        await expect(baris).toContainText('Rp 133.333');
        await expect(baris).toContainText('Pindah kota');
        await page.screenshot({ path: `${SS}/5-daftar-refund.png`, fullPage: true });
    });

    test('dashboard owner: pemasukan hari ini dikurangi refund', async ({ page }) => {
        await loginAdmin(page, 'owner@repkard.test');
        // Hanya upgrade 416.667 yang dibayar hari ini (pembayaran seed 10 hari lalu), refund 133.333 → 283.334.
        await expect(page.getByText('Rp 283.334')).toBeVisible();
        await page.screenshot({ path: `${SS}/6-dashboard.png`, fullPage: true });
    });

    test('tampilan ponsel 390 px', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAdmin(page, 'owner@repkard.test');
        await page.goto('/admin/refunds');
        await expect(page.getByText('Rp 133.333')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.screenshot({ path: `${SS}/7-ponsel.png`, fullPage: true });
    });
});
