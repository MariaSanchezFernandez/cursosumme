import { test, expect } from '@playwright/test';
import { getAdmin } from './helpers/auth';

const ALUMNO_EMAIL = process.env.TEST_ALUMNO_EMAIL;
const ALUMNO_PASS  = process.env.TEST_ALUMNO_PASS;
const ADMIN_EMAIL  = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS   = process.env.TEST_ADMIN_PASS;

test.describe('Login', () => {
  test('credenciales incorrectas muestran mensaje de error', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('noexiste@test.test');
    await page.locator('#password').fill('incorrecta123');
    await page.locator('button[type="submit"]').click();

    await expect(page.locator('#login-error')).toBeVisible();
  });

  test('alumno entra y llega a /inicio', async ({ page }) => {
    test.skip(!ALUMNO_EMAIL || !ALUMNO_PASS, 'Credenciales alumno no configuradas');
    await page.goto('/login');
    await page.locator('#email').fill(ALUMNO_EMAIL!);
    await page.locator('#password').fill(ALUMNO_PASS!);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/inicio'));
    expect(page.url()).toContain('/inicio');
  });

  test('admin entra y llega a /admin', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales admin no configuradas');
    await page.goto('/login');
    await page.locator('#email').fill(ADMIN_EMAIL!);
    await page.locator('#password').fill(ADMIN_PASS!);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => url.pathname.startsWith('/admin'));
    expect(page.url()).toContain('/admin');
  });

  test('logins repetidos sin device_id no acumulan slots fantasma', async ({ request }) => {
    test.skip(!ALUMNO_EMAIL || !ALUMNO_PASS || !ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales alumno/admin no configuradas');

    // Simula un navegador que nunca persiste el device_id (Safari ITP, modo
    // privado...): cada login llega sin device_id en el body. Antes del fix,
    // cada uno generaba un slot nuevo en `sesiones` hasta agotar max_sesiones
    // y bloquear el acceso pese a ser siempre "el mismo" dispositivo.
    const admin = getAdmin();
    const resAlumnos = await request.get('/api/alumnos.php', { headers: { 'X-Token': admin.token } });
    const alumnoId = (await resAlumnos.json()).alumnos
      .find((a: { email: string }) => a.email.toLowerCase() === ALUMNO_EMAIL!.toLowerCase())?.id;
    await request.delete(`/api/sesiones.php?usuario_id=${alumnoId}`, { headers: { 'X-Token': admin.token } });

    let ultimoToken = '';
    for (let i = 0; i < 5; i++) {
      const res = await request.post('/api/login.php', {
        data: { email: ALUMNO_EMAIL, contrasena: ALUMNO_PASS },
      });
      const body = await res.json();
      expect(body.ok, `login #${i + 1}: ${body.mensaje}`).toBe(true);
      ultimoToken = body.token;
    }

    await request.post('/api/logout.php', { headers: { Authorization: `Bearer ${ultimoToken}` } });
  });
});
