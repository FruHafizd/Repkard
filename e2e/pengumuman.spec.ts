import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/pengumuman';

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

async function loginMember(page: Page, email: string) {
    await page.goto('/member/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/member$/);
}

test.describe('Pengumuman', () => {
    test('landing: hanya pengumuman show_on_landing yang sedang tayang', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('heading', { name: 'Pengumuman', exact: true })).toBeVisible();
        await expect(page.getByText('Gym tutup saat Lebaran')).toBeVisible();
        await expect(page.getByText('Alat baru datang')).toBeVisible();
        await expect(page.getByText('Belum tayang nanti')).toHaveCount(0);
        await expect(page.getByText('Pengumuman lama')).toHaveCount(0);
        await page.locator('section[aria-labelledby="judul-pengumuman"]').screenshot({ path: `${SS}/1-landing.png` });
    });

    test('member: dashboard widget, halaman, dan isi ter-escape', async ({ page }) => {
        let dialogs = 0;
        page.on('dialog', async (d) => {
            dialogs++;
            await d.dismiss();
        });

        await loginMember(page, 'pm-aktif@example.com');

        // Widget dimuat lazy oleh Filament; pengumuman disematkan harus tampil.
        await expect(page.getByText('Gym tutup saat Lebaran').first()).toBeVisible({ timeout: 15_000 });
        await page.screenshot({ path: `${SS}/2-dashboard-member.png`, fullPage: true });

        await page.goto('/member/announcements');
        await expect(page.getByText('Gym tutup saat Lebaran')).toBeVisible();
        await expect(page.getByText('Pengumuman lama')).toHaveCount(0);
        await expect(page.getByText('Belum tayang nanti')).toHaveCount(0);

        // Isi berbahaya tampil sebagai teks, tidak dijalankan.
        await expect(page.getByText('<script>alert(1)</script>')).toBeVisible();
        expect(dialogs).toBe(0);

        // Member tidak boleh membuka resource pengumuman di admin.
        const resp = await page.goto('/admin/announcements');
        expect(resp?.status()).toBe(403);

        await page.goto('/member/announcements');
        await page.screenshot({ path: `${SS}/3-halaman-member.png`, fullPage: true });
    });

    test('owner: buat pengumuman lewat form, lalu hapus', async ({ page }) => {
        await loginAdmin(page, 'owner@repkard.test');
        await page.goto('/admin/announcements');
        await expect(page.getByText('Gym tutup saat Lebaran')).toBeVisible();
        await page.screenshot({ path: `${SS}/4-admin-daftar.png`, fullPage: true });

        await page.goto('/admin/announcements/create');
        await page.getByLabel('Judul').fill('Jam libur E2E');
        await page.getByLabel('Isi').fill('Baris satu\nBaris dua');
        await page.getByRole('button', { name: /^buat$/i }).first().click();

        await expect(page).toHaveURL(/\/admin\/announcements\/\d+$/);
        await expect(page.getByText('Jam libur E2E')).toBeVisible();

        // Hapus lewat halaman edit (owner boleh menghapus).
        await page.goto(page.url() + '/edit');
        await page.getByRole('button', { name: /^hapus$/i }).first().click();
        await page.getByRole('alertdialog').getByRole('button', { name: /^hapus$/i }).click();
        await expect(page).toHaveURL(/\/admin\/announcements$/);
        await expect(page.getByText('Jam libur E2E')).toHaveCount(0);
    });

    test('pegawai: buat & edit milik sendiri, 403 untuk milik owner, tidak bisa hapus', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');

        await page.goto('/admin/announcements/create');
        await page.getByLabel('Judul').fill('Dari pegawai E2E');
        await page.getByLabel('Isi').fill('Isi dari pegawai.');
        await page.getByRole('button', { name: /^buat$/i }).first().click();
        await expect(page).toHaveURL(/\/admin\/announcements\/\d+$/);
        const ownUrl = page.url();

        // Edit milik sendiri: boleh, dan tidak ada tombol hapus.
        await page.goto(ownUrl + '/edit');
        await expect(page.getByRole('button', { name: /^simpan/i }).first()).toBeVisible();
        await expect(page.getByRole('button', { name: /^hapus$/i })).toHaveCount(0);

        // Milik owner (id 1 dari seed, dibuat pertama): ditolak 403.
        const lain = await page.goto('/admin/announcements/1/edit');
        expect(lain?.status()).toBe(403);
    });
});
