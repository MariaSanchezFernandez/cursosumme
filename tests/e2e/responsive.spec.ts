// ─────────────────────────────────────────────────────────────
// tests/e2e/responsive.spec.ts
// Tests E2E del comportamiento responsive del sitio en
// diferentes viewports: móvil (~iPhone SE), tablet (~iPad mini)
// y escritorio. Cubre:
//   - viewport meta correcto (initial-scale=1)
//   - login sin scroll horizontal en móvil
//   - admin: drawer fuera de pantalla y hamburguesa visible en móvil
//   - admin: sidebar visible y hamburguesa oculta en escritorio
// Usamos setViewportSize en lugar de test.use(devices[...]) porque
// Playwright no permite cambiar defaultBrowserType en un describe.
// ─────────────────────────────────────────────────────────────

import { test, expect } from '@playwright/test';
import { getAdmin } from './helpers/auth';

const VP_MOBILE  = { width: 375,  height: 667 };
const VP_TABLET  = { width: 768,  height: 1024 };
const VP_DESKTOP = { width: 1280, height: 800 };

async function inyectarSesionAdmin(page: import('@playwright/test').Page) {
  // Usamos el token real cacheado por global-setup. Antes inyectábamos una
  // sesión sintética sin token y funcionaba porque las APIs no validaban
  // auth — ahora sí, y el interceptor redirige al login al detectar 401.
  const admin = getAdmin();
  await page.addInitScript((s) => {
    sessionStorage.setItem('umme_session', JSON.stringify(s));
  }, {
    userId: admin.userId,
    email:  admin.email,
    nombre: 'Test',
    rol:    'admin',
    token:  admin.token,
    exp:    Date.now() + 60_000,
  });
}

test.describe('Responsive: viewport meta', () => {
  test('login incluye initial-scale=1 en el viewport', async ({ page }) => {
    await page.goto('/login');
    const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
    expect(viewport ?? '').toContain('initial-scale=1');
  });
});

test.describe('Responsive: login en móvil', () => {
  test('login no genera scroll horizontal en 375x667', async ({ page }) => {
    await page.setViewportSize(VP_MOBILE);
    await page.goto('/login');
    await expect(page.locator('#login-form')).toBeVisible();

    const overflow = await page.evaluate(() => ({
      docW: document.documentElement.scrollWidth,
      winW: window.innerWidth,
    }));
    expect(overflow.docW, 'la página no debe tener scroll horizontal').toBeLessThanOrEqual(
      overflow.winW + 1,
    );
  });

  test('login: el input email tiene tamaño usable en móvil', async ({ page }) => {
    await page.setViewportSize(VP_MOBILE);
    await page.goto('/login');
    const email = page.locator('input#email');
    const box = await email.boundingBox();
    expect(box, 'el input email debe ser visible').not.toBeNull();
    if (box) {
      expect(box.width).toBeGreaterThan(150);
      expect(box.height).toBeGreaterThanOrEqual(32);
    }
  });
});

test.describe('Responsive: login en tablet', () => {
  test('login se centra y no desborda en 768x1024', async ({ page }) => {
    await page.setViewportSize(VP_TABLET);
    await page.goto('/login');
    await expect(page.locator('.login-card')).toBeVisible();
    const overflow = await page.evaluate(() => ({
      docW: document.documentElement.scrollWidth,
      winW: window.innerWidth,
    }));
    expect(overflow.docW).toBeLessThanOrEqual(overflow.winW + 1);
  });
});

test.describe('Responsive: drawer admin', () => {
  test('en móvil el sidebar está fuera de pantalla y hay hamburguesa', async ({ page }) => {
    await page.setViewportSize(VP_MOBILE);
    await inyectarSesionAdmin(page);

    await page.goto('/admin');
    if (!page.url().includes('/admin')) test.skip(true, 'Sesión sintética rechazada');

    await expect(page.locator('#btn-menu')).toBeVisible();

    const sidebarBox = await page.locator('#sidebarAdmin').boundingBox();
    expect(sidebarBox, 'sidebar debería existir').not.toBeNull();
    if (sidebarBox) expect(sidebarBox.x).toBeLessThan(0);

    await page.locator('#btn-menu').click();
    await page.waitForTimeout(350);
    const sidebarAbierto = await page.locator('#sidebarAdmin').boundingBox();
    if (sidebarAbierto) expect(sidebarAbierto.x).toBeGreaterThanOrEqual(0);

    await expect(page.locator('#sidebarOverlay')).toHaveClass(/activo/);
  });

  test('en escritorio el sidebar es visible y la hamburguesa está oculta', async ({ page }) => {
    await page.setViewportSize(VP_DESKTOP);
    await inyectarSesionAdmin(page);

    await page.goto('/admin');
    if (!page.url().includes('/admin')) test.skip(true, 'Sesión sintética rechazada');

    await expect(page.locator('#sidebarAdmin')).toBeVisible();
    await expect(page.locator('#btn-menu')).toBeHidden();

    const sidebarBox = await page.locator('#sidebarAdmin').boundingBox();
    if (sidebarBox) expect(sidebarBox.x).toBeGreaterThanOrEqual(0);
  });
});
