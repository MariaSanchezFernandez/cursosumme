# Integración Stripe

Cuando un alumno completa un pago en Stripe Checkout, el webhook crea su cuenta automáticamente, le asigna los cursos comprados y le envía las credenciales por email (SMTP de IONOS).

## Estado: implementado (2026-05-04, refinado 2026-05-26)

Está activo en **modo test** (`sk_test_*`). Las claves están en `public/api/db-config.php` como `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` y `STRIPE_WEBHOOK_SECRET`. **Antes de producción** hay que cambiarlas a `sk_live_*` y regenerar el webhook secret en el dashboard de Stripe.

## Flujo

```
Comprar (precios.astro) → POST /api/stripe-checkout.php {tipo, id}
→ inserta `pagos` con estado=pendiente
→ devuelve URL de Stripe Checkout
→ usuario paga en Stripe
→ Stripe llama POST /api/stripe-webhook.php (checkout.session.completed)
→ verifica firma, crea/recupera usuario, asigna cursos, marca pago=completado, envía email
→ usuario llega a /pago-ok?session_id=cs_xxx
→ pago-ok sondea /api/pago-status.php hasta ver estado=completado (máx 10 intentos × 1.5s)
```

## Archivos

| Archivo | Propósito |
|---|---|
| `public/api/stripe-checkout.php` | Crea sesión Stripe (curso o pack) |
| `public/api/stripe-webhook.php` | Procesa `checkout.session.completed` |
| `public/api/pago-status.php` | Estado del pago para `/pago-ok` |
| `public/api/packs.php` | CRUD de packs (GET público, resto admin) |
| `public/api/email-helper.php` | Email bienvenida vía SMTP cURL |
| `src/pages/precios.astro` | Página pública de venta |
| `src/pages/pago-ok.astro` / `pago-ko.astro` | Retorno tras pago |
| `src/pages/admin/cursos.astro` | Editar precio + `stripe_price_id` por curso y por pack |

## Tablas BD (ya en `base-de-datos/estructura.sql`)

- `cursos.precio DECIMAL(8,2)`, `cursos.stripe_price_id VARCHAR(100)`
- `cursos.pack VARCHAR(120)` — vínculo curso → pack por **nombre del pack**
- `packs` (id, nombre, descripcion, precio, stripe_price_id, etiqueta, activo)
- `pagos` (id, stripe_session_id UNIQUE, email, cursos_ids JSON, alumno_id, estado, importe)

## Puntos críticos aprendidos

1. **Los cursos de un pack se identifican por `cursos.pack = packs.nombre`, NO por `etiqueta`.** Un bug previo buscaba por etiqueta (que es la categoría de la materia, no la pertenencia al pack) y entregaba los cursos equivocados o ninguno.
2. **El webhook RECHAZA eventos si `STRIPE_WEBHOOK_SECRET` está vacío** (devuelve 500). Antes saltaba la verificación si el secret estaba vacío — era un bypass de seguridad que permitía a cualquiera crearse una cuenta con acceso a cualquier curso fabricando un payload.
3. **Códigos HTTP del webhook**: 200 solo en éxito o "ya procesado", 400 si payload incompleto (no recuperable), 500 si excepción interna (Stripe reintenta hasta ~3 días).
4. **Contraseña inicial**: 12 chars aleatorios con `random_int`, hasheada con `bcrypt` cost 12. Se envía en plano en el email — la usuaria conoce el trade-off.
5. **Idempotencia**: el webhook chequea si `pagos.estado` ya es `completado` y sale temprano. La columna `stripe_session_id` tiene UNIQUE.
6. **Si el email ya existe**: no se crea alumno duplicado, se le añaden los cursos al existente y NO se envía email (el alumno ya tiene credenciales).
7. **CORS de checkout**: limitado a `http(s)://cursosumme.es` mediante header dinámico + `Vary: Origin`.
8. **`pago-ok` no muestra éxito sin confirmación del servidor** — sondea `pago-status.php` para evitar que el usuario crea que ha pagado si llegó a la URL manualmente.

## Pendientes antes de producción

- Cambiar claves Stripe a `sk_live_*` + regenerar `STRIPE_WEBHOOK_SECRET`
- Crear productos y precios en el dashboard de Stripe; copiar `price_id` a cada curso/pack (ya hay UI admin para esto)
- Actualizar URLs `success_url`/`cancel_url` en `stripe-checkout.php` a `https://` cuando se active SSL (ahora mismo `http://cursosumme.es`)
- E2E con Playwright del happy path (ver [[feedback-tests]])
