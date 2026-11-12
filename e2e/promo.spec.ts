import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/promo';

test.beforeEach(() => resetLoginThrottle());

async function loginAdmin(page: Page, email: string) {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

test.describe.serial('promo & kode diskon', () => {
    test('owner membuat promo, kode ganda ditolak', async ({ page }) => {
        const errors: string[] = [];
        page.on('response', (r) => {
            if (r.status() >= 500) errors.push(`${r.status()} ${r.url()}`);
        });
        await loginAdmin(page, 'owner@repkard.test');
        await page.goto('/admin/promo-codes');
        await expect(page.getByText('E2E20', { exact: true })).toBeVisible();
        await expect(page.getByText('20%')).toBeVisible();

        await page.goto('/admin/promo-codes/create');
        await page.getByRole('textbox', { name: /^Kode/ }).fill('baru-50rb');
        await page.getByRole('textbox', { name: /^Nama promo/ }).fill('Promo Baru');
        await page.getByRole('combobox', { name: /^Jenis diskon/ }).selectOption('fixed');
        await page.getByRole('spinbutton', { name: /^Nilai diskon/ }).fill('50000');
        await page.screenshot({ path: `${SS}/1-form.png`, fullPage: true });
        await page.getByRole('button', { name: 'Buat', exact: true }).click();
        await expect(page).toHaveURL(/promo-codes\/\d+/);
        await expect(page.getByText('BARU-50RB')).toBeVisible();
        await expect(page.getByText('Rp 50.000')).toBeVisible();

        // Kode sama (beda huruf besar/kecil) ditolak.
        await page.goto('/admin/promo-codes/create');
        await page.getByRole('textbox', { name: /^Kode/ }).fill('e2e20');
        await page.getByRole('textbox', { name: /^Nama promo/ }).fill('Duplikat');
        await page.getByRole('spinbutton', { name: /^Nilai diskon/ }).fill('10');
        await page.getByRole('button', { name: 'Buat', exact: true }).click();
        await expect(page.getByText(/sudah (ada|dipakai)|has already been taken/i).first()).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('pegawai hanya melihat promo (403 untuk buat/ubah)', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/promo-codes');
        await expect(page.getByText('E2E20', { exact: true })).toBeVisible();
        expect((await page.goto('/admin/promo-codes/create'))?.status()).toBe(403);
        expect((await page.goto('/admin/promo-codes/1/edit'))?.status()).toBe(403);
    });

    test('pegawai mengaktifkan member: kode dari pendaftaran terisi, diskon tampil, bayar terisi', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        await page.getByRole('button', { name: /Menunggu aktivasi/ }).click();
        const baris = page.getByRole('row').filter({ hasText: 'Calon Promo' });
        await baris.getByRole('button', { name: 'Aktifkan' }).click();

        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await expect(modal.getByRole('textbox', { name: /^Kode promo/ })).toHaveValue('E2E20');
        await expect(modal.getByText(/Diskon Rp 5\.000 → bayar Rp 20\.000/)).toBeVisible();
        await expect(modal.getByRole('spinbutton', { name: /^Jumlah/ })).toHaveValue('20000');

        // Kode salah → pesan error.
        await modal.getByRole('textbox', { name: /^Kode promo/ }).fill('NGAWUR');
        await modal.getByRole('spinbutton', { name: /^Jumlah/ }).click(); // blur
        await expect(modal.getByText('Kode promo tidak ditemukan')).toBeVisible();

        await modal.getByRole('textbox', { name: /^Kode promo/ }).fill('e2e20');
        await modal.getByRole('spinbutton', { name: /^Jumlah/ }).click();
        await expect(modal.getByText(/bayar Rp 20\.000/)).toBeVisible();
        await modal.getByLabel(/Saya sudah mencocokkan wajah/).check();
        await page.screenshot({ path: `${SS}/2-modal-aktifkan.png` });
        await modal.getByRole('button', { name: 'Aktifkan' }).click();
        await expect(page.getByText('Member diaktifkan')).toBeVisible();
    });

    test('kuota habis menolak aktivasi', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/members');
        await page.getByRole('button', { name: /Menunggu aktivasi/ }).click();
        await page.getByRole('row').filter({ hasText: 'Calon Habis' }).getByRole('button', { name: 'Aktifkan' }).click();
        const modal = page.locator('[role=dialog], [role=alertdialog]');
        await expect(modal.getByText('Kuota kode promo sudah habis')).toBeVisible();

        await modal.getByLabel(/Saya sudah mencocokkan wajah/).check();
        await modal.getByRole('button', { name: 'Aktifkan' }).click();
        await expect(page.getByText('Kuota kode promo sudah habis').first()).toBeVisible();
        await expect(page.getByText('Member diaktifkan')).toHaveCount(0);
    });

    test('pembayaran & kuitansi menampilkan harga asli, diskon, kode', async ({ page }) => {
        await loginAdmin(page, 'pegawai@repkard.test');
        await page.goto('/admin/payments');
        const baris = page.getByRole('row').filter({ hasText: 'Calon Promo' });
        await expect(baris).toContainText('E2E20');
        await expect(baris).toContainText('Rp 5.000');
        await expect(baris).toContainText('Rp 20.000');
        await page.screenshot({ path: `${SS}/3-pembayaran.png`, fullPage: true });

        const href = await baris.getByRole('link', { name: 'Kuitansi' }).getAttribute('href');
        const res = await page.request.get(href!);
        expect(res.status()).toBe(200);
        expect(res.headers()['content-type']).toContain('application/pdf');

        await baris.getByRole('link', { name: 'Lihat' }).click();
        await expect(page.getByText('Rp 25.000')).toBeVisible();
        await expect(page.getByText('E2E20')).toBeVisible();
        await page.screenshot({ path: `${SS}/4-detail.png`, fullPage: true });
    });

    test('registrasi: kode salah ditolak, kode benar lolos', async ({ page }) => {
        await page.goto('/member/register?plan=1');
        const isi = async (kode: string) => {
            await page.getByRole('textbox', { name: /^Nama/ }).fill('Daftar Promo');
            await page.getByRole('textbox', { name: /^Alamat email/ }).fill('daftar-promo@example.com');
            await page.getByRole('textbox', { name: /^Nomor HP/ }).fill('081499999999');
            await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('rahasia-e2e-123');
            await page.getByRole('textbox', { name: /^Konfirmasi kata sandi/ }).fill('rahasia-e2e-123');
            await page.getByRole('textbox', { name: /^Kode promo/ }).fill(kode);
            await page.getByRole('button', { name: 'Buat akun' }).click();
        };

        await isi('NGAWUR');
        await expect(page.getByText('Kode promo tidak ditemukan')).toBeVisible();
        await expect(page).toHaveURL(/register/);
        await page.screenshot({ path: `${SS}/5-register-error.png`, fullPage: true });

        await isi('e2e20');
        await expect(page).toHaveURL(/email-verification\/prompt/);
    });
});
