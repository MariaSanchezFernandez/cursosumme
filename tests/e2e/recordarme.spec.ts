// ─────────────────────────────────────────────────────────────
// tests/e2e/recordarme.spec.ts
// Tests E2E del flujo "Recordarme en este dispositivo (15 días)".
//
// Bloque 1 (sin credenciales): verifica el comportamiento del
//   checkbox y de la hidratación localStorage → sessionStorage:
//   - El checkbox existe en el login y arranca desmarcado.
//   - Si una sesión vigente vive en localStorage, el layout la
//     copia a sessionStorage al cargar (alumno y admin).
//   - Si la sesión en localStorage está expirada, se elimina y
//     no hidrata sessionStorage.
//
// Bloque 2 (con credenciales reales en TEST_ALUMNO_EMAIL /
//   TEST_ALUMNO_PASS): verifica el flujo end-to-end del checkbox
//   simulando "cerrar y reabrir el navegador":
//   - Login con checkbox MARCADO → la sesión queda en localStorage
//     y, al abrir un contexto nuevo (sin sessionStorage), entrar a
//     /inicio NO redirige al login. La sesión persiste.
//   - Login con checkbox DESMARCADO → la sesión NO queda en
//     localStorage; al abrir un contexto nuevo, /inicio sí
//     redirige al login. La sesión efímera se pierde.
//   Estos tests se saltan automáticamente si no hay credenciales.
// ─────────────────────────────────────────────────────────────

import { test, expect } from '@playwright/test';

const ALUMNO_EMAIL = process.env.TEST_ALUMNO_EMAIL;
const ALUMNO_PASS  = process.env.TEST_ALUMNO_PASS;

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

// ── Bloque 2: flujo end-to-end con credenciales reales ───────────────────────
// Estos tests reproducen lo que hace la usuaria: marcar (o no) el checkbox,
// hacer login y comprobar que la sesión sobrevive (o no) a "cerrar el
// navegador". Para simular "cerrar y reabrir el navegador" se cierra el
// contexto de Playwright y se abre uno nuevo: como sessionStorage no se
// arrastra entre contextos pero localStorage sí (al exportarlo con
// storageState), reproducimos exactamente el comportamiento real.

test.describe('Recordarme: flujo end-to-end con credenciales', () => {
  test.skip(
    !ALUMNO_EMAIL || !ALUMNO_PASS,
    'Define TEST_ALUMNO_EMAIL y TEST_ALUMNO_PASS para correr este flujo',
  );

  test('login con checkbox MARCADO → la sesión persiste tras "cerrar y reabrir" el navegador', async ({ browser }) => {
    // 1) Primer contexto: hago login marcando "Recordarme"
    const ctx1  = await browser.newContext();
    const page1 = await ctx1.newPage();
    await page1.goto('/');
    await page1.locator('#email').fill(ALUMNO_EMAIL!);
    await page1.locator('#password').fill(ALUMNO_PASS!);
    await page1.locator('#recordarme').check();
    await page1.locator('button[type="submit"]').click();
    await page1.waitForURL('**/inicio', { timeout: 15_000 });

    // 2) La sesión debe estar tanto en sessionStorage como en localStorage
    const localTrasLogin   = await page1.evaluate(() => localStorage.getItem('umme_session'));
    const sessionTrasLogin = await page1.evaluate(() => sessionStorage.getItem('umme_session'));
    expect(localTrasLogin,   'localStorage debe tener la sesión persistente').not.toBeNull();
    expect(sessionTrasLogin, 'sessionStorage debe tener la sesión efímera').not.toBeNull();

    // 3) "Cierro el navegador": exporto storageState (solo localStorage + cookies)
    //    y cierro el contexto entero. sessionStorage se pierde por diseño del navegador.
    const storageState = await ctx1.storageState();
    await ctx1.close();

    // 4) "Abro de nuevo el navegador": contexto nuevo con la misma persistencia
    const ctx2  = await browser.newContext({ storageState });
    const page2 = await ctx2.newPage();

    // 5) Voy directo al área privada. Si "Recordarme" funciona, NO me echa al login.
    await page2.goto('/inicio');
    await page2.waitForLoadState('networkidle');
    expect(page2.url(), 'tras reabrir el navegador, /inicio debe seguir abierta').toContain('/inicio');

    // Y el script de hidratación habrá rellenado sessionStorage de nuevo
    const sessionRehidratada = await page2.evaluate(() => sessionStorage.getItem('umme_session'));
    expect(sessionRehidratada).not.toBeNull();

    await ctx2.close();
  });

  test('login con checkbox DESMARCADO → al "cerrar y reabrir" el navegador, la sesión se pierde', async ({ browser }) => {
    const ctx1  = await browser.newContext();
    const page1 = await ctx1.newPage();
    await page1.goto('/');
    await page1.locator('#email').fill(ALUMNO_EMAIL!);
    await page1.locator('#password').fill(ALUMNO_PASS!);
    // Sin marcar el checkbox
    await page1.locator('button[type="submit"]').click();
    await page1.waitForURL('**/inicio', { timeout: 15_000 });

    // Sin "Recordarme": NO debe haber sesión en localStorage
    const localTrasLogin = await page1.evaluate(() => localStorage.getItem('umme_session'));
    expect(localTrasLogin, 'sin checkbox, localStorage no debe tener la sesión').toBeNull();

    const storageState = await ctx1.storageState();
    await ctx1.close();

    const ctx2  = await browser.newContext({ storageState });
    const page2 = await ctx2.newPage();

    // Sin sesión persistente, /inicio debe redirigir al login
    await page2.goto('/inicio');
    await page2.waitForURL('**/', { timeout: 10_000 });
    expect(page2.url().replace(/\/$/, '')).toBe('http://cursosumme.es');

    await ctx2.close();
  });
});
