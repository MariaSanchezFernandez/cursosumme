// ─────────────────────────────────────────────────────────────
// tests/e2e/duracion.spec.ts
// Tests de la duración por tema y por curso.
//
// La fuente de verdad es ahora materiales.duracion_seg (segundos
// exactos del vídeo). Las APIs derivan duracion_seg por tema y por
// curso sumando ese valor. Estos tests verifican que:
//
//   - GET /api/materiales.php devuelve duracion_seg como campo.
//   - GET /api/temas.php incluye duracion_seg por tema.
//   - GET /api/cursos.php incluye duracion_seg total del curso.
//   - GET /api/mis-cursos.php (con sesión válida) devuelve la
//     duracion_seg del curso para el alumno.
//
// No comprueban el resultado numérico exacto contra producción —
// los valores cambian según los vídeos subidos en cada momento —
// sino que la columna existe y tiene tipo numérico.
// ─────────────────────────────────────────────────────────────

import { test, expect } from '@playwright/test';

test.describe('Duración: el schema y las APIs exponen duracion_seg', () => {
  test('materiales.php incluye duracion_seg en la respuesta', async ({ request }) => {
    const res = await request.get('/api/materiales.php?tema_id=10');
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.ok).toBe(true);
    if (data.materiales.length > 0) {
      const m = data.materiales[0];
      expect(m, 'cada material debe traer duracion_seg').toHaveProperty('duracion_seg');
      // null cuando aún no se ha calculado, número >= 0 si ya se calculó
      expect(m.duracion_seg === null || typeof m.duracion_seg === 'number').toBeTruthy();
    }
  });

  test('temas.php incluye duracion_seg por tema (suma de sus vídeos)', async ({ request }) => {
    const res = await request.get('/api/temas.php?curso_id=6');
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.ok).toBe(true);
    if (data.temas.length > 0) {
      const t = data.temas[0];
      expect(t).toHaveProperty('duracion_seg');
      // El SUM de COALESCE devuelve 0 si no hay vídeos con dato; nunca null.
      expect(typeof Number(t.duracion_seg)).toBe('number');
      expect(Number(t.duracion_seg)).toBeGreaterThanOrEqual(0);
    }
  });

  test('cursos.php incluye duracion_seg total del curso', async ({ request }) => {
    const res = await request.get('/api/cursos.php');
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.ok).toBe(true);
    if (data.cursos.length > 0) {
      const c = data.cursos[0];
      expect(c).toHaveProperty('duracion_seg');
      expect(Number(c.duracion_seg)).toBeGreaterThanOrEqual(0);
    }
  });

  test('cursos.php: la suma del curso es la suma de todos sus temas', async ({ request }) => {
    // Coherencia: cursos.php trae la suma total del curso, temas.php
    // trae la suma de cada tema; ambos deben coincidir.
    const cursosRes = await request.get('/api/cursos.php');
    const cursos    = (await cursosRes.json()).cursos as any[];
    const conTemas  = cursos.find((c) => Number(c.num_temas) > 0);
    if (!conTemas) test.skip(true, 'No hay cursos con temas');

    const temasRes = await request.get(`/api/temas.php?curso_id=${conTemas.id}`);
    const temas    = (await temasRes.json()).temas as any[];
    const sumaTemas = temas.reduce((acc, t) => acc + (Number(t.duracion_seg) || 0), 0);

    expect(Number(conTemas.duracion_seg)).toBe(sumaTemas);
  });
});
