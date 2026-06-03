// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

// Rutas que NO deben aparecer en el sitemap: área privada del alumno,
// pasarelas de pago, panel admin, utilidades internas y previews que
// no tienen sentido como página de aterrizaje desde Google.
// Prefijos que indican secciones enteras del sitio que no deben indexarse:
// panel admin, área privada de cursos del alumno y carpetas internas.
const PREFIJOS_PRIVADOS = [
  '/admin/',
  '/cursos/',
];

// Páginas concretas privadas o sin valor SEO (login, pago, etc.).
const RUTAS_PRIVADAS = [
  '/inicio',
  '/perfil',
  '/checkout',
  '/pago-ok',
  '/pago-ko',
  '/recuperar-contrasena',
  '/preview',
  '/test-vdo',
  '/login',
  // Envoltorio HTML para PDFs (preserva el favicon en la pestaña).
  // Solo tiene sentido cuando se llega con ?ruta=... — sin URL útil
  // para Google, así que la dejamos fuera del sitemap.
  '/visor',
];

// https://astro.build/config
export default defineConfig({
  // Necesario para que @astrojs/sitemap genere URLs absolutas y para
  // que cualquier integración SEO use el dominio real en producción.
  site: 'https://cursosumme.es',

  integrations: [
    sitemap({
      filter: (page) => {
        // `page` es la URL absoluta de cada página estática a publicar.
        if (PREFIJOS_PRIVADOS.some((p) => page.includes(p))) return false;
        return !RUTAS_PRIVADAS.some((ruta) => {
          return page.endsWith(ruta) || page.endsWith(ruta + '/');
        });
      },
    }),
  ],

  vite: {
    server: {
      proxy: {
        '/api': {
          target: 'http://cursosumme.es',
          changeOrigin: true,
        },
        '/uploads': {
          target: 'http://cursosumme.es',
          changeOrigin: true,
        },
      },
    },
  },
});
