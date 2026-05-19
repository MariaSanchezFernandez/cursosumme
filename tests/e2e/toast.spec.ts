/**
 * TEST E2E — Sistema de toasts (window.mostrarToast / window.toast.*)
 * --------------------------------------------------------------------
 *
 * Comprueba el componente Toast.astro montado globalmente en ambos
 * layouts (Plantilla y PlantillaAdmin):
 *   - El contenedor #toastContainer existe en /
 *   - mostrarToast() inyecta un toast con texto, icono y clase correcta
 *   - El toast se auto-cierra al cumplirse la duración
 *   - Varios toasts coexisten (no se sobreescriben)
 *   - El botón × cierra manualmente
 *
 * No requiere credenciales: usa la página pública / (login) y dispara
 * los toasts vía page.evaluate() directamente.
 */

import { test, expect } from '@playwright/test';

test.describe('Toasts (sistema global)', () => {
  test('el contenedor #toastContainer existe en la página de login', async ({ page }) => {
    await page.goto('/');
    const contenedor = page.locator('#toastContainer');
    await expect(contenedor).toBeAttached();
    await expect(contenedor).toHaveAttribute('role', 'region');
    await expect(contenedor).toHaveAttribute('aria-label', 'Notificaciones');
  });

  test('mostrarToast() inyecta un toast visible con el texto correcto', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => window.mostrarToast('Hola desde el test', 'success'));

    const toast = page.locator('#toastContainer .toast').first();
    await expect(toast).toBeVisible();
    await expect(toast).toHaveClass(/toast--success/);
    await expect(toast).toContainText('Hola desde el test');
  });

  test('los 4 tipos aplican la clase correcta', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => {
      window.toast.success('Ok');
      window.toast.error('Mal');
      window.toast.info('Info');
      window.toast.warning('Cuidado');
    });
    await expect(page.locator('.toast--success')).toHaveCount(1);
    await expect(page.locator('.toast--error')).toHaveCount(1);
    await expect(page.locator('.toast--info')).toHaveCount(1);
    await expect(page.locator('.toast--warning')).toHaveCount(1);
  });

  test('un toast se auto-cierra al cumplirse la duración', async ({ page }) => {
    await page.goto('/');
    // Duración corta (600 ms) para que el test no se eternice
    await page.evaluate(() => window.mostrarToast('Efímero', 'info', 600));
    const toast = page.locator('.toast', { hasText: 'Efímero' });
    await expect(toast).toBeVisible();
    await expect(toast).toBeHidden({ timeout: 3000 });
  });

  test('el botón × cierra el toast manualmente', async ({ page }) => {
    await page.goto('/');
    // Duración 0 = no auto-cierra
    await page.evaluate(() => window.mostrarToast('Persistente', 'info', 0));
    const toast = page.locator('.toast', { hasText: 'Persistente' });
    await expect(toast).toBeVisible();
    await toast.locator('.toast-cerrar').click();
    await expect(toast).toBeHidden({ timeout: 1500 });
  });

  test('no se inyecta HTML — el mensaje se trata como texto plano', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => window.mostrarToast('<img src=x onerror=alert(1)>', 'error', 0));
    const toast = page.locator('.toast').first();
    // El texto se muestra literal, no se renderiza ningún <img> dentro de .toast-contenido
    await expect(toast.locator('.toast-contenido')).toContainText('<img');
    await expect(toast.locator('.toast-contenido img')).toHaveCount(0);
  });
});
