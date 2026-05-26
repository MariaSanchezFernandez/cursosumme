---
name: VdoCipher — integración de vídeo con DRM
description: VdoCipher sustituye a Cloudflare Stream para DRM Widevine/FairPlay en CursosUmme
type: project
---

CursosUmme usa VdoCipher para reproducción de vídeos con DRM (Widevine + FairPlay). Migrado desde Cloudflare Stream en mayo 2026.

**API key:** en `public/api/db-config.php` como `define('VDOCIPHER_API_KEY', '...')`.

## Flujo de subida

1. Admin → `POST /api/vdocipher-upload.php?token=X&uid=Y` → PHP hace `PUT` a VdoCipher y devuelve credenciales S3
2. Navegador sube el archivo DIRECTAMENTE al S3 de VdoCipher con FormData (sin pasar por IONOS)
3. Admin hace polling a `GET /api/vdocipher-status.php?material_id=X&token=Y` hasta `status=ready`

### Campos FormData S3 — orden exacto obligatorio:
```
x-amz-credential, x-amz-algorithm, x-amz-date, x-amz-signature,
key, policy, success_action_status=201, success_action_redirect='', file (siempre último)
```

## Flujo de reproducción (alumno)

1. `GET /api/vdocipher-otp.php?material_id=X&usuario_id=Y&token=Z`
2. Devuelve `{ otp, playbackInfo }`
3. iframe: `https://player.vdocipher.com/v2/?otp={otp}&playbackInfo={playbackInfo}`

## BD: columnas en tabla `materiales`

- `vdocipher_video_id VARCHAR(100)` — ID del vídeo en VdoCipher
- `vdo_status ENUM('uploading','processing','ready')` — estado de procesamiento
- `ruta` es nullable — vídeos VdoCipher no tienen archivo local
- Antiguas columnas `cf_video_id`/`cf_status` (Cloudflare) se conservan en BD pero ya no se usan

## Archivos clave

- `public/api/vdocipher-upload.php` — credenciales S3 (usa PUT a la API de VdoCipher)
- `public/api/vdocipher-status.php` — polling estado + rellena `duracion_seg` al completar
- `public/api/vdocipher-otp.php` — OTP para reproducción DRM
- `public/api/materiales.php` — DELETE llama a VdoCipher API para borrar el vídeo del CDN
- `src/pages/admin/cursos/editar.astro` — función `subirVideoVdoCipher`
- `src/pages/inicio.astro` — función `reproducirVideo` con lógica VdoCipher/local
- `src/components/PanelDetalle.astro` — iframe `#vdoWrapper/#vdoPlayer`

## Puntos críticos aprendidos

1. **PUT no POST**: la API de VdoCipher usa `PUT https://dev.vdocipher.com/api/videos?title=...`
2. **Token por query string**: IONOS Apache elimina el header `Authorization` → pasar siempre como `?token=X&uid=Y`
3. **`success_action_redirect=''` obligatorio**: la policy S3 lo exige aunque sea vacío — sin él → AccessDenied
4. **Sesión stale → 403**: si la admin hace login en otro dispositivo, el token del navegador queda obsoleto → solución: cerrar sesión y volver a entrar
5. **db-config.php**: está en `.gitignore` pero sí se despliega con `npm run deploy` (va en `dist/`). Si se modifica hay que desplegar.
6. **`overflow: hidden` en un antepasado del `<vdocipher-player>` ROMPE la protección DRM contra capturas** (Chrome desktop, hallazgo 2026-05-26). El contenedor `.video-box` tenía `overflow: hidden` para clip-pear las esquinas redondeadas → forzaba al navegador a una ruta de compositing no protegida → Widevine perdía el secure path → captura mostraba el frame en vez de quedarse en negro. **Solución:** cada hijo (`vdoWrapper`, `videoPlayer`, `videoMarcaAgua`) lleva su propio `border-radius`; `.video-box` NO usa `overflow: hidden`. Aplicable a cualquier reproductor DRM en Chromium — Widevine es sensible a la cadena de compositing del navegador.
7. **El watermark dinámico se inyecta vía `annotate` en el body del OTP**, no vía Custom Player Theme. El Custom Player ID `gMYKMNbATpzHnUZM` DESACTIVA la protección anti-captura del default — por eso ya **no** se pasa player ID al frontend desde `vdocipher-otp.php`. Ver doc 3.2.2 de VdoCipher; ejemplo de annotate (texto rotatorio con email del alumno):
   ```php
   ['type'=>'rtext','text'=>$email,'alpha'=>'0.60','color'=>'0xFFFFFF',
    'size'=>'15','interval'=>'5000','skip'=>'5000']
   ```
8. **El overlay HTML `#videoMarcaAgua` SOLO se muestra en vídeos locales sin DRM**. Para vídeos VdoCipher se oculta porque (a) duplicaría el watermark, (b) un overlay HTML aparece en screenshots aunque el vídeo no — destruye la sensación de "captura bloqueada".

**Why:** Cloudflare Stream no ofrecía DRM en el plan contratado; VdoCipher incluye Widevine L1 + FairPlay.

**How to apply:** Cualquier nuevo tipo de contenido de vídeo protegido debe seguir este mismo flujo (upload → poll → OTP).
