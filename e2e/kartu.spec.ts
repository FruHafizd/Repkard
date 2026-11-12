import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/kartu';

// Filament membatasi percobaan login per IP, jadi sesi dipakai ulang antar test.
const sesi = new Map<string, Awaited<ReturnType<ReturnType<Page['context']>['cookies']>>>();

test.beforeEach(() => resetLoginThrottle());

async function login(page: Page, email: string, password = 'password') {
    const cookies = sesi.get(email);
    if (cookies) {
        await page.context().addCookies(cookies);
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/admin$/);
        return;
    }
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill(password);
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
    sesi.set(email, await page.context().cookies());
}

async function bukaMember(page: Page) {
    await page.goto('/admin/members');
    await page.getByRole('button', { name: 'Semua' }).click().catch(() => {});
    const baris = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' });
    await baris.getByRole('link', { name: 'Lihat' }).click();
    await expect(page.getByText('Riwayat kartu')).toBeVisible();
}

test.describe.serial('kartu member', () => {
    test('aktivasi otomatis menerbitkan kartu versi 1 dan PDF bisa diunduh', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        await page.getByRole('button', { name: /Menunggu aktivasi/ }).click();
        // Tunggu filter tab selesai (member aktif hilang dari daftar) sebelum menghitung baris.
        await expect(page.getByRole('row').filter({ hasText: 'Budi Valid' })).toHaveCount(0);

        const antre = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' });
        if (await antre.count()) {
            await antre.getByRole('button', { name: 'Aktifkan' }).click();
            const modal = page.locator('[role=dialog], [role=alertdialog]');
            await modal.getByLabel(/Saya sudah mencocokkan wajah/).check();
            await modal.getByRole('button', { name: 'Aktifkan' }).click();
            await expect(page.getByText('Member diaktifkan')).toBeVisible();
        }

        await bukaMember(page);
        await expect(page.getByText('Aktif', { exact: true }).first()).toBeVisible();
        await page.screenshot({ path: `${SS}/1-kartu-versi-1.png`, fullPage: true });

        const pdf = await page.request.get(
            await page.getByRole('link', { name: 'Cetak kartu' }).getAttribute('href') as string,
        );
        expect(pdf.status()).toBe(200);
        expect(pdf.headers()['content-type']).toContain('application/pdf');
        const body = await pdf.body();
        expect(body.subarray(0, 4).toString()).toBe('%PDF');
        const fs = await import('node:fs');
        fs.mkdirSync(SS, { recursive: true });
        fs.writeFileSync(`${SS}/kartu.pdf`, body);
    });

    test('pegawai menerbitkan ulang kartu, tidak punya tombol cabut', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await bukaMember(page);

        await expect(page.getByRole('button', { name: 'Cabut kartu' })).toHaveCount(0);

        await page.getByRole('button', { name: 'Terbitkan ulang kartu' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await modal.locator('button[type=submit]').click(); // alasan wajib
        await expect(modal.getByLabel(/Alasan/)).toBeVisible(); // modal tetap terbuka, tidak terkirim
        await modal.getByLabel(/Alasan/).fill('Kartu hilang');
        await page.screenshot({ path: `${SS}/2-form-terbit-ulang.png` });
        await modal.locator('button[type=submit]').click();

        await expect(page.getByText('Kartu baru diterbitkan. Kartu lama tidak berlaku lagi.')).toBeVisible();
        await page.screenshot({ path: `${SS}/3-setelah-terbit-ulang.png`, fullPage: true });
        await page.reload();
        await expect(page.getByText('Diganti kartu baru: Kartu hilang')).toBeVisible();
    });

    test('pegawai tidak bisa mengakses cabut kartu, owner bisa', async ({ page }) => {
        await login(page, 'owner@repkard.test');
        await bukaMember(page);

        await page.getByRole('button', { name: 'Cabut kartu' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await modal.getByLabel(/Alasan pencabutan/).fill('Disalahgunakan');
        await page.screenshot({ path: `${SS}/4-form-cabut-owner.png` });
        await modal.locator('button[type=submit]').click();

        await expect(page.getByText('Kartu dicabut')).toBeVisible();
        await page.reload();
        await expect(page.getByText('Disalahgunakan')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Cetak kartu' })).toHaveCount(0);
        await page.screenshot({ path: `${SS}/5-setelah-dicabut.png`, fullPage: true });

        // Tanpa kartu aktif, URL PDF langsung → 404.
        const url = page.url().replace(/\/admin\/members\/(\d+).*/, '/kartu/$1/pdf');
        expect((await page.request.get(url)).status()).toBe(404);
    });
});
