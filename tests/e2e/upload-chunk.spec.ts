// Tests del endpoint /api/upload-chunk.php
//
// El endpoint usa raw body streaming (Content-Type: application/octet-stream)
// con la metadata pasada por query string. NO multipart. Cualquier ruta de
// admin exige X-Token tras el endurecimiento de seguridad.
//
// No probamos el happy-path completo (último chunk) para no insertar
// material real en BD — solo el intermedio, que solo escribe en disco.

import { test, expect } from '@playwright/test';
import { loginAdmin, type SesionTest } from './helpers/auth';

const ENDPOINT = '/api/upload-chunk.php';

function buildUrl(params: Record<string, string>): string {
  const qs = new URLSearchParams(params).toString();
  return `${ENDPOINT}?${qs}`;
}

test.describe('API upload-chunk', () => {
  let admin: SesionTest;

  test.beforeAll(async ({ request }) => {
    admin = await loginAdmin(request);
  });

  test('GET devuelve 405', async ({ request }) => {
    // Esto no requiere auth: el check de método sucede antes de requireAdmin.
    const res = await request.get(ENDPOINT);
    expect(res.status()).toBe(405);
  });

  test('POST sin token devuelve 401', async ({ request }) => {
    const res = await request.post(buildUrl({
      upload_id:       'abcdef0123456789abcdef0123456789',
      chunk_index:     '0',
      total_chunks:    '2',
      tema_id:         '1',
      nombre_original: 'test.mp4',
    }), {
      headers: { 'Content-Type': 'application/octet-stream' },
      data: Buffer.from('x'),
    });
    expect(res.status()).toBe(401);
  });

  test('upload_id inválido devuelve 400', async ({ request }) => {
    const res = await request.post(buildUrl({
      upload_id:       '../etc/passwd',
      chunk_index:     '0',
      total_chunks:    '2',
      tema_id:         '1',
      nombre_original: 'test.mp4',
    }), {
      headers: { 'Content-Type': 'application/octet-stream', 'X-Token': admin.token },
      data: Buffer.from('x'),
    });
    expect(res.status()).toBe(400);
  });

  test('extensión no permitida es rechazada', async ({ request }) => {
    const res = await request.post(buildUrl({
      upload_id:       'abcdef0123456789abcdef0123456789',
      chunk_index:     '0',
      total_chunks:    '2',
      tema_id:         '1',
      nombre_original: 'malicioso.php',
    }), {
      headers: { 'Content-Type': 'application/octet-stream', 'X-Token': admin.token },
      data: Buffer.from('x'),
    });
    const body = await res.json();
    expect(body.ok).toBe(false);
    expect(String(body.mensaje || '')).toMatch(/permitido/i);
  });

  test('chunk intermedio válido responde {ok:true, recibido}', async ({ request }) => {
    // total_chunks=2 + index=0 → escribe el chunk al .part pero no
    // dispara el ensamblaje final ni insertan nada en BD.
    const uploadId = ('test' + Date.now().toString(36) + Math.random().toString(36).slice(2, 14)).slice(0, 32);
    const res = await request.post(buildUrl({
      upload_id:       uploadId,
      chunk_index:     '0',
      total_chunks:    '2',
      total_size:      '1000',
      tema_id:         '1',
      nombre_original: 'test-chunked.mp4',
    }), {
      headers: { 'Content-Type': 'application/octet-stream', 'X-Token': admin.token },
      data: Buffer.from('fake-chunk-content'),
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body.ok).toBe(true);
    expect(body.recibido).toBe(0);
  });
});
