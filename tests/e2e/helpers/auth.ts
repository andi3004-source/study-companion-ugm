import { Page } from '@playwright/test';

/**
 * Navigates to a target URL.
 * In pre-W2 state where routes still enforce auth middleware,
 * this detects redirection to /login and completes login with seeded credentials.
 * Once W2 is merged (routes without login), it proceeds without auth.
 */
export async function navigateTo(page: Page, url: string) {
  await page.goto(url);
  await page.waitForLoadState('domcontentloaded');

  if (page.url().includes('/login')) {
    const emailField = page.locator('input[name="email"], input[type="email"]');
    const passwordField = page.locator('input[name="password"], input[type="password"]');
    const submitBtn = page.locator('button[type="submit"]');

    if (await emailField.isVisible({ timeout: 2000 }).catch(() => false)) {
      await emailField.fill('test@example.com');
      await passwordField.fill('password');
      await submitBtn.click();
      await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 8000 }).catch(() => {});
      await page.waitForLoadState('domcontentloaded');

      if (!page.url().includes(url)) {
        await page.goto(url);
        await page.waitForLoadState('domcontentloaded');
      }
    }
  }
}
