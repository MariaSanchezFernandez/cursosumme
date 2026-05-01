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

2. **`public/api/db-config.php` debe existir localmente** — está en `.gitignore` pero el build lo incluye en `dist/` y lo sube al servidor. Sin él, toda la BD falla en producción. Si no existe, crearlo desde cero (ver estructura en el archivo).

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
