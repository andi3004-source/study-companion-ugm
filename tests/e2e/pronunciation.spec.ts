import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Ucapan / Cek Pelafalan Tanpa Akun (W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Halaman /pronunciation-check memuat struktur heading dan kontrol yang benar', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute /pronunciation-check tanpa login belum di-merge');

    await page.goto('/pronunciation-check');

    await expect(page).toHaveTitle(/.*Ucapan.*Tuturnusa.*/);
    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Ucapan');

    // Kontrol awal: kembali ke Beranda
    const kembaliLink = page.locator('main a').first();
    await expect(kembaliLink).toHaveText(/.*Beranda.*/);

    // Bagian-bagian utama sesuai rancangan sistem
    const h2Headings = page.locator('h2');
    const texts = await h2Headings.allTextContents();
    expect(texts.some((t) => t.includes('kata') || t.includes('Rekam') || t.includes('Hasil'))).toBe(true);
  });

  test('Pemberitahuan privasi tampil sebelum penggunaan mikrofon pertama kali', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: dialog privasi rekaman belum di-merge');

    await page.goto('/pronunciation-check');

    // Cari pemberitahuan privasi audio / mic
    const privacyNotice = page.locator('text=/.*privasi.*suara|rekaman|mikrofon.*/i');
    await expect(privacyNotice.first()).toBeVisible();
  });

  test('Alur cek pelafalan: cari kata -> rekam -> cek -> hasil diumumkan lewat status stabil', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: flow cek ucapan tanpa akun belum di-merge');

    await page.goto('/pronunciation-check');

    // Pilih atau cari kata
    const inputKata = page.locator('input[type="search"], input[type="text"]').first();
    if (await inputKata.isVisible()) {
      await inputKata.fill('hello');
      await page.keyboard.press('Enter');
    }

    // Tombol Rekam
    const rekamBtn = page.locator('button:has-text("Rekam"), button[aria-label*="rekam" i]').first();
    await expect(rekamBtn).toBeVisible();
    await rekamBtn.click();

    // Berhenti merekam
    const stopBtn = page.locator('button:has-text("Berhenti"), button:has-text("Selesai")').first();
    if (await stopBtn.isVisible()) {
      await stopBtn.click();
    }

    // Tombol Cek
    const cekBtn = page.locator('button:has-text("Cek"), button:has-text("Periksa")').first();
    if (await cekBtn.isVisible()) {
      await cekBtn.click();
    }

    // Hasil diumumkan pada role="status" stabil
    const statusRegion = page.locator('[role="status"]');
    await expect(statusRegion).toBeAttached();
  });
});
