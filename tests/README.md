# Tests E2E — CursosUmme

Playwright corre contra **http://cursosumme.es** (producción) por defecto.
Cambiar con `BASE_URL=http://localhost:4321 npx playwright test`.

## Comandos

```bash
npm test                 # correr todos los tests (headless)
npm run test:ui          # UI modo interactivo (útil para debug)
npm run test:report      # abrir el último HTML report
npx playwright test smoke   # solo smoke
npx playwright test login   # solo login
```

## Estructura

- `tests/e2e/smoke.spec.ts` — páginas públicas + endpoints de API (sin login)
- `tests/e2e/login.spec.ts` — flujos de login (necesita credenciales)

## Credenciales de prueba

Los tests que requieren login se saltan si faltan las variables.
Crearlas en `.env.test` (no commitear) o exportarlas:

```bash
export TEST_ALUMNO_EMAIL="test.alumno@umme.test"
export TEST_ALUMNO_PASS="..."
export TEST_ADMIN_EMAIL="..."
export TEST_ADMIN_PASS="..."
npm test
```

**Recomendación:** crear un alumno y admin dedicados para tests, no usar cuentas reales.

## Política

Cada nueva funcionalidad debe incluir al menos un test E2E del *happy path*.
Si tocas un flujo con test, actualiza el test.
