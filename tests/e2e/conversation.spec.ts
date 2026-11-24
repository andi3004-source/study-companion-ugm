import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Percakapan Ngobrol Tanpa Akun (W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Halaman picker percakapan /conversation memuat pilihan skenario dan tombol mulai', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute /conversation tanpa login belum di-merge');

    await page.goto('/conversation');

    await expect(page).toHaveTitle(/.*Ngobrol.*Tuturnusa.*/);
    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Ngobrol');

    // Kontrol pertama di main adalah kembali ke Beranda
    const kembaliLink = page.locator('main a').first();
    await expect(kembaliLink).toHaveText(/.*Beranda.*/);

    // Tombol mulai sesi percakapan
    const mulaiBtn = page.locator('button:has-text("Mulai")');
    await expect(mulaiBtn).toBeVisible();

    // Status region stabil untuk galat
    const statusRegion = page.locator('[role="status"]');
    await expect(statusRegion).toBeAttached();
  });

  test('Alur percakapan: mulai sesi -> kirim pesan -> terima balasan -> akhiri sesi', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: endpoint AI anonim belum di-merge');

    await page.goto('/conversation');

    // Mulai sesi
    const mulaiBtn = page.locator('button:has-text("Mulai")').first();
    await mulaiBtn.click();

    // Browser diarahkan ke sesi percakapan /conversation/{id}
    await page.waitForURL(/\/conversation\/\d+/);

    // Transkrip menggunakan role="log"
    const chatLog = page.locator('[role="log"]');
    await expect(chatLog).toBeVisible();

    // Status pengumuman stabil (role="status")
    const chatStatus = page.locator('[role="status"]');
    await expect(chatStatus).toBeAttached();

    // Textarea pengetikan pesan
    const textarea = page.locator('textarea');
    await expect(textarea).toBeVisible();

    // Kirim turn pengguna
    await textarea.fill('Halo, selamat pagi!');
    const kirimBtn = page.locator('button:has-text("Kirim")');
    await kirimBtn.click();

    // Fokus kembali/bertahan di textarea setelah kirim
    await expect(textarea).toBeFocused({ timeout: 5000 });

    // Pesan muncul di transkrip
    await expect(chatLog).toContainText('Halo, selamat pagi!');

    // Tidak ada autoplay audio pada balasan AI
    const autoplayMedia = await page.locator('audio[autoplay]').count();
    expect(autoplayMedia).toBe(0);

    // Akhiri percakapan
    const akhiriBtn = page.locator('button:has-text("Akhiri"), button:has-text("Selesai")');
    if (await akhiriBtn.isVisible()) {
      await akhiriBtn.click();
      // Status analisis / akhir sesi diumumkan lewat role="status"
      await expect(chatStatus).toBeAttached();
    }
  });

  test('Session ID browser lain tidak dapat diakses (404/redirect aman)', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: proteksi session cookie anonim belum di-merge');

    // Akses ID sesi sembarang yang bukan milik cookie aktif
    const response = await page.goto('/conversation/999999');
    expect([404, 302]).toContain(response?.status());
  });
});
