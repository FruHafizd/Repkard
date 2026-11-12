import { expect, Page, test } from '@playwright/test';
import { resetLoginThrottle } from './helpers';

const SS = 'test-results/pt-sesi';

test.beforeEach(() => resetLoginThrottle());

async function login(page: Page, panel: 'admin' | 'member', email: string) {
    await page.goto(`/${panel}/login`);
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill(email);
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('password');
    await page.getByRole('button', { name: /masuk/i }).click();
    await expect(page).toHaveURL(new RegExp(`/${panel}$`));
}

const modal = (page: Page) => page.locator('[role=dialog], [role=alertdialog]');

function besok10(): string {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T10:00`;
}

test.describe.serial('jadwal sesi PT', () => {
    test('pegawai menjadwalkan sesi untuk klien PT', async ({ page }) => {
        await login(page, 'admin', 'pegawai@repkard.test');
        await page.goto('/admin/pt-sessions/create');

        await page.getByRole('button', { name: /^Klien PT/ }).click();
        await page.getByRole('option', { name: /Uji Sesi — sisa 5 sesi/ }).click();
        await page.getByRole('textbox', { name: /^Tanggal & jam/ }).fill(besok10());
        await page.screenshot({ path: `${SS}/1-form-jadwal.png`, fullPage: true });
        await page.getByRole('button', { name: 'Buat', exact: true }).click();

        await expect(page).toHaveURL(/\/admin\/pt-sessions$/);
        const baris = page.getByRole('row').filter({ hasText: 'Uji Sesi' });
        await expect(baris).toContainText('Terjadwal');
        await page.screenshot({ path: `${SS}/2-daftar-pegawai.png`, fullPage: true });
    });

    test('trainer menandai sesi Selesai, komisi muncul di laporan', async ({ page }) => {
        await login(page, 'admin', 'trainer-sesi@repkard.test');
        await expect(page.getByRole('navigation').getByText('Sesi Saya')).toBeVisible();
        await page.goto('/admin/pt-sessions');

        const baris = page.getByRole('row').filter({ hasText: 'Uji Sesi' });
        await baris.getByRole('button', { name: 'Selesai' }).click();
        await modal(page).getByRole('button', { name: /^(Konfirmasi|Kirim)$/ }).click();
        await expect(page.getByText('Sesi ditandai Selesai')).toBeVisible();
        await expect(baris).toContainText('Selesai');
        await page.screenshot({ path: `${SS}/3-trainer-selesai.png`, fullPage: true });

        await page.goto('/admin/komisi-trainer');
        const komisi = page.getByRole('row').filter({ hasText: 'Trainer Sesi' });
        await expect(komisi).toContainText('Rp 50.000');
        await page.screenshot({ path: `${SS}/4-komisi-trainer.png`, fullPage: true });
    });

    test('pegawai tidak bisa membuka Komisi Trainer', async ({ page }) => {
        await login(page, 'admin', 'pegawai@repkard.test');
        expect((await page.goto('/admin/komisi-trainer'))?.status()).toBe(403);
    });

    test('member melihat sisa sesi berkurang dan riwayat', async ({ page }) => {
        await login(page, 'member', 'pt-sesi@example.com');
        await page.goto('/member/personal-trainer');
        await expect(page.getByText('4 dari 5')).toBeVisible();
        await expect(page.getByText('Riwayat sesi')).toBeVisible();
        await expect(page.getByText('Selesai').first()).toBeVisible();
        await page.screenshot({ path: `${SS}/5-member.png`, fullPage: true });
    });
});
