import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

// Nama berawalan "zz" supaya jalan paling akhir: penjualan PT hari ini mengubah angka pemasukan di spec lain.
const SS = 'test-results/personal-trainer';

test.beforeEach(() => resetLoginThrottle());

async function login(page: Page, panel: 'admin' | 'member', email: string) {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(new RegExp(`/${panel}$`));
}

async function bukaMember(page: Page, nomor: string) {
    await page.goto('/admin/members');
    await page.getByRole('searchbox', { name: 'Cari', exact: true }).fill(nomor);
    const baris = page.getByRole('row').filter({ hasText: nomor });
    await expect(baris).toHaveCount(1);
    await baris.getByRole('link', { name: 'Lihat' }).click();
    await expect(page).toHaveURL(/members\/\d+$/);
}

const modal = (page: Page) => page.locator('[role=dialog], [role=alertdialog]');

function pantau5xx(page: Page) {
    const errors: string[] = [];
    page.on('response', (r) => {
        if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`);
    });
    return errors;
}

test.describe.serial('personal trainer', () => {
    test('owner: daftar pegawai memuat trainer, komisi bisa diubah, paket PT terlihat', async ({ page }) => {
        const errors = pantau5xx(page);
        await login(page, 'admin', 'owner@repkard.test');

        await page.goto('/admin/users');
        await expect(page.getByRole('row').filter({ hasText: 'Trainer Satu' })).toContainText('trainer');
        await page.screenshot({ path: `${SS}/1-daftar-pegawai.png`, fullPage: true });

        await page.getByRole('row').filter({ hasText: 'Trainer Satu' }).getByRole('link', { name: 'Ubah' }).click();
        await expect(page.getByRole('spinbutton', { name: /^Komisi per sesi/ })).toHaveValue('50000');
        await page.getByRole('spinbutton', { name: /^Komisi per sesi/ }).fill('60000');
        await page.screenshot({ path: `${SS}/2-edit-trainer.png`, fullPage: true });
        await page.getByRole('button', { name: 'Simpan', exact: true }).click();
        await expect(page.getByText('Data berhasil disimpan')).toBeVisible();
        await page.reload();
        await expect(page.getByRole('spinbutton', { name: /^Komisi per sesi/ })).toHaveValue('60000');

        await page.goto('/admin/pt-packages');
        await expect(page.getByRole('row').filter({ hasText: 'PT 10 Sesi' })).toContainText('Rp 1.500.000');
        await page.screenshot({ path: `${SS}/3-paket-pt.png`, fullPage: true });
        expect(errors).toEqual([]);
    });

    test('pegawai: paket PT read-only, tidak bisa membuat (403)', async ({ page }) => {
        await login(page, 'admin', 'pegawai@repkard.test');
        await page.goto('/admin/pt-packages');
        await expect(page.getByRole('row').filter({ hasText: 'PT 10 Sesi' })).toBeVisible();
        expect((await page.goto('/admin/pt-packages/create'))?.status()).toBe(403);
    });

    test('pegawai: member tanpa membership berlaku tidak ditawari jual PT', async ({ page }) => {
        await login(page, 'admin', 'pegawai@repkard.test');
        await bukaMember(page, 'RPK-2611-0982');
        await expect(page.getByRole('button', { name: 'Jual paket PT' })).toHaveCount(0);
    });

    test('pegawai menjual paket PT ke member aktif', async ({ page }) => {
        const errors = pantau5xx(page);
        await login(page, 'admin', 'pegawai@repkard.test');
        await bukaMember(page, 'RPK-2611-0981');

        await page.getByRole('button', { name: 'Jual paket PT' }).click();
        const m = modal(page);
        await m.getByRole('combobox', { name: /^Paket PT/ }).selectOption({ label: 'PT 10 Sesi' });
        await expect(m.getByRole('spinbutton', { name: /^Jumlah/ })).toHaveValue('1500000');
        await m.getByRole('combobox', { name: /^Trainer/ }).selectOption({ label: 'Trainer Satu' });
        await page.screenshot({ path: `${SS}/4-modal-jual.png` });
        await m.getByRole('button', { name: 'Jual', exact: true }).click();
        await expect(page.getByText('Paket PT terjual')).toBeVisible();

        await page.goto('/admin/payments');
        const baris = page.getByRole('row').filter({ hasText: 'Uji PT' }).filter({ hasText: 'Rp 1.500.000' }).first();
        await expect(baris).toContainText('Paket PT');
        await page.screenshot({ path: `${SS}/5-pembayaran.png`, fullPage: true });

        const href = await baris.getByRole('link', { name: 'Kuitansi' }).getAttribute('href');
        const res = await page.request.get(href!);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('application/pdf');

        await page.goto('/admin/pt-subscriptions');
        const klien = page.getByRole('row').filter({ hasText: 'Trainer Satu' });
        await expect(klien).toContainText('10 / 10');
        await expect(klien).toContainText('Aktif');
        await page.screenshot({ path: `${SS}/6-klien-pt-pegawai.png`, fullPage: true });
        expect(errors).toEqual([]);
    });

    test('trainer: hanya klien sendiri, menu lain tertutup', async ({ page }) => {
        const errors = pantau5xx(page);
        await login(page, 'admin', 'trainer1@repkard.test');

        const nav = page.getByRole('navigation');
        await expect(nav.getByText('Klien PT')).toBeVisible();
        await expect(nav.getByText('Paket PT')).toBeVisible();
        for (const label of ['Member', 'Pembayaran', 'Kasir', 'Scan Check-in', 'Pegawai']) {
            await expect(nav.getByRole('link', { name: label, exact: true })).toHaveCount(0);
        }
        await page.screenshot({ path: `${SS}/7-dashboard-trainer.png`, fullPage: true });

        await page.goto('/admin/pt-subscriptions');
        await expect(page.getByRole('row').filter({ hasText: 'Uji PT' })).toHaveCount(1);
        await expect(page.getByText('Uji PT Lain')).toHaveCount(0);
        await page.screenshot({ path: `${SS}/8-klien-trainer.png`, fullPage: true });

        // Klien trainer lain (subscription id 1) → 403.
        expect((await page.goto('/admin/pt-subscriptions/1'))?.status()).toBe(403);
        for (const url of ['/admin/members', '/admin/payments', '/admin/kasir', '/admin/scan-check-in', '/admin/users']) {
            const status = (await page.goto(url))?.status() ?? 0;
            expect(status, url).toBeGreaterThanOrEqual(403);
            expect(status, url).toBeLessThan(500);
        }
        expect(errors).toEqual([]);
    });

    test('owner: ganti trainer dan batalkan paket PT (alasan wajib)', async ({ page }) => {
        await login(page, 'admin', 'owner@repkard.test');
        await page.goto('/admin/pt-subscriptions');
        await page.getByRole('row').filter({ hasText: 'Uji PT Lain' }).getByRole('link', { name: 'Lihat' }).click();

        await page.getByRole('button', { name: 'Ganti trainer' }).click();
        const m = modal(page);
        await m.getByRole('combobox', { name: /^Trainer baru/ }).selectOption({ label: 'Trainer Satu' });
        await m.getByRole('button', { name: /^(Kirim|Konfirmasi)$/ }).click();
        expect(await m.getByRole('textbox', { name: /^Alasan/ }).evaluate((el: HTMLTextAreaElement) => el.validity.valueMissing)).toBe(true);
        await m.getByRole('textbox', { name: /^Alasan/ }).fill('Trainer Dua cuti');
        await m.getByRole('button', { name: /^(Kirim|Konfirmasi)$/ }).click();
        await expect(page.getByText('Trainer diganti')).toBeVisible();

        await page.getByRole('button', { name: 'Batalkan' }).click();
        await m.getByRole('textbox', { name: /^Alasan/ }).fill('Member pindah gym');
        await m.getByRole('button', { name: /^(Kirim|Konfirmasi)$/ }).click();
        await expect(page.getByText('Paket PT dibatalkan', { exact: true })).toBeVisible();
        await page.goto(page.url(), { waitUntil: 'networkidle' });
        await expect(page.getByText('Dibatalkan').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/9-dibatalkan.png`, fullPage: true });
    });

    test('member melihat paket PT miliknya di panel member', async ({ page }) => {
        await login(page, 'member', 'pt-1@example.com');
        await page.goto('/member/personal-trainer');
        await expect(page.getByText('PT 10 Sesi')).toBeVisible();
        await expect(page.getByText('Trainer Satu')).toBeVisible();
        await expect(page.getByText('10 dari 10')).toBeVisible();
        await page.screenshot({ path: `${SS}/10-member-ada.png`, fullPage: true });

        await page.goto('/member/payments');
        await expect(page.getByText('Paket PT: PT 10 Sesi (10 sesi) — Trainer Trainer Satu')).toBeVisible();
    });

    test('member tanpa paket PT: teks ajakan; ponsel 390 px', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'member', 'pt-2@example.com');
        await page.goto('/member/personal-trainer');
        await expect(page.getByText('Tanyakan paket personal trainer ke petugas.')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.screenshot({ path: `${SS}/11-member-kosong-ponsel.png`, fullPage: true });
    });
});
