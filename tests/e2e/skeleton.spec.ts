/**
 * TEST E2E — Skeleton screens
 * ---------------------------
 *
 * Verifica que las primitivas de skeleton están disponibles globalmente
 * (cargadas desde src/styles/global.css → @import './Skeleton.css') y
 * que la animación shimmer está presente.
 *
 * Las composiciones específicas por página (curso-skel, alumno-skel-row,
 * ticket-skel, err-skel-row) viven en sus páginas y requieren login
 * real para verlas, así que aquí solo probamos las primitivas en /preview
 * (página pública) y la página /preview en sí.
 */

import { test, expect } from '@playwright/test';

test.describe('Skeleton screens (primitivas)', () => {
  test('las clases .skeleton tienen animación shimmer aplicada', async ({ page }) => {
    await page.goto('/preview');

    // En /preview hay primitivas dentro de la sección 3
    const skeletons = page.locator('.skeleton');
    const count = await skeletons.count();
    expect(count).toBeGreaterThan(0);

    // La primera primitiva debe tener un ::after con animation
    const tieneAnimacion = await skeletons.first().evaluate((el) => {
      const styles = getComputedStyle(el, '::after');
      const anim = styles.animationName || styles.getPropertyValue('animation-name');
      return anim && anim !== 'none' && anim !== '';
    });
    expect(tieneAnimacion).toBeTruthy();
  });

  test('la página /preview muestra primitivas, tarjeta de curso y fila de alumno', async ({ page }) => {
    await page.goto('/preview');
    await expect(page.locator('.skeleton-text').first()).toBeVisible();
    await expect(page.locator('.skeleton-text-lg').first()).toBeVisible();
    await expect(page.locator('.skeleton-circle').first()).toBeVisible();
    await expect(page.locator('.skeleton-box').first()).toBeVisible();
    await expect(page.locator('.curso-skel-prev').first()).toBeVisible();
    await expect(page.locator('.fila-skel-prev').first()).toBeVisible();
  });
});
