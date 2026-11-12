import { expect, Page, test } from '@playwright/test';
import fs from 'node:fs';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/panel-member';

test.beforeEach(() => resetLoginThrottle());

async function login(page: Page, email: string) {
    await page.goto('/member/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/member$/);
}

test.describe.serial('Panel member', () => {
    test('member aktif: dashboard, kartu, riwayat, profil', async ({ page }) => {
        await login(page, 'pm-aktif@example.com');

        // Dashboard
        await expect(page.getByText('Status membership')).toBeVisible();
        await expect(page.getByText('Aktif', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('Sisa kuota')).toBeVisible();
        await expect(page.getByText('Kehadiran bulan ini')).toBeVisible();
        await page.screenshot({ path: `${SS}/1-dashboard-aktif.png`, fullPage: true });

        // Kartu Saya
        await page.goto('/member/kartu-saya');
        await expect(page.getByAltText('QR kartu')).toBeVisible();
        await expect(page.getByText('RPK-2611-0801')).toBeVisible();
        const pdf = page.getByRole('link', { name: 'Unduh / Cetak PDF' });
        await expect(pdf).toBeVisible();
        await page.screenshot({ path: `${SS}/2-kartu-saya.png`, fullPage: true });
        const resp = await page.request.get((await pdf.getAttribute('href'))!);
        expect(resp.status()).toBe(200);
        expect(resp.headers()['content-type']).toContain('application/pdf');

        // Riwayat Kehadiran: 3 diterima; yang ditolak dan milik member lain tidak tampil
        await page.goto('/member/check-ins');
        await expect(page.getByRole('row')).toHaveCount(4); // header + 3
        await expect(page.getByText('Kuota habis')).toHaveCount(0);
        await page.screenshot({ path: `${SS}/3-riwayat-kehadiran.png`, fullPage: true });

        // Riwayat Pembayaran: hanya Rp 200.000, bukan Rp 777.000 milik member lain
        await page.goto('/member/payments');
        await expect(page.getByText('Rp 200.000')).toBeVisible();
        await expect(page.getByText('Rp 777.000')).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Kuitansi' })).toBeVisible();
        await page.screenshot({ path: `${SS}/4-riwayat-pembayaran.png`, fullPage: true });

        // Profil: nama/email read-only, ubah HP & alamat
        await page.goto('/member/profile');
        await expect(page.getByRole('textbox', { name: /^Nama/ }).first()).toBeDisabled();
        await expect(page.getByRole('textbox', { name: /^Email/ }).first()).toBeDisabled();
        await page.screenshot({ path: `${SS}/5-profil-sebelum.png`, fullPage: true });
        await page.getByRole('textbox', { name: /^Nomor HP/ }).fill('089912345678');
        await page.getByRole('textbox', { name: /^Alamat/ }).fill('Jl. Baru No. 99');
        await page.getByRole('button', { name: /simpan/i }).click();
        await expect(page.getByRole("heading", { name: "Disimpan" })).toBeVisible();
        await page.screenshot({ path: `${SS}/6-profil-tersimpan.png`, fullPage: true });
        await page.reload();
        await expect(page.getByRole('textbox', { name: /^Nomor HP/ })).toHaveValue('089912345678');
        await expect(page.getByRole('textbox', { name: /^Alamat/ })).toHaveValue('Jl. Baru No. 99');
        await expect(page.getByRole('textbox', { name: /^Nama/ }).first()).toHaveValue('Sari Aktif');
    });

    test('akses data member lain ditolak', async ({ page }) => {
        const ids = JSON.parse(fs.readFileSync('test-results/panel-member-ids.json', 'utf8'));
        await login(page, 'pm-aktif@example.com');
        expect((await page.request.get(`/pembayaran/${ids.paymentLain}/kuitansi`)).status()).toBe(403);
        expect((await page.request.get(`/kartu/${ids.memberLain}/pdf`)).status()).toBe(403);
        await page.goto(`/pembayaran/${ids.paymentLain}/kuitansi`);
        await page.screenshot({ path: `${SS}/7-kuitansi-member-lain-403.png` });
    });

    test('member dibekukan', async ({ page }) => {
        await login(page, 'pm-beku@example.com');
        await expect(page.getByText('Dibekukan')).toBeVisible();
        await page.screenshot({ path: `${SS}/8-dashboard-dibekukan.png`, fullPage: true });
    });

    test('member pending: kartu tanpa QR', async ({ page }) => {
        await login(page, 'pm-pending@example.com');
        await expect(page.getByText('Akun Anda menunggu aktivasi')).toBeVisible();
        await page.screenshot({ path: `${SS}/9-dashboard-pending.png`, fullPage: true });
        await page.goto('/member/kartu-saya');
        await expect(page.getByText('Kartu tersedia setelah akun diaktifkan')).toBeVisible();
        await expect(page.getByAltText('QR kartu')).toHaveCount(0);
        await page.screenshot({ path: `${SS}/10-kartu-pending.png`, fullPage: true });
    });
});
