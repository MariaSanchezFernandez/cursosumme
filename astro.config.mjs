// @ts-check
import { defineConfig } from 'astro/config';

// https://astro.build/config
export default defineConfig({
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
