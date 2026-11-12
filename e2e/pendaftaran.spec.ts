import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import { resetLoginThrottle } from './helpers';

// PNG 1x1 valid; server memotong & memperbesar ke 3:4.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

test.beforeAll(() => fs.writeFileSync('storage/logs/laravel.log', ''));

test.beforeEach(() => resetLoginThrottle());

function linkVerifikasi(): string {
    const log = fs.readFileSync('storage/logs/laravel.log', 'utf8')
        .replace(/=\r?\n/g, '')
        .replace(/=3D/g, '=')
        .replace(/&amp;/g, '&');
    const links = [...log.matchAll(/http:\/\/127\.0\.0\.1:8001\/member\/email-verification\/verify\/[^\s"'<>]+/g)];
    expect(links.length, 'link verifikasi ada di log email').toBeGreaterThan(0);
    return links[links.length - 1][0];
}

test('landing: hanya paket aktif, tombol Daftar membawa paket', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Paket Membership' })).toBeVisible();
    await expect(page.getByText('Bulanan', { exact: true })).toBeVisible();
    await expect(page.getByText('Rp 200.000')).toBeVisible();

    await page.getByRole('article').filter({ hasText: '3 Bulan' }).getByRole('link', { name: 'Daftar' }).click();
    await expect(page).toHaveURL(/\/member\/register\?plan=\d+/);
    await expect(page.locator('select option:checked')).toHaveText('3 Bulan');
});

test('pendaftar: daftar → verifikasi email → lengkapi profil → menunggu aktivasi → ganti paket', async ({ page }) => {
    await page.goto('/member/register?plan=2');
    await page.getByRole('textbox', { name: /^Nama/ }).fill('Budi E2E');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill('budi-e2e@example.com');
    await page.getByRole('textbox', { name: /^Nomor HP/ }).fill('081234567890');
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('rahasia-e2e-123');
    await page.getByRole('textbox', { name: /^Konfirmasi kata sandi/ }).fill('rahasia-e2e-123');
    await page.getByRole('button', { name: 'Buat akun' }).click();

    // Belum verifikasi → halaman verifikasi; halaman lain tertutup.
    await expect(page).toHaveURL(/email-verification\/prompt/);
    await page.goto('/member');
    await expect(page).toHaveURL(/email-verification\/prompt/);

    await page.goto(linkVerifikasi());

    // Terverifikasi tanpa foto → Lengkapi Profil.
    await expect(page).toHaveURL(/lengkapi-profil/);
    await page.goto('/member');
    await expect(page).toHaveURL(/lengkapi-profil/);
    await expect(page.getByText('Foto ini akan dicocokkan dengan wajah Anda saat aktivasi di gym.')).toBeVisible();

    await page.getByRole('combobox', { name: /Jenis kelamin/ }).selectOption({ label: 'Laki-laki' });
    await page.locator('input[type=file]').setInputFiles({ name: 'wajah.png', mimeType: 'image/png', buffer: png });
    await expect(page.getByText(/upload complete|selesai|wajah\.png/i).first()).toBeVisible();
    await page.getByRole('button', { name: 'Simpan' }).click();

    // Dashboard: widget pending.
    await expect(page.getByText('Akun Anda menunggu aktivasi')).toBeVisible();
    await expect(page.getByText('Datang ke gym, tunjukkan wajah Anda ke petugas')).toBeVisible();

    // Ganti paket.
    await page.locator('select').selectOption({ label: '3 Bulan — Rp 550.000' });
    await page.getByRole('button', { name: 'Ganti paket' }).click();
    await expect(page.getByText('Paket diganti')).toBeVisible();
    await expect(page.getByText('Rp 550.000').first()).toBeVisible();

    // Profil lengkap tidak bisa dibuka lagi.
    await page.goto('/member/lengkapi-profil');
    await expect(page).toHaveURL(/\/member$/);
});

test('pegawai dengan password sementara wajib ganti password', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /^Alamat email/ }).fill('pegawai-baru@repkard.test');
    await page.getByRole('textbox', { name: /^Kata sandi/ }).fill('sementara123');
    await page.getByRole('button', { name: /masuk/i }).click();

    await expect(page).toHaveURL(/\/admin\/ganti-password/);
    await page.goto('/admin/members');
    await expect(page).toHaveURL(/\/admin\/ganti-password/);

    await page.getByRole('textbox', { name: /^Password lama/ }).fill('sementara123');
    await page.getByRole('textbox', { name: /^Password baru/ }).fill('passwordbaru-123');
    await page.getByRole('textbox', { name: /^Konfirmasi password baru/ }).fill('passwordbaru-123');
    await page.getByRole('button', { name: 'Simpan' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/members');
    await expect(page).toHaveURL(/\/admin\/members/);
});
