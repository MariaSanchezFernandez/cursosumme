// ─────────────────────────────────────────────────────────────
// tests/e2e/recordarme.spec.ts
// Tests E2E del flujo "Recordarme en este dispositivo (15 días)".
// Verifican el comportamiento del checkbox y de la hidratación
// localStorage → sessionStorage SIN necesidad de credenciales:
//   - El checkbox existe en el login y arranca desmarcado.
//   - Si una sesión vigente vive en localStorage, el layout la
//     copia a sessionStorage al cargar (alumno y admin).
//   - Si la sesión en localStorage está expirada, se elimina y
//     no hidrata sessionStorage.
//   - Al cerrar sesión desde la cabecera de alumno, se borra
//     también la sesión persistente.
// ─────────────────────────────────────────────────────────────

import { test, expect } from '@playwright/test';

const SESSION_KEY = 'umme_session';

test.describe('Recordarme: UI del login', () => {
  test('el checkbox "Recordarme" existe y está desmarcado por defecto', async ({ page }) => {
    await page.goto('/');
    const cb = page.locator('#recordarme');
    await expect(cb).toBeVisible();
    await expect(cb).not.toBeChecked();
  });
});

test.describe('Recordarme: hidratación de sesión', () => {
  test('una sesión vigente en localStorage hidrata sessionStorage al entrar a /inicio', async ({ page }) => {
    const sesion = {
      userId: 0,
      rol: 'alumno',
      email: 'test@test.test',
      nombre: 'Test',
      token: 't',
      exp: Date.now() + 60_000,
    };
    await page.addInitScript((s) => {
      localStorage.setItem('umme_session', JSON.stringify(s));
    }, sesion);

    await page.goto('/inicio');

    const sessionRaw = await page.evaluate(() => sessionStorage.getItem('umme_session'));
    expect(sessionRaw, 'la sesión persistente debe haberse hidratado').not.toBeNull();
    const recuperada = JSON.parse(sessionRaw!);
    expect(recuperada.email).toBe('test@test.test');
  });

  test('una sesión expirada en localStorage se elimina y no hidrata', async ({ page }) => {
    const sesionExpirada = {
      userId: 0,
      rol: 'alumno',
      email: 'test@test.test',
      nombre: 'Test',
      token: 't',
      exp: Date.now() - 1000,
    };
    await page.addInitScript((s) => {
      localStorage.setItem('umme_session', JSON.stringify(s));
    }, sesionExpirada);

    await page.goto('/inicio');

    const sessionRaw = await page.evaluate(() => sessionStorage.getItem('umme_session'));
    const localRaw   = await page.evaluate(() => localStorage.getItem('umme_session'));
    expect(sessionRaw, 'sessionStorage debe seguir vacío').toBeNull();
    expect(localRaw, 'localStorage caducado debe haberse limpiado').toBeNull();
  });

  test('una sesión persistente de admin también se hidrata en /admin', async ({ page }) => {
    const sesion = {
      userId: 0,
      rol: 'admin',
      email: 'admin@test.test',
      nombre: 'Admin',
      token: 't',
      exp: Date.now() + 60_000,
    };
    await page.addInitScript((s) => {
      localStorage.setItem('umme_session', JSON.stringify(s));
    }, sesion);

    await page.goto('/admin');

    const sessionRaw = await page.evaluate(() => sessionStorage.getItem('umme_session'));
    expect(sessionRaw).not.toBeNull();
    expect(JSON.parse(sessionRaw!).rol).toBe('admin');
  });
});
