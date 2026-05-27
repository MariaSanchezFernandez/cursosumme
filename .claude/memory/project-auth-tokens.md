---
name: project-auth-tokens
description: Cómo viaja el token de sesión entre front y server, y por qué requireAuth tiene que aceptar X-Token + Authorization + ?token=
metadata:
  type: project
---

# Auth: cómo viaja el token entre front y server

## Cómo lo manda el front

Cada llamada a `/api/*` pasa por un interceptor de `window.fetch` instalado en `Plantilla.astro` y `PlantillaAdmin.astro`. Ese interceptor:

1. Lee `sessionStorage.umme_session.token`.
2. Lo inyecta como `X-Token: <token>` en los headers.
3. Si la respuesta es 401 y había sesión guardada, dispara `sesionExpirada()` → limpia storage y `window.location.replace('/login?expirada=1')`.

No se manda `Authorization: Bearer` salvo en sitios concretos que lo hacen explícitamente (logout, integraciones).

## Cómo lo lee el server

`public/api/db-config.php → requireAuth()` busca el token en este orden:

1. **`HTTP_X_TOKEN`** (header `X-Token`) — el caso normal del front.
2. **`HTTP_AUTHORIZATION` (`Bearer <t>`)** — para llamadas externas o helpers que lo usan explícitamente. Incluye fallback a `apache_request_headers()` por si Apache lo expone diferente.
3. **`?token=<t>`** — último recurso para endpoints donde Apache borra los headers (ej. uploads de vídeo en VdoCipher por chunked PUT).

## Por qué los 3, y no solo uno

- **X-Token**: lo que de hecho envía el interceptor del front.
- **Authorization**: lo que requería el código original, lo que usa Stripe y lo que sigue usando `logout.php` y `video.php`. Romperlo rompería esos flujos.
- **?token=**: IONOS Apache shared hosting borra headers en algunos verbos/rutas (ver [[project-vdocipher]]). Por eso `packs.php` y los flows VdoCipher pasan el token por query string.

## Dónde vive el token en BD

En la tabla **`sesiones`** — no en `usuarios.token_sesion`. La migración [`base-de-datos/migracion-sesiones.sql`](../../base-de-datos/migracion-sesiones.sql) sustituyó las columnas `usuarios.token_sesion` / `token_expira` por una tabla `sesiones` (un registro por dispositivo activo). El SQL correcto para validar un token es:

```sql
SELECT u.id, u.rol, u.nombre, u.email
  FROM sesiones s
  JOIN usuarios u ON u.id = s.usuario_id
 WHERE s.token = :t AND s.expira_en > NOW() AND u.activo = 1
 LIMIT 1
```

## Los dos bugs históricos (resueltos 2026-05-27)

1. **Header**: `requireAuth` SOLO leía `Authorization: Bearer` mientras que el interceptor del front SOLO mandaba `X-Token`. El `.htaccess` solo traduce `Authorization` → `HTTP_AUTHORIZATION`, no traduce `X-Token`. Resuelto: `requireAuth` ahora acepta las 3 fuentes (X-Token, Bearer, ?token=).

2. **Schema**: tras la migración a tabla `sesiones`, `requireAuth` seguía haciendo `SELECT ... FROM usuarios WHERE token_sesion = ?` contra una columna que ya no existía. `login.php` y `logout.php` sí se actualizaron; el helper no. Resultado: TODO endpoint protegido devolvía 401 aunque el token fuera válido y se enviara correctamente, porque el SELECT no encontraba al usuario. Esto provocaba el "entra y se sale" del admin: el dashboard llamaba a `/api/tickets.php`, recibía 401 → interceptor → `/login?expirada=1`. Resuelto: el SQL ahora hace JOIN con la tabla `sesiones`.

## How to apply

- Si un endpoint nuevo necesita auth: usar `requireAuth($pdo)` o `requireAdmin($pdo)`. NO leas headers manualmente — usa el helper.
- Cualquier llamada desde el front pasa por el interceptor automáticamente, no necesita añadir headers a mano.
- Si tienes que llamar a la API desde un contexto donde no hay interceptor (cron en server, script CLI, herramienta externa), usa `Authorization: Bearer <token>` — sigue funcionando.
- Si el endpoint sufre de "headers borrados por Apache" (uploads grandes, PUT, etc.), añade `?token=` como fallback igual que hace [`packs.php`](../../public/api/packs.php) y [`vdocipher-upload.php`](../../public/api/vdocipher-upload.php).
