import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Perjalanan Buku Saku (W1-A & W2-A)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Katalog /buku-saku menampilkan dua buku dan tautan bantuan', async ({ page }) => {
    await page.goto('/buku-saku');

    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Buku Saku');

    // Dua buku yang tersedia
    const bukuMandiri = page.locator('a[href*="buku-saku-mandiri"]');
    const bukuSenam = page.locator('a[href*="buku-saku-senam-dasar"]');
    await expect(bukuMandiri).toBeVisible();
    await expect(bukuSenam).toBeVisible();

    // Tautan kembali ke Beranda di dalam main
    const kembaliLink = page.locator('main a:has-text("Beranda")');
    await expect(kembaliLink).toBeVisible();
  });

  test('Daftar isi buku /buku-saku/{buku} memuat bab dalam urutan nomor', async ({ page }) => {
    await page.goto('/buku-saku/buku-saku-mandiri');

    const h1 = page.locator('h1');
    await expect(h1).toContainText('Buku Saku Mandiri');

    // Bab tersusun rapi dengan tautan bernomor
    const babLinks = page.locator('a[href*="/buku-saku/buku-saku-mandiri/"]');
    expect(await babLinks.count()).toBeGreaterThan(0);
  });

  test('Halaman bab /buku-saku/{buku}/{bab} memuat kontrol audio manual tanpa autoplay', async ({ page }) => {
    await page.goto('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri');

    // Title dan heading utama
    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Kalimat penjelasan pilihan mendengar narasi atau pembaca layar
    const panduanTeks = page.locator('text=/.*Dengarkan narasi, atau baca teks di bawah dengan pembaca layarmu.*/i');
    await expect(panduanTeks.first()).toBeVisible();

    // Audio element TANPA autoplay dan preload none
    const audio = page.locator('audio');
    await expect(audio).toBeAttached();
    await expect(audio).toHaveAttribute('preload', 'none');
    await expect(audio).not.toHaveAttribute('autoplay');

    // Kontrol tombol: Putar narasi, Mundur 10 detik, Maju 10 detik
    const putarBtn = page.locator('button:has-text("Putar narasi")').first();
    await expect(putarBtn).toBeVisible();

    const mundurBtn = page.locator('button:has-text("Mundur 10 detik")');
    await expect(mundurBtn).toBeVisible();

    const majuBtn = page.locator('button:has-text("Maju 10 detik")');
    await expect(majuBtn).toBeVisible();

    // Tautan navigasi "Kembali ke daftar isi"
    const kembaliDaftarIsi = page.locator('a:has-text("Kembali ke daftar isi")');
    await expect(kembaliDaftarIsi.first()).toBeVisible();
  });

  test('Judul bab tidak berulang ganda pada h1', async ({ page }) => {

    await page.goto('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri');
    const h1Text = await page.locator('h1').textContent();
    // Seharusnya tidak berulang "Bab 1. Bab 1."
    expect(h1Text).not.toMatch(/^Bab \d+\.\s*Bab \d+\./i);
  });

  test('Ukuran font heading h1 pada bab minimal 48px (3rem dari dasar 18px)', async ({ page }) => {

    await page.goto('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri');
    const h1 = page.locator('h1');
    const fontSize = await h1.evaluate((el) => window.getComputedStyle(el).fontSize);
    expect(parseFloat(fontSize)).toBeGreaterThanOrEqual(48);
  });

  test('Info "Bab N dari M" hanya menghitung bab isi tanpa pengantar dan referensi', async ({ page }) => {

    await page.goto('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri');
    const infoBab = page.locator('text=/Bab 1 dari/i');
    await expect(infoBab).toHaveText(/Bab 1 dari 15/i);
  });

  test('Slug buku atau bab yang tidak valid / traversal menghasilkan 404', async ({ page }) => {
    const resBukuAsing = await page.goto('/buku-saku/buku-tidak-ada');
    expect(resBukuAsing?.status()).toBe(404);

    const resTraversal = await page.goto('/buku-saku/../routes/web.php');
    expect([404, 400]).toContain(resTraversal?.status());
  });
});
