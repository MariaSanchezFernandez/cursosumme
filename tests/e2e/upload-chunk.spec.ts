// Tests del endpoint /api/upload-chunk.php
// Verifican validación de entrada y el flujo intermedio (sin llegar al
// último chunk para no insertar en BD). Un test de happy-path completo
// con vídeo real no es viable aquí (requeriría subir 1 GB+ a producción).
import { test, expect, request } from '@playwright/test';

const ENDPOINT = '/api/upload-chunk.php';

test.describe('API upload-chunk', () => {
  test('GET devuelve 405', async ({ request }) => {
    const res = await request.get(ENDPOINT);
    expect(res.status()).toBe(405);
  });

  test('POST sin chunk devuelve 400', async ({ request }) => {
    const res = await request.post(ENDPOINT, {
      multipart: {
        upload_id:       'abcdef0123456789abcdef0123456789',
        chunk_index:     '0',
        total_chunks:    '2',
        tema_id:         '1',
        nombre_original: 'test.mp4',
      },
    });
    expect(res.status()).toBe(400);
    const body = await res.json();
    expect(body.ok).toBe(false);
  });

  test('upload_id inválido devuelve 400', async ({ request }) => {
    const res = await request.post(ENDPOINT, {
      multipart: {
        upload_id:       '../etc/passwd',
        chunk_index:     '0',
        total_chunks:    '2',
        tema_id:         '1',
        nombre_original: 'test.mp4',
        chunk: { name: 'c.part', mimeType: 'application/octet-stream', buffer: Buffer.from('x') },
      },
    });
    expect(res.status()).toBe(400);
  });

  test('extensión no permitida es rechazada', async ({ request }) => {
    const res = await request.post(ENDPOINT, {
      multipart: {
        upload_id:       'abcdef0123456789abcdef0123456789',
        chunk_index:     '0',
        total_chunks:    '2',
        tema_id:         '1',
        nombre_original: 'malicioso.php',
        chunk: { name: 'c.part', mimeType: 'application/octet-stream', buffer: Buffer.from('x') },
      },
    });
    const body = await res.json();
    expect(body.ok).toBe(false);
    expect(String(body.mensaje || '')).toMatch(/permitido/i);
  });

  test('chunk intermedio válido responde {ok:true, recibido}', async ({ request }) => {
    // Usamos total_chunks=2 y enviamos solo el índice 0. El servidor
    // responde "recibido: 0" y NO inicia el ensamblaje — no toca BD.
    const uploadId = 'test' + Date.now().toString(36) + Math.random().toString(36).slice(2, 14);
    const res = await request.post(ENDPOINT, {
      multipart: {
        upload_id:       uploadId.slice(0, 32),
        chunk_index:     '0',
        total_chunks:    '2',
        tema_id:         '1',
        nombre_original: 'test-chunked.mp4',
        chunk: { name: 'c.part', mimeType: 'application/octet-stream', buffer: Buffer.from('fake-chunk-content') },
      },
    });
    expect(res.status()).toBe(200);
    const body = await res.json();
    expect(body.ok).toBe(true);
    expect(body.recibido).toBe(0);
  });
});
