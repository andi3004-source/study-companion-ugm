import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Buku Saku (W1-A & W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Katalog /buku-saku menampilkan dua buku dan tautan bantuan', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute /buku-saku belum di-merge');

    await page.goto('/buku-saku');

    await expect(page).toHaveTitle(/.*Buku Saku.*Tuturnusa.*/);
    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Buku Saku');

    // Dua buku yang tersedia
    const bukuMandiri = page.locator('a[href*="buku-saku-mandiri"]');
    const bukuSenam = page.locator('a[href*="senam-dasar"]');
    await expect(bukuMandiri).toBeVisible();
    await expect(bukuSenam).toBeVisible();

    // Tautan kembali ke Beranda
    const kembaliLink = page.locator('main a').first();
    await expect(kembaliLink).toHaveText(/.*Beranda.*/);
  });

  test('Daftar isi buku /buku-saku/{buku} memuat bab dalam urutan nomor', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute daftar bab buku belum di-merge');

    await page.goto('/buku-saku/buku-saku-mandiri');

    const h1 = page.locator('h1');
    await expect(h1).toContainText(/Buku Saku Mandiri/i);

    // Bab tersusun rapi dengan tautan bernomor
    const babLinks = page.locator('a[href*="/buku-saku/buku-saku-mandiri/"]');
    expect(await babLinks.count()).toBeGreaterThan(0);
  });

  test('Halaman bab /buku-saku/{buku}/{bab} memuat kontrol audio manual tanpa autoplay', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: rute bab buku saku belum di-merge');

    await page.goto('/buku-saku/buku-saku-mandiri/bab-1');

    // Title dan heading
    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Kalimat penjelasan pilihan mendengar narasi atau pembaca layar
    const panduanTeks = page.locator('text=/.*Dengarkan narasi atau baca teks dengan pembaca layar.*/i');
    await expect(panduanTeks.first()).toBeVisible();

    // Audio element TANPA autoplay dan preload none
    const audio = page.locator('audio');
    await expect(audio).toBeAttached();
    await expect(audio).toHaveAttribute('preload', 'none');
    await expect(audio).not.toHaveAttribute('autoplay');

    // Kontrol tombol: Putar/Jeda narasi
    const putarBtn = page.locator('button:has-text("Putar narasi"), button[aria-label*="Putar narasi" i]').first();
    await expect(putarBtn).toBeVisible();

    // Tautan navigasi bab & unduh MP3
    const kembaliDaftarIsi = page.locator('a:has-text("Kembali ke daftar isi"), a:has-text("Daftar isi")');
    await expect(kembaliDaftarIsi.first()).toBeVisible();

    const unduhMp3 = page.locator('a[href$=".mp3"]');
    await expect(unduhMp3.first()).toBeAttached();
  });

  test('Slug buku atau bab yang tidak valid / traversal menghasilkan 404', async ({ page }) => {
    // aktif setelah W2
    test.fixme(true, 'aktif setelah W2: validasi rute buku saku belum di-merge');

    const resBukuAsing = await page.goto('/buku-saku/buku-tidak-ada');
    expect(resBukuAsing?.status()).toBe(404);

    const resTraversal = await page.goto('/buku-saku/../routes/web.php');
    expect([404, 400]).toContain(resTraversal?.status());
  });
});
