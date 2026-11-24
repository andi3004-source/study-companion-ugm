import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Ucapan / Cek Pelafalan Tanpa Akun (W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Halaman /pronunciation-check memuat struktur heading dan kontrol yang benar', async ({ page }) => {
    await page.goto('/pronunciation-check');

    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Cek Ucapan');

    // Kontrol awal: kembali ke Beranda di header
    const berandaLink = page.locator('header a#logo-beranda');
    await expect(berandaLink).toBeVisible();

    // Pilihan bahasa dan input pencarian kata
    const bahasaSelect = page.locator('#ucapan-bahasa');
    await expect(bahasaSelect).toBeVisible();

    const cariInput = page.locator('#ucapan-cari');
    await expect(cariInput).toBeVisible();
  });

  test('Pemberitahuan privasi muncul saat Rekam pertama kali, dan Batal tidak menyalakan mikrofon', async ({ page }) => {
    await page.goto('/pronunciation-check');
    await page.locator('#ucapan-cari').fill('beautiful');
    await page.locator('button[data-saran]').first().click();

    await page.locator('button:has-text("Rekam suara")').click();
    const dialog = page.locator('dialog[aria-labelledby="privasi-mikrofon-judul"]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Sebelum mikrofon dipakai');
    await expect(dialog).toContainText('24 jam');

    await dialog.locator('button:has-text("Batal")').click();
    await expect(dialog).toBeHidden();
    // Nama aksesibel tombol tetap "Rekam suara": mikrofon tidak menyala.
    await expect(page.getByRole('button', { name: 'Rekam suara' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Berhenti merekam' })).toHaveCount(0);
  });

  test('Alur cek pelafalan: cari kata -> pilih kata -> rekam -> cek -> hasil diumumkan lewat status stabil', async ({ page }) => {
    await page.goto('/pronunciation-check');

    // Cari kata bahasa Inggris yang ada di fixture kecil (beautiful)
    const inputKata = page.locator('#ucapan-cari');
    await inputKata.fill('beautiful');

    // Tunggu saran kata muncul
    const saranItem = page.locator('button[data-saran]').first();
    await expect(saranItem).toBeVisible({ timeout: 5000 });
    await saranItem.click();

    // Kata terpilih tampil
    await expect(page.locator('[data-kata-terpilih]')).toBeVisible();

    // Tombol Rekam suara
    const rekamBtn = page.locator('button:has-text("Rekam suara")');
    await expect(rekamBtn).toBeVisible();
    await rekamBtn.click();

    // Pertama kali: setujui pemberitahuan privasi mikrofon dulu.
    await page.locator('button:has-text("Saya mengerti, nyalakan mikrofon")').click();

    // Berhenti merekam
    const stopBtn = page.locator('button:has-text("Berhenti merekam")');
    await expect(stopBtn).toBeVisible();
    await stopBtn.click();

    // Hasil diumumkan pada role="status" stabil di dalam main
    const statusRegion = page.locator('main [role="status"]').last();
    await expect(statusRegion).toBeAttached();
  });
});
