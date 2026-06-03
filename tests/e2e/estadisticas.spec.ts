// ─────────────────────────────────────────────────────────────
// tests/e2e/estadisticas.spec.ts
// Happy path de la vista admin de estadísticas:
//
//   - GET /api/estadisticas.php exige rol admin (401 sin token).
//   - Con sesión admin, devuelve la forma esperada:
//     { ok, resumen{...}, alumnos[], cursos[] }
//     con los campos numéricos correctos.
//   - La página /admin/estadisticas carga, renderiza las stat-cards
//     con valores (no quedan en "—") y pinta la tabla de alumnos
//     (o el estado "sin datos" si no hay alumnado con cursos).
// ─────────────────────────────────────────────────────────────

import { test, expect, type Page } from '@playwright/test';
import { loginAdmin, authHeaders, getAdmin, type SesionTest } from './helpers/auth';

async function inyectarSesionAdmin(page: Page): Promise<void> {
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

test.describe('Estadísticas: API', () => {
  let admin: SesionTest;

  test.beforeAll(async ({ request }) => {
    admin = await loginAdmin(request);
  });

  test('rechaza llamadas sin sesión admin', async ({ request }) => {
    const res = await request.get('/api/estadisticas.php');
    // requireAdmin devuelve 401 o 403 según el helper. Aceptamos ambos
    // (lo importante: NO devuelve 200 con datos).
    expect([401, 403]).toContain(res.status());
  });

  test('devuelve resumen, alumnos y cursos con la forma esperada', async ({ request }) => {
    const res = await request.get('/api/estadisticas.php', { headers: authHeaders(admin) });
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.ok).toBe(true);

    // Resumen
    expect(data).toHaveProperty('resumen');
    const r = data.resumen;
    for (const campo of [
      'total_alumnos', 'activos_7d', 'activos_30d', 'sin_avance',
      'avance_medio_global', 'temas_totales', 'materiales_vistos_total',
    ]) {
      expect(r, `resumen.${campo} debe existir`).toHaveProperty(campo);
      expect(typeof r[campo]).toBe('number');
    }
    // Coherencia básica: activos_7d ≤ activos_30d ≤ total_alumnos
    expect(r.activos_7d).toBeLessThanOrEqual(r.activos_30d);
    expect(r.activos_30d).toBeLessThanOrEqual(r.total_alumnos);
    expect(r.avance_medio_global).toBeGreaterThanOrEqual(0);
    expect(r.avance_medio_global).toBeLessThanOrEqual(100);

    // Alumnos
    expect(Array.isArray(data.alumnos)).toBe(true);
    if (data.alumnos.length > 0) {
      const a = data.alumnos[0];
      for (const campo of [
        'id', 'nombre', 'apellidos', 'email',
        'num_cursos', 'total_temas', 'temas_completados', 'porcentaje',
      ]) {
        expect(a, `alumno.${campo} debe existir`).toHaveProperty(campo);
      }
      expect(a.porcentaje).toBeGreaterThanOrEqual(0);
      expect(a.porcentaje).toBeLessThanOrEqual(100);
      // temas_completados nunca puede exceder total_temas
      expect(a.temas_completados).toBeLessThanOrEqual(a.total_temas);
      // ultima_actividad debe ser string o null
      expect(a.ultima_actividad === null || typeof a.ultima_actividad === 'string').toBeTruthy();
    }

    // Cursos
    expect(Array.isArray(data.cursos)).toBe(true);
    if (data.cursos.length > 0) {
      const c = data.cursos[0];
      for (const campo of [
        'id', 'titulo', 'num_alumnos', 'num_temas',
        'completaciones_totales', 'porcentaje_medio',
      ]) {
        expect(c, `curso.${campo} debe existir`).toHaveProperty(campo);
      }
      expect(c.porcentaje_medio).toBeGreaterThanOrEqual(0);
      expect(c.porcentaje_medio).toBeLessThanOrEqual(100);
    }
  });
});

test.describe('Estadísticas: UI admin', () => {
  test('la página /admin/estadisticas carga y rellena las stat-cards', async ({ page }) => {
    await inyectarSesionAdmin(page);

    const errores: string[] = [];
    page.on('pageerror', e => errores.push(e.message));

    await page.goto('/admin/estadisticas');
    if (!page.url().includes('/admin/estadisticas')) {
      test.skip(true, 'Sesión sintética rechazada por el servidor');
    }

    // El sidebar debe marcar la sección activa
    await expect(page.locator('a[href="/admin/estadisticas"].activo')).toBeVisible();

    // Las stat-cards arrancan con "—" y la carga AJAX las debe rellenar.
    // Espera a que stat-avance deje de ser "—" (o el endpoint devuelva 0%).
    const stat = page.locator('#stat-avance');
    await expect(stat).not.toHaveText('—', { timeout: 10_000 });

    // Las demás stat-cards también deben tener un número (no el guión).
    for (const id of ['#stat-act-7', '#stat-act-30', '#stat-sin-avance', '#stat-vistos', '#stat-temas']) {
      await expect(page.locator(id)).not.toHaveText('—', { timeout: 10_000 });
    }

    // O bien se ve la tabla de alumnos o bien el estado vacío.
    // Ambas son válidas: depende de si hay datos en producción.
    const tabla = page.locator('#tabla-alumnos');
    const vacio = page.locator('#alumnos-vacio');
    await expect(tabla.or(vacio)).toBeVisible({ timeout: 10_000 });

    expect(errores, 'JS errors en /admin/estadisticas: ' + errores.join(' | ')).toHaveLength(0);
  });

  test('el buscador filtra la tabla de alumnos', async ({ page }) => {
    await inyectarSesionAdmin(page);
    await page.goto('/admin/estadisticas');
    if (!page.url().includes('/admin/estadisticas')) {
      test.skip(true, 'Sesión sintética rechazada por el servidor');
    }

    // Si no hay tabla (no hay alumnos), el filtro no aplica.
    const tabla = page.locator('#tabla-alumnos');
    await page.waitForLoadState('networkidle');
    if (!(await tabla.isVisible())) test.skip(true, 'No hay alumnos con datos para filtrar');

    // Buscamos algo que no exista — la tabla debe ocultarse y aparecer "sin coincidencias".
    await page.locator('#buscar-alumno').fill('zzz-no-existe-zzz');
    await expect(page.locator('#alumnos-vacio')).toBeVisible({ timeout: 3_000 });
    await expect(tabla).toBeHidden();
  });
});
