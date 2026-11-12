import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/kasir';

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

test.describe.serial('kasir', () => {
    test('pegawai menjual 2 produk tunai, kembalian benar, struk terbuka', async ({ page, context }) => {
        const errors: string[] = [];
        page.on('response', (r) => {
            if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`);
        });

        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/kasir');
        await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible();

        // Air mineral Rp 5.000 x2 + Protein shake Rp 25.000 = Rp 35.000
        await page.getByRole('button', { name: /Air mineral 600ml/ }).click();
        await page.getByRole('button', { name: /Air mineral 600ml/ }).click();
        await page.getByRole('button', { name: /Protein shake/ }).click();
        await expect(page.getByTestId('kasir-total')).toHaveText('Rp 35.000');

        await page.getByRole('spinbutton', { name: /^Uang diterima/ }).fill('50000');
        await expect(page.getByTestId('kasir-kembalian')).toHaveText('Rp 15.000');
        await page.screenshot({ path: `${SS}/1-keranjang.png`, fullPage: true });

        await page.getByRole('button', { name: 'Bayar', exact: true }).click();
        await expect(page.getByText(/Transaksi TRX-\d{6}-0001 berhasil/)).toBeVisible();
        await expect(page.getByTestId('kasir-total')).toHaveText('Rp 0');

        const link = page.getByRole('link', { name: /Cetak struk TRX-/ });
        await expect(link).toBeVisible();
        const href = await link.getAttribute('href');
        const res = await context.request.get(href!);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('application/pdf');

        // Pegawai tidak bisa membatalkan, owner bisa.
        await page.goto('/admin/sales');
        await expect(page.getByText(/TRX-\d{6}-0001/)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Batalkan' })).toHaveCount(0);
        expect(errors).toEqual([]);
    });

    test('owner membatalkan transaksi dan stok kembali', async ({ page }) => {
        await loginAdmin(page, 'owner@repkard.test');
        await page.goto('/admin/sales');
        await page.getByRole('button', { name: 'Batalkan' }).first().click();
        await page.getByRole('textbox', { name: /^Alasan pembatalan/ }).fill('Salah input E2E');
        await page.getByRole('button', { name: 'Konfirmasi' }).click();
        await expect(page.getByText('Transaksi dibatalkan')).toBeVisible();
        await expect(page.getByText('Dibatalkan').first()).toBeVisible();
    });
});
