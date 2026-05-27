---
name: Deploy con npm run deploy
description: Cómo funciona el despliegue, prerrequisitos, errores conocidos y lecciones aprendidas
type: feedback
---

# Deploy con `npm run deploy`

El despliegue está automatizado: [scripts/deploy.mjs](../../scripts/deploy.mjs) hace `npm run build` y sube `dist/` entero al servidor IONOS vía SFTP usando `ssh2-sftp-client.uploadDir`.

## Comando

```bash
npm run deploy
```

Lee credenciales de `cursosumme/.env`. Si `SFTP_PASS` no está definida, la pide por terminal.

## Prerrequisitos antes de desplegar

1. **`.env` completo** — debe tener todas las variables rellenas:
   ```
   DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
   SFTP_HOST, SFTP_PORT, SFTP_USER, SFTP_PASS, SFTP_REMOTE_PATH
   ```

2. **`public/api/db-config.php` debe existir localmente Y ESTAR COMPLETO** — está en `.gitignore` pero el build lo incluye en `dist/` y lo sube al servidor PISANDO el del server. Si tu copia local está incompleta (faltan claves de VdoCipher, Stripe, SMTP, BACKUP_SECRET…), el deploy ROMPE la web aunque la BD siga funcionando. Lista mínima de constantes:
   - `DB_HOST/PORT/NOMBRE/USUARIO/PASSWORD`
   - `BACKUP_SECRET`
   - `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_TAX_RATE_IVA`
   - `SMTP_HOST/PORT/USER/PASS/FROM_NAME`
   - `VDOCIPHER_API_KEY`, `VDOCIPHER_PLAYER_ID`
   - helpers: `sanitizeText`, `rateLimit`, `requireAuth`, `requireAdmin`
   Antes de cualquier deploy, hacer un `grep -E "define\(" public/api/db-config.php` y confirmar que están todas. Si falta alguna, **NO desplegar** hasta restaurar el archivo completo.

3. **`SFTP_REMOTE_PATH` debe ser `/`** — la raíz del SFTP es la raíz web del hosting. Si se pone `/cursosumme` u otro subdirectorio, los archivos van al sitio equivocado y la web sigue sirviendo los ficheros viejos.

## Reglas importantes

- **Siempre `npm run deploy`** — no hacer `put` manual. El script sube TODO `dist/` de forma atómica.
- Tras cualquier cambio en `.astro` o layouts, los hashes en `dist/_astro/` cambian. El script ya sube el `_astro/` completo.
- Para desplegar solo cambios PHP, aun así es preferible `npm run deploy` completo — es rápido y evita inconsistencias.
- **Nunca** subir `.env`, `db-config.php` ni nada de `uploads/` al repo (están en `.gitignore`).

## Si falla el deploy — checklist

| Síntoma | Causa probable | Solución |
|---|---|---|
| `Cannot find package 'ssh2-sftp-client'` | Dependencia no instalada | `npm install ssh2-sftp-client` |
| `All configured authentication methods failed` | Usuario SFTP incorrecto en `.env` | Verificar `SFTP_USER` en panel IONOS → SFTP & SSH |
| Deploy OK pero la web no cambia | `SFTP_REMOTE_PATH` incorrecto | Debe ser `/` — conectar por SFTP y listar la raíz para confirmarlo |
| `Error de conexión: Access denied` en la API | `db-config.php` con contraseña incorrecta | Comprobar contraseña exacta en panel IONOS → Bases de datos |
| API devuelve HTML de parking en vez de JSON | El archivo PHP no existe en el servidor | `SFTP_REMOTE_PATH` incorrecto o el deploy no incluyó ese archivo |

## Lección aprendida (2026-05-01)

Al configurar el proyecto desde cero hubo tres problemas encadenados:
- `db-config.php` no existía localmente → el servidor tenía uno antiguo con contraseña `cursosumme123` (incorrecta)
- `SFTP_REMOTE_PATH` estaba en `/cursosumme` → los deploys iban a una carpeta fantasma; la web seguía con los archivos viejos
- El usuario SFTP en `.env` era incorrecto → la autenticación fallaba al intentar verificar via script

**Why:** El `.env` y el `db-config.php` nunca se habían configurado en este equipo — solo existían en el servidor de forma manual.

**How to apply:** Si en un equipo nuevo el deploy falla o la API no responde, verificar estos tres puntos antes de cualquier otra cosa.

## Lección aprendida (2026-05-27)

El `db-config.php` local quedó desactualizado respecto al del servidor: faltaban Stripe, SMTP, VdoCipher, BACKUP_SECRET y rateLimit/sanitizeText. Al desplegar tras editar `requireAuth`, el archivo local pisó el del servidor y rompió todo (subida de vídeos, pagos, emails). Los "bugs" diagnosticados antes (X-Token, tabla `sesiones`) no eran bugs reales — el server YA tenía esas correcciones; el problema vino del local incompleto.

**How to apply:**
- Antes de tocar `db-config.php`, hacer `grep -c "define(" public/api/db-config.php` — la versión correcta tiene 16 defines. Si tu copia tiene menos, descárgate la del server por SFTP o pide a María la versión actual antes de tocar nada.
- NUNCA hacer `Write` (sobreescribir) sobre `db-config.php` sin confirmar primero que tu copia incluye todas las constantes listadas arriba. Usar `Edit` localizado.
- Si el server queda sin claves: pedir a María la copia completa y volver a desplegar.
