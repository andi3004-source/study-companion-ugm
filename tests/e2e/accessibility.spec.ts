import { test, expect } from '@playwright/test';
import { checkA11y } from './helpers/axe';
import { injectBrowserMocks } from './helpers/browser-mocks';

test.describe('Aksesibilitas Global & Layout (WCAG 2.1 / 2.2 AA)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Halaman /bantuan memenuhi standar WCAG AA dengan axe-core', async ({ page }) => {
    await page.goto('/bantuan');
    await checkA11y(page, '/bantuan');
  });

  test('Skip link adalah elemen fokus pertama dan melompat ke konten utama', async ({ page }) => {
    await page.goto('/bantuan');

    // Tab once to focus the skip link
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      return {
        tag: el?.tagName,
        href: el?.getAttribute('href'),
        text: el?.textContent?.trim(),
      };
    });

    expect(focused.href).toBe('#konten');
    expect(focused.text).toContain('Lewati ke konten');

    // Press Enter to follow skip link
    await page.keyboard.press('Enter');
    const mainHasFocus = await page.evaluate(() => {
      const main = document.getElementById('konten');
      return document.activeElement === main;
    });
    expect(mainHasFocus).toBe(true);
  });

  test('Landmark struktural <header>, <main id="konten">, dan <footer> tersedia', async ({ page }) => {
    await page.goto('/bantuan');

    const header = page.locator('header');
    const main = page.locator('main#konten');
    const footer = page.locator('footer');

    await expect(header).toBeAttached();
    await expect(main).toBeAttached();
    await expect(footer).toBeAttached();
  });

  test('Meta color-scheme only light terpasang dan latar body putih', async ({ page }) => {
    await page.goto('/bantuan');

    const metaColorScheme = page.locator('meta[name="color-scheme"]');
    await expect(metaColorScheme).toHaveAttribute('content', 'only light');

    const bodyBg = await page.evaluate(() => {
      const style = window.getComputedStyle(document.body);
      return style.backgroundColor;
    });
    expect(bodyBg).toBe('rgb(255, 255, 255)');
  });

  test('Tidak ada media audio yang autoplay pada halaman', async ({ page }) => {
    await page.goto('/bantuan');

    const autoplayAudio = await page.locator('audio[autoplay]').count();
    expect(autoplayAudio).toBe(0);
  });

  test('Reflow dan responsivitas pada viewport 1440, 390, dan 320 tanpa scroll horizontal', async ({ page }) => {
    await page.goto('/bantuan');

    const viewports = [
      { width: 1440, height: 900 },
      { width: 390, height: 844 },
      { width: 320, height: 568 },
    ];

    for (const vp of viewports) {
      await page.setViewportSize(vp);
      await page.waitForTimeout(100);

      const hasHorizontalScroll = await page.evaluate(() => {
        return document.documentElement.scrollWidth > document.documentElement.clientWidth;
      });
      expect(hasHorizontalScroll, `Horizontal scroll detected on viewport ${vp.width}x${vp.height}`).toBe(false);
    }
  });

  test('Mode forced-colors (tema kontras tinggi OS) didukung', async ({ page }) => {
    await page.emulateMedia({ forcedColors: 'active', colorScheme: 'light' });
    await page.goto('/bantuan');

    // In forced-colors, the operating system overrides foreground and background colors.
    await checkA11y(page, '/bantuan (forced-colors)', { disabledRules: ['color-contrast'] });
  });
});

test.describe('Aksesibilitas Halaman Fitur Final (W2)', () => {
  test.beforeEach(async ({ page }) => {
    await injectBrowserMocks(page);
  });

  test('Beranda 3 kartu / memenuhi standar axe', async ({ page }) => {
    await page.goto('/');
    await checkA11y(page, 'Beranda 3 kartu');
  });

  test('Picker Ngobrol /conversation memenuhi standar axe', async ({ page }) => {
    await page.goto('/conversation');
    await checkA11y(page, 'Picker Ngobrol');
  });

  test('Sesi Ngobrol /conversation/{session} memenuhi standar axe', async ({ page }) => {
    await page.goto('/conversation');
    // Start session
    const scenarioBtn = page.locator('button[data-voice-start]').first();
    await scenarioBtn.click();
    await page.waitForURL(/\/conversation\/\d+/);

    await checkA11y(page, 'Sesi Ngobrol');
  });

  test('Ucapan /pronunciation-check memenuhi standar axe', async ({ page }) => {
    await page.goto('/pronunciation-check');
    await checkA11y(page, 'Ucapan');
  });

  test('Katalog Buku Saku /buku-saku memenuhi standar axe', async ({ page }) => {
    await page.goto('/buku-saku');
    await checkA11y(page, 'Katalog Buku Saku');
  });

  test('Bab Buku Saku /buku-saku/{buku}/{bab} memenuhi standar axe', async ({ page }) => {
    await page.goto('/buku-saku/buku-saku-mandiri/01-cara-menyikat-gigi-mandiri');
    await checkA11y(page, 'Bab Buku Saku');
  });
});
