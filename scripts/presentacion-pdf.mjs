// Genera presentacion.pdf a partir de presentacion.html usando el Chromium de Playwright.
// Respeta el CSS de impresión (horizontal, sin márgenes, con fondos).
// Uso: node scripts/presentacion-pdf.mjs
import { chromium } from 'playwright';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';

const htmlPath = resolve('presentacion.html');
const pdfPath = resolve('presentacion.pdf');

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
await page.emulateMedia({ media: 'print' });
// Espera a que carguen las fuentes (si hay red); si no, sigue con el fallback del sistema.
await page.evaluate(() => document.fonts.ready).catch(() => {});
await page.waitForTimeout(800);
// Tamaño de página 16:9 exacto (igual que las diapositivas del HTML), sin márgenes.
await page.pdf({
  path: pdfPath,
  printBackground: true,
  width: '1280px',
  height: '720px',
  margin: { top: '0', right: '0', bottom: '0', left: '0' },
});
await browser.close();
console.log('PDF generado en', pdfPath);
