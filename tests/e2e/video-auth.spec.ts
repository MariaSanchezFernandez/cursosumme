// ─────────────────────────────────────────────────────────────
// tests/e2e/video-auth.spec.ts
// Tests de seguridad de /api/video.php.
//
// El endpoint protege los vídeos del directorio bloqueado
// /uploads/videos/ y debe rechazar cualquier petición que no
// presente un token de sesión válido emitido por /api/login.php.
// Estos tests garantizan que NO se puede acceder a un vídeo
// "haciéndose pasar" por admin: hace falta el token del admin,
// que solo conoce quien ha iniciado sesión.
// ─────────────────────────────────────────────────────────────

import { test, expect } from '@playwright/test';

const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL || process.env.TEST_ALUMNO_EMAIL;
const ADMIN_PASS  = process.env.TEST_ADMIN_PASS  || process.env.TEST_ALUMNO_PASS;
const TOKEN_FALSO = 'a'.repeat(64); // 64 hex chars, formato válido pero inexistente en BD

// Modo serial: cada login regenera el token del usuario en BD, así que
// dos tests con login en paralelo se invalidan el token mutuamente.
test.describe.configure({ mode: 'serial' });

test.describe('Seguridad de /api/video.php', () => {
  test('sin token → 401', async ({ request }) => {
    const res = await request.get('/api/video.php?material_id=43&usuario_id=1');
    expect(res.status()).toBe(401);
  });

  test('con token mal formado → 401', async ({ request }) => {
    const res = await request.get('/api/video.php?material_id=43&usuario_id=1&token=hola');
    expect(res.status()).toBe(401);
  });

  test('con token bien formado pero inexistente en BD → 401', async ({ request }) => {
    const res = await request.get(`/api/video.php?material_id=43&usuario_id=1&token=${TOKEN_FALSO}`);
    expect(res.status()).toBe(401);
  });

  test('con token de otro usuario (no coincide con usuario_id) → 401', async ({ request }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales no configuradas');
    // Hago login real, obtengo un token válido para el usuario X y
    // luego intento usarlo con un usuario_id distinto. Debe fallar.
    const login = await request.post('/api/login.php', {
      data: { email: ADMIN_EMAIL, contrasena: ADMIN_PASS },
    });
    const data = await login.json();
    expect(data.ok).toBe(true);
    const tokenValido = data.token as string;
    const idDistinto  = String(parseInt(data.id, 10) + 1);

    const res = await request.get(
      `/api/video.php?material_id=43&usuario_id=${idDistinto}&token=${tokenValido}`,
    );
    expect(res.status()).toBe(401);
  });

  test('con token válido del propio admin → 200 (puede ver cualquier vídeo)', async ({ request }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales no configuradas');
    const login = await request.post('/api/login.php', {
      data: { email: ADMIN_EMAIL, contrasena: ADMIN_PASS },
    });
    const data = await login.json();
    expect(data.ok).toBe(true);

    // Pedimos solo el primer kilobyte con un Range para no descargar
    // el vídeo entero (cientos de MB). Si la auth pasa, el endpoint
    // responde 206 Partial Content con Content-Type video/...
    const res = await request.get(
      `/api/video.php?material_id=43&usuario_id=${data.id}&token=${data.token}`,
      { headers: { Range: 'bytes=0-1023' } },
    );
    expect([200, 206]).toContain(res.status());
    expect(res.headers()['content-type']).toContain('video/');
  });

  test('admin con &descarga=1 → respuesta incluye Content-Disposition: attachment', async ({ request }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASS, 'Credenciales no configuradas');
    const login = await request.post('/api/login.php', {
      data: { email: ADMIN_EMAIL, contrasena: ADMIN_PASS },
    });
    const data = await login.json();
    expect(data.ok).toBe(true);

    const res = await request.get(
      `/api/video.php?material_id=43&usuario_id=${data.id}&token=${data.token}&descarga=1`,
      { headers: { Range: 'bytes=0-1023' } },
    );
    expect([200, 206]).toContain(res.status());
    const cd = res.headers()['content-disposition'] ?? '';
    expect(cd, 'debe forzar attachment con nombre').toContain('attachment');
    expect(cd).toMatch(/filename=/);
  });

  test('descarga sin token → 401 (no se puede descargar sin estar autenticado)', async ({ request }) => {
    const res = await request.get('/api/video.php?material_id=43&usuario_id=1&descarga=1');
    expect(res.status()).toBe(401);
  });
});
