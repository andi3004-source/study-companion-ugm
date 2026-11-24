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

  test('Pemberitahuan privasi tampil sebelum penggunaan mikrofon pertama kali', async ({ page }) => {
    // BUG: Halaman /pronunciation-check tidak menampilkan pemberitahuan privasi sebelum mikrofon pertama kali digunakan
    test.fail(true, 'BUG: Halaman /pronunciation-check belum memuat pemberitahuan privasi mikrofon sesuai spesifikasi');

    await page.goto('/pronunciation-check');

    // Cari pemberitahuan privasi audio / mic sesuai spesifikasi rancangan sistem §5
    const privacyNotice = page.locator('text=/.*privasi.*(suara|rekaman|mikrofon).*/i');
    await expect(privacyNotice.first()).toBeVisible({ timeout: 2000 });
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

    // Berhenti merekam
    const stopBtn = page.locator('button:has-text("Berhenti merekam")');
    await expect(stopBtn).toBeVisible();
    await stopBtn.click();

    // Hasil diumumkan pada role="status" stabil di dalam main
    const statusRegion = page.locator('main [role="status"]').last();
    await expect(statusRegion).toBeAttached();
  });
});
