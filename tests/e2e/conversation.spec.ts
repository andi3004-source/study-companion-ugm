import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Percakapan Ngobrol Tanpa Akun (W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Halaman picker percakapan /conversation memuat pilihan skenario dan tombol mulai', async ({ page }) => {
    await page.goto('/conversation');

    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Teman Ngobrol AI');

    // Logo beranda di header sebagai kontrol navigasi kembali
    const berandaLink = page.locator('header a#logo-beranda');
    await expect(berandaLink).toBeVisible();

    // Skenario percakapan tersedia
    const scenarioBtns = page.locator('button[data-voice-start]');
    expect(await scenarioBtns.count()).toBeGreaterThan(0);

    // Status region stabil untuk galat di dalam main
    const statusRegion = page.locator('main [role="status"]');
    await expect(statusRegion).toBeAttached();
  });

  test('Alur percakapan: mulai sesi -> kirim pesan -> tangani balasan/status -> akhiri sesi', async ({ page }) => {
    await page.goto('/conversation');

    // Mulai sesi dengan memilih skenario pertama
    const scenarioBtn = page.locator('button[data-voice-start]').first();
    await scenarioBtn.click();

    // Browser diarahkan ke sesi percakapan /conversation/{id}
    await page.waitForURL(/\/conversation\/\d+/);

    // Transkrip menggunakan role="log"
    const chatLog = page.locator('[role="log"]');
    await expect(chatLog).toBeVisible();

    // Status pengumuman stabil (role="status")
    const chatStatus = page.locator('#status-percakapan');
    await expect(chatStatus).toBeAttached();

    // Textarea pengetikan pesan
    const textarea = page.locator('#kolom-tulis');
    await expect(textarea).toBeVisible();

    // Kirim turn pengguna
    await textarea.fill('Halo!');
    const submitBtn = page.locator('form[wire\\:submit="sendMessage"] button[type="submit"]');
    await submitBtn.click();

    // Tidak ada autoplay audio pada percakapan
    const autoplayMedia = await page.locator('audio[autoplay]').count();
    expect(autoplayMedia).toBe(0);

    // Selesaikan sesi percakapan
    const selesaikanBtn = page.locator('button:has-text("Selesai dan analisis")');
    if (await selesaikanBtn.isVisible()) {
      await selesaikanBtn.click();
      const konfirmasiBtn = page.locator('button:has-text("Ya, selesaikan")');
      await expect(konfirmasiBtn).toBeVisible();
      await konfirmasiBtn.click();

      // Wilayah rangkuman atau status analisis tampil
      await expect(page.locator('text=/Sesi selesai|Analisis kesalahan/i').first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('Session ID browser lain tidak dapat diakses (404/redirect aman)', async ({ page }) => {
    // Akses ID sesi sembarang yang bukan milik cookie aktif
    const response = await page.goto('/conversation/999999');
    expect(response?.status()).toBe(404);
  });
});
