/**
 * TEST E2E — Helper window.botonCargando()
 * -----------------------------------------
 *
 * Verifica el helper global definido en src/components/BotonCargando.astro:
 *   - Mientras la acción se está ejecutando, el botón muestra un spinner
 *     y el texto contextual, queda disabled y con data-cargando="1".
 *   - Al terminar (resuelto o rechazado), se restaura el HTML, disabled
 *     y el ancho originales.
 *   - Dos clics seguidos sobre el mismo botón solo disparan UNA acción
 *     (protección anti-doble-click).
 *
 * No requiere credenciales: la página pública /login ya monta el
 * helper en su layout, así que podemos invocarlo directamente vía
 * page.evaluate() sobre un botón sintético creado en runtime.
 */

import { test, expect } from '@playwright/test';

test.describe('botonCargando (helper global)', () => {
  test('inyecta spinner + texto y luego restaura el contenido original', async ({ page }) => {
    await page.goto('/login');

    // Inyectamos un botón sintético y disparamos botonCargando con una promesa
    // que mantenemos abierta vía window.__resolver para poder inspeccionar
    // el estado intermedio.
    await page.evaluate(() => {
      const btn = document.createElement('button');
      btn.id = 'btnTest';
      btn.textContent = 'Guardar';
      btn.style.cssText = 'padding:8px 16px; font-size:14px;';
      document.body.appendChild(btn);
      (window as any).__cargando = window.botonCargando(btn, 'Guardando…', () =>
        new Promise((resolve) => { (window as any).__resolver = resolve; })
      );
    });

    const btn = page.locator('#btnTest');
    // Mientras la acción está en curso
    await expect(btn).toHaveAttribute('data-cargando', '1');
    await expect(btn).toBeDisabled();
    await expect(btn).toContainText('Guardando…');
    await expect(btn.locator('.boton-cargando-spinner')).toBeVisible();

    // Resolvemos la promesa y esperamos a que termine el ciclo
    await page.evaluate(() => (window as any).__resolver());
    await page.evaluate(() => (window as any).__cargando);

    // Tras terminar
    await expect(btn).not.toHaveAttribute('data-cargando', '1');
    await expect(btn).toBeEnabled();
    await expect(btn).toContainText('Guardar');
    await expect(btn.locator('.boton-cargando-spinner')).toHaveCount(0);
  });

  test('ignora clics adicionales mientras está cargando (anti-doble-click)', async ({ page }) => {
    await page.goto('/login');

    const llamadas = await page.evaluate(async () => {
      const btn = document.createElement('button');
      btn.id = 'btnAnti';
      btn.textContent = 'Borrar';
      document.body.appendChild(btn);

      let veces = 0;
      const accion = () => new Promise<void>((resolve) => {
        veces++;
        setTimeout(resolve, 200);
      });

      // Dos llamadas seguidas: la segunda debería ignorarse
      const p1 = window.botonCargando(btn, 'Borrando…', accion);
      const p2 = window.botonCargando(btn, 'Borrando…', accion);
      await Promise.all([p1, p2]);
      return veces;
    });

    expect(llamadas).toBe(1);
  });

  test('restaura el botón aunque la acción lance una excepción', async ({ page }) => {
    await page.goto('/login');

    await page.evaluate(async () => {
      const btn = document.createElement('button');
      btn.id = 'btnError';
      btn.textContent = 'Probar';
      document.body.appendChild(btn);

      try {
        await window.botonCargando(btn, 'Probando…', async () => {
          throw new Error('fallo simulado');
        });
      } catch { /* esperado */ }
    });

    const btn = page.locator('#btnError');
    await expect(btn).toBeEnabled();
    await expect(btn).not.toHaveAttribute('data-cargando', '1');
    await expect(btn).toContainText('Probar');
  });
});
