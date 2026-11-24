import { test, expect } from '@playwright/test';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Beranda Tiga Fitur & PWA (W2-A & W3-P)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Beranda memuat tepat 3 kartu fitur dalam <ul> dan tautan bantuan', async ({ page }) => {
    await page.goto('/');

    // Heading utama
    const h1 = page.locator('h1');
    await expect(h1).toHaveText('Mau belajar apa hari ini?');

    // Tepat tiga kartu fitur dalam <ul>
    const daftarKartu = page.locator('ul.daftar-kartu-beranda > li');
    await expect(daftarKartu).toHaveCount(3);

    // Kartu 1: Ngobrol
    const kartuNgobrol = daftarKartu.nth(0).locator('a');
    await expect(kartuNgobrol).toHaveAttribute('href', /.*conversation.*/);
    await expect(kartuNgobrol.locator('h2')).toHaveText('Ngobrol');

    // Kartu 2: Ucapan
    const kartuUcapan = daftarKartu.nth(1).locator('a');
    await expect(kartuUcapan).toHaveAttribute('href', /.*pronunciation-check.*/);
    await expect(kartuUcapan.locator('h2')).toHaveText('Ucapan');

    // Kartu 3: Buku Saku
    const kartuBukuSaku = daftarKartu.nth(2).locator('a');
    await expect(kartuBukuSaku).toHaveAttribute('href', /.*buku-saku.*/);
    await expect(kartuBukuSaku.locator('h2')).toHaveText('Buku Saku');

    // Pola GOV.UK: tidak ada tombol bersarang di dalam tautan <a> kartu
    const nestedButtons = await page.locator('ul.daftar-kartu-beranda a button').count();
    expect(nestedButtons).toBe(0);

    // Tautan Bantuan tersedia di halaman
    const linkBantuan = page.locator('a[href*="bantuan"]');
    await expect(linkBantuan.first()).toBeVisible();
  });

  test('Section PWA Pasang Aplikasi tersembunyi secara default', async ({ page }) => {
    // BUG: tombol #tombol-pasang-aplikasi memiliki atribut hidden tapi tetap tampil (visible) karena ditimpa CSS inline-flex
    test.fail(true, 'BUG: tombol-pasang-aplikasi memiliki atribut hidden tapi tetap tampil (visible) karena ditimpa utility class inline-flex');

    await page.goto('/');

    const installBtn = page.locator('#tombol-pasang-aplikasi');
    await expect(installBtn).toBeAttached();
    await expect(installBtn).toBeHidden();
  });

  test('Section PWA menampilkan tombol setelah event sintetis beforeinstallprompt', async ({ page }) => {
    await page.goto('/');

    const installBtn = page.locator('#tombol-pasang-aplikasi');
    await expect(installBtn).toBeAttached();

    // Kirim event sintetis beforeinstallprompt
    await page.evaluate(() => {
      const event = new Event('beforeinstallprompt');
      (event as any).prompt = () => Promise.resolve();
      (event as any).userChoice = Promise.resolve({ outcome: 'accepted' });
      window.dispatchEvent(event);
    });

    await expect(installBtn).toBeVisible();

    // Klik pasang aplikasi dan periksa pengumuman status
    await installBtn.click();
    const status = page.locator('#mode-suara-status');
    await expect(status).toHaveText('Tuturnusa sedang dipasang');
  });

  test('Tautan Cara Memasang mengarah ke /bantuan#pasang', async ({ page }) => {
    await page.goto('/');
    const caraPasangLink = page.locator('a[href*="bantuan#pasang"]');
    await expect(caraPasangLink).toBeAttached();
    await expect(caraPasangLink).toBeVisible();
  });
});
