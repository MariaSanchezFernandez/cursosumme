import { test, expect } from '@playwright/test';

test.describe('Smoke: páginas públicas cargan sin errores', () => {
  test('login page carga y muestra formulario', async ({ page }) => {
    const errores: string[] = [];
    page.on('pageerror', e => errores.push(e.message));

    await page.goto('/');

    await expect(page.locator('#login-form')).toBeVisible();
    await expect(page.locator('input#email')).toBeVisible();
    await expect(page.locator('input#password')).toBeVisible();
    expect(errores, 'JS errors en la página: ' + errores.join(' | ')).toHaveLength(0);
  });

  test('recuperar contraseña carga', async ({ page }) => {
    await page.goto('/recuperar-contrasena');
    await expect(page).toHaveURL(/recuperar-contrasena/);
  });

  test('rutas protegidas redirigen al login sin sesión', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL('/');
  });

  test('inicio alumno redirige al login sin sesión', async ({ page }) => {
    await page.goto('/inicio');
    await expect(page).toHaveURL('/');
  });
});

test.describe('Smoke: API responde', () => {
  test('info-limites.php devuelve JSON de límites', async ({ request }) => {
    const res = await request.get('/api/info-limites.php');
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('upload_max_filesize');
    expect(data).toHaveProperty('post_max_size');
    expect(data).toHaveProperty('php_version');
  });

  test('login.php rechaza credenciales vacías', async ({ request }) => {
    const res = await request.post('/api/login.php', {
      data: { email: '', contrasena: '' },
    });
    const data = await res.json();
    expect(data.ok).toBeFalsy();
  });

  test('login.php rechaza credenciales inválidas', async ({ request }) => {
    const res = await request.post('/api/login.php', {
      data: { email: 'noexiste@test.test', contrasena: 'incorrecta123' },
    });
    const data = await res.json();
    expect(data.ok).toBeFalsy();
  });
});
