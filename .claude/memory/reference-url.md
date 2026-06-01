# URL pública de CursosUmme

La web en producción está en **https://cursosumme.es** (no `.com`). SSL activo desde 2026-06-01.

- Todas las URLs de test, fetch externas o curl deben usar `https://cursosumme.es`.
- Ya migrado a HTTPS en `playwright.config.ts`, `stripe-checkout.php` (success/cancel) y `src/pages/cursos/[id].astro`. El webhook live de Stripe apunta a `https://cursosumme.es/api/stripe-webhook.php`.
