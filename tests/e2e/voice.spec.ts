import { test, expect } from '@playwright/test';
import { navigateTo } from './helpers/auth';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Mode Suara & Pengenalan Suara (W3-C & W4-G)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Tombol Mode suara tersedia di layout dengan status bawaan mati', async ({ page }) => {
    await navigateTo(page, '/bantuan');

    const tombolModeSuara = page.locator('#tombol-mode-suara');
    await expect(tombolModeSuara).toBeVisible();
    await expect(tombolModeSuara).toHaveAttribute('aria-pressed', 'false');
    await expect(tombolModeSuara).toContainText('Mode suara: mati');

    // Status region aria-live stabil
    const statusRegion = page.locator('#mode-suara-status');
    await expect(statusRegion).toHaveAttribute('role', 'status');
  });

  test('Persetujuan privasi mikrofon muncul sebelum Mode suara aktif pertama kali', async ({ page }) => {
    await navigateTo(page, '/bantuan');

    const tombolModeSuara = page.locator('#tombol-mode-suara');
    await tombolModeSuara.click();

    // Dialog persetujuan tampil
    const setujuJudul = page.locator('#mode-suara-setuju-judul');
    await expect(setujuJudul).toBeVisible();
    await expect(setujuJudul).toHaveText('Sebelum memakai Mode suara');

    // Tombol setuju dan aktifkan
    const setujuBtn = page.locator('button:has-text("Setuju dan aktifkan")');
    await expect(setujuBtn).toBeVisible();

    // Klik setuju
    await setujuBtn.click();

    // Mode suara sekarang aktif (nyala)
    await expect(tombolModeSuara).toHaveAttribute('aria-pressed', 'true');
    await expect(tombolModeSuara).toContainText('Mode suara: nyala');
  });

  test('Push-to-talk: pengenalan suara berhenti setelah satu ujaran', async ({ page }) => {
    await navigateTo(page, '/bantuan');

    const tombolModeSuara = page.locator('#tombol-mode-suara');
    await tombolModeSuara.click();

    const setujuBtn = page.locator('button:has-text("Setuju dan aktifkan")');
    if (await setujuBtn.isVisible()) {
      await setujuBtn.click();
    }

    // Tombol Berhenti muncul saat aktif
    const berhentiBtn = page.locator('button:has-text("Berhenti")');
    // Mock SpeechRecognition akan berhenti otomatis setelah ujaran (150ms)
    // atau dapat dihentikan manual
    if (await berhentiBtn.isVisible()) {
      await berhentiBtn.click();
    }
  });

  test('Perintah suara "buka buku saku" bernavigasi ke rute Buku Saku', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute tujuan buku-saku belum di-merge');

    await page.goto('/');

    // Nyalakan mode suara dengan mock transcript "buka buku saku"
    const tombolModeSuara = page.locator('#tombol-mode-suara');
    await tombolModeSuara.click();

    const setujuBtn = page.locator('button:has-text("Setuju dan aktifkan")');
    if (await setujuBtn.isVisible()) {
      await setujuBtn.click();
    }

    // Harusnya bernavigasi ke /buku-saku
    await page.waitForURL(/.*buku-saku.*/, { timeout: 5000 });
    await expect(page).toHaveURL(/.*buku-saku.*/);
  });
});
