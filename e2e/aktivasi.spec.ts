import { expect, Page, test } from '@playwright/test';

// Filament membatasi percobaan login per IP, jadi sesi dipakai ulang antar test.
const sesi = new Map<string, Awaited<ReturnType<ReturnType<Page['context']>['cookies']>>>();

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

test.describe.serial('aktivasi, perpanjang, bekukan, pembayaran', () => {
    test('pegawai mengaktifkan member dari antrean', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        await page.getByRole('button', { name: /Menunggu aktivasi/ }).click();

        const baris = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' });
        await expect(baris).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Calon Ditolak' })).toBeVisible();
        await baris.getByRole('button', { name: 'Aktifkan' }).click();

        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await expect(modal.getByText('Calon Aktivasi — 081300000001')).toBeVisible();
        await expect(modal.getByRole('img', { name: 'Foto member' })).toBeVisible();

        // Tanpa centang → ditolak.
        await modal.getByRole('button', { name: 'Aktifkan' }).click();
        await expect(modal.getByText(/Cocokkan wajah dengan foto/)).toBeVisible();

        await modal.getByLabel(/Saya sudah mencocokkan wajah/).check();
        await modal.getByRole('button', { name: 'Aktifkan' }).click();

        await expect(page.getByText('Member diaktifkan')).toBeVisible();
        await page.getByRole('button', { name: 'Semua' }).click();
        await expect(page.getByRole('row').filter({ hasText: 'Calon Aktivasi' })).toContainText(/RPK-\d{4}-0001/);
    });

    test('pegawai memperpanjang dan membekukan', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        const baris = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' });

        await baris.getByRole('button', { name: 'Tindakan' }).click(); // menu aksi lainnya
        await page.getByRole('menuitem', { name: 'Perpanjang' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await modal.getByRole('button', { name: 'Perpanjang' }).click();
        await expect(page.getByText('Membership diperpanjang')).toBeVisible();

        await baris.getByRole('button', { name: 'Tindakan' }).click();
        await page.getByRole('menuitem', { name: 'Bekukan' }).click();
        await page.getByRole('dialog').getByLabel(/Lama pembekuan/).fill('31');
        await page.getByRole('dialog').locator('button[type=submit]').click();
        await expect(page.getByRole('dialog').getByText(/tidak boleh lebih dari|maksimal/i).first()).toBeVisible();

        await page.getByRole('dialog').getByLabel(/Lama pembekuan/).fill('7');
        await page.getByRole('dialog').locator('button[type=submit]').click();
        await expect(page.getByText('Membership dibekukan')).toBeVisible();
    });

    test('pegawai melihat pembayarannya sendiri dan mengunduh kuitansi, tanpa tombol batalkan', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/payments');
        const baris = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' });
        await expect(baris).toHaveCount(2); // aktivasi + perpanjangan
        await expect(page.getByRole('button', { name: 'Batalkan' })).toHaveCount(0);

        const href = await baris.first().getByRole('link', { name: 'Kuitansi' }).getAttribute('href');
        const res = await page.request.get(href!);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('application/pdf');
    });

    test('owner membatalkan pembayaran', async ({ page }) => {
        await login(page, 'owner@repkard.test');
        await page.goto('/admin/payments');
        const baris = page.getByRole('row').filter({ hasText: 'Calon Aktivasi' }).first();
        await baris.getByRole('button', { name: 'Batalkan' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await modal.getByLabel(/Alasan pembatalan/).fill('Salah input');
        await modal.locator('button[type=submit]').click();
        await expect(page.getByText('Pembayaran dibatalkan')).toBeVisible();
        await expect(page.getByText('Dibatalkan', { exact: true })).toBeVisible();
    });

    test('pegawai menolak pendaftaran', async ({ page }) => {
        await login(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        await page.getByRole('button', { name: /Menunggu aktivasi/ }).click();
        const baris = page.getByRole('row').filter({ hasText: 'Calon Ditolak' });
        await baris.getByRole('button', { name: 'Tindakan' }).click();
        await page.getByRole('menuitem', { name: 'Tolak pendaftaran' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await modal.getByLabel(/Alasan penolakan/).fill('Foto palsu');
        await modal.locator('button[type=submit]').click();
        await expect(page.getByText('Pendaftaran ditolak dan dihapus')).toBeVisible();
        await expect(page.getByRole('row').filter({ hasText: 'Calon Ditolak' })).toHaveCount(0);
    });
});
