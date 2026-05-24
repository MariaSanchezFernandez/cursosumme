import { test, expect } from '@playwright/test';

const ALUMNO_EMAIL = process.env.TEST_ALUMNO_EMAIL;
const ALUMNO_PASS  = process.env.TEST_ALUMNO_PASS;
const ADMIN_EMAIL  = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS   = process.env.TEST_ADMIN_PASS;

test.describe('Login', () => {
  test('credenciales incorrectas muestran mensaje de error', async ({ page }) => {
    await page.goto('/');
    await page.locator('#email').fill('noexiste@test.test');
    await page.locator('#password').fill('incorrecta123');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('#login-error')).toBeVisible();
  });

  test('alumno entra y llega a /inicio', async ({ page }) => {
    test.skip(!ALUMNO_EMAIL || !ALUMNO_PASS, 'Credenciales alumno no configuradas');
    await page.goto('/');
    await page.locator('#email').fill(ALUMNO_EMAIL!);
    await page.locator('#password').fill(ALUMNO_PASS!);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/inicio'));
    expect(page.url()).toContain('/inicio');
  });

  test('admin entra y llega a /admin', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales admin no configuradas');
    await page.goto('/');
    await page.locator('#email').fill(ADMIN_EMAIL!);
    await page.locator('#password').fill(ADMIN_PASS!);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/admin'));
    expect(page.url()).toContain('/admin');
  });
});
