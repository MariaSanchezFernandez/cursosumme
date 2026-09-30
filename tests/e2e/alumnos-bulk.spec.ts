// Tests E2E de las acciones en bloque sobre alumnos (/api/alumnos-bulk.php).
// Solo se usa el alumno de prueba (TEST_ALUMNO_EMAIL) y acciones que no
// alteran su estado final: asignar y luego quitar el mismo curso, y
// "activar" una cuenta ya activa. Nunca se desactiva al alumno de prueba
// porque invalidaría el token cacheado del resto de tests.

import { test, expect } from '@playwright/test';
import { getAdmin, getAlumno } from './helpers/auth';

test.describe('Acciones en bloque de alumnos', () => {
  test('un alumno no puede usar el endpoint', async ({ request }) => {
    const alumno = getAlumno();
    const res = await request.post('/api/alumnos-bulk.php', {
      headers: { 'X-Token': alumno.token },
      data: { accion: 'activar', ids: [alumno.userId] },
    });
    expect([401, 403]).toContain(res.status());
  });

  test('admin asigna y quita un curso en bloque', async ({ request }) => {
    const admin  = getAdmin();
    const alumno = getAlumno();
    const headers = { 'X-Token': admin.token };

    const cursos = (await (await request.get('/api/cursos.php', { headers })).json()).cursos ?? [];
    const misCursos = (await (await request.get('/api/mis-cursos.php', { headers: { 'X-Token': alumno.token } })).json());
    const idsMios = new Set(((misCursos.cursos ?? []) as { id: number }[]).map(c => Number(c.id)));
    // Curso que el alumno de prueba NO tenga, para dejarlo igual al final
    const curso = cursos.find((c: { id: number }) => !idsMios.has(Number(c.id)));
    test.skip(!curso, 'No hay un curso libre para probar');

    const asignar = await request.post('/api/alumnos-bulk.php', {
      headers, data: { accion: 'asignar_cursos', ids: [alumno.userId], cursos: [curso.id] },
    });
    expect((await asignar.json()).ok).toBe(true);

    const quitar = await request.post('/api/alumnos-bulk.php', {
      headers, data: { accion: 'quitar_cursos', ids: [alumno.userId], cursos: [curso.id] },
    });
    const body = await quitar.json();
    expect(body.ok).toBe(true);
    expect(body.afectados).toBe(1);
  });

  test('la barra de acciones aparece al seleccionar alumnos', async ({ page }) => {
    const admin = getAdmin();
    await page.addInitScript(s => sessionStorage.setItem('umme_session', JSON.stringify(s)),
      { ...admin, nombre: 'Admin test', exp: Date.now() + 3_600_000 });
    await page.goto('/admin/alumnos');
    await page.locator('.bulk-check').first().check();
    await expect(page.locator('#bulk-bar')).toBeVisible();
    await page.locator('#bulk-accion').selectOption('asignar_cursos');
    await expect(page.locator('#bulk-cursos')).toBeVisible();
    await expect(page.locator('#bulk-aplicar')).toBeDisabled();

    // En "Desbloquear temas", marcar un curso muestra sus temas
    await page.locator('#bulk-accion').selectOption('desbloquear_temas');
    await page.locator('#bulk-cursos-lista input').first().check();
    await expect(page.locator('#bulk-temas')).toBeVisible();
  });
});
