# Instrucciones VdoCipher API

Documentación completa de la API de VdoCipher para integración en proyectos.

**Fuente:** https://www.vdocipher.com/docs/server/

---

## Tabla de Contenidos

1. [Visión General](#1-visión-general)
2. [Autenticación de la API](#2-autenticación-de-la-api)
3. [Autenticación de Reproducción (OTP)](#3-autenticación-de-reproducción-otp)
   - Generación de OTP
   - Opciones Avanzadas de OTP
4. [Subida Automatizada de Videos](#4-subida-automatizada-de-videos)
5. [Gestión de Videos](#5-gestión-de-videos)
6. [Carpetas (Folders)](#6-carpetas-folders)
7. [Tags (Etiquetas)](#7-tags-etiquetas)
8. [Captions y Posters](#8-captions-y-posters)
9. [Web Hooks](#9-web-hooks)
10. [Consumo de Ancho de Banda](#10-consumo-de-ancho-de-banda)
11. [Player (Reproductor)](#11-player-reproductor)
12. [API de JavaScript del Player](#12-api-de-javascript-del-player)

---

## 1. Visión General

VdoCipher proporciona APIs RESTful para la gestión de activos de video. Las APIs cubren:

- **Autenticación de reproducción**: Control de acceso a videos mediante OTP
- **Gestión de videos**: Listar, borrar, renombrar videos y gestionar carpetas/tags
- **Subida automatizada**: Pipeline de ingesta de videos via API

**Base URL para operaciones sobre un video específico:**

```
https://dev.vdocipher.com/api/videos/{videoID}/
```

> La documentación completa de Swagger está en: https://www.vdocipher.com/docs/swagger/index.html

---

## 2. Autenticación de la API

Todas las solicitudes a la API deben autenticarse con tu API key mediante el header `Authorization`.

**Formato del header:**

```
Authorization: Apisecret TU_API_KEY
```

**Ejemplo:**

Si tu API key es `a1b2c3d4e5`, el header correcto es:

```
Authorization: Apisecret a1b2c3d4e5
```

> ⚠️ **IMPORTANTE:** El valor del header siempre debe tener el prefijo `Apisecret` seguido de un espacio. Mantén tu API secret key siempre privada, nunca la expongas en código cliente.

---

## 3. Autenticación de Reproducción (OTP)

### 3.1 Generación de OTP

Los OTP (One-Time Password) son tokens generados mediante la API de VdoCipher que se requieren para autorizar la reproducción de videos.

**Reglas importantes:**

- El OTP **siempre debe generarse en el servidor backend**, nunca en el frontend
- El OTP generado se envía al frontend para incluirlo en el código de embed del video
- Es necesario enviar tanto `otp` como `playbackInfo` al frontend

**Endpoint:**

```
POST https://dev.vdocipher.com/api/videos/{videoID}/otp
```

**Headers requeridos:**

```
Accept: application/json
Authorization: Apisecret TU_API_KEY
Content-Type: application/json
```

**Respuesta exitosa:**

```json
{
  "otp": "1234567890abcdefghijk",
  "playbackInfo": "z1y2x3w4v5u6t7s8r9q10"
}
```

**Ejemplo CURL:**

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/1234567890/otp \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"ttl":300}'
```

### 3.2 Opciones Avanzadas del OTP (Request Body)

El cuerpo del POST para generar OTP acepta los siguientes parámetros opcionales:

#### 3.2.1 Time-to-Live (TTL)

Controla el tiempo de validez del OTP (en segundos). Por defecto es 6 horas.

- Se recomienda un TTL mínimo de 5 minutos (300 segundos)
- Para URLs estáticas (contenido no premium), se puede usar un TTL de 30 años
- Se genera un nuevo OTP cada vez que la página se recarga

```json
{
  "ttl": 300
}
```

#### 3.2.2 Marca de Agua (Watermark / Annotation)

Permite mostrar una marca de agua dinámica (texto en movimiento) sobre el video.

```json
{
  "annotate": "[{'type':'rtext', 'text':' {name}', 'alpha':'0.60', 'color':'0xFF0000','size':'15','interval':'5000', 'skip': '5000'}]"
}
```

> ⚠️ El parámetro `annotate` necesita serialización extra (JSON.stringify en JS, json_encode en PHP), además de la serialización general del body POST.

**Ejemplo CURL con watermark:**

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/1234567890/otp \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"annotate":"[{''type'':''rtext'', ''text'':'' {name}'', ''alpha'':''0.60'', ''color'':''0xFF0000'',''size'':''15'',''interval'':''5000''}]"}'
```

#### 3.2.3 Whitelist de URLs

Restringe la reproducción a dominios específicos mediante expresión regular.

```json
{
  "whitelisthref": "tudominio.com"
}
```

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/1234567890/otp \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"whitelisthref":"tudominio.com"}'
```

#### 3.2.4 Reglas de IP y Geografía

Permite restringir o permitir la reproducción por IP o país.

**Parámetro `ipGeo`:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `allow` | string[] | Lista de códigos de país (ISO 3166-1 alpha2) e IPs IPv4 permitidas |
| `block` | string[] | Lista de códigos de país e IPs a bloquear |
| `except` | string[] | Excepciones a la lista allow o block |

**Configuraciones de ejemplo:**

```json
// Permitir solo una IP específica
{"allow": ["123.123.123.123"]}

// Bloquear países
{"block": ["CN", "RU", "KP"]}

// Permitir solo ciertos países
{"allow": ["US", "MX", "ES"]}

// Bloquear países excepto una IP
{"block": ["CN", "RU"], "except": ["123.123.123.123"]}

// Restringir a una IP específica dentro de un país
{"allow": [["123.123.123.123", "US", "MX"]]}
```

> ✅ **Recomendación:** Al generar OTP en el backend, también obtén la IP del usuario y restringe el OTP a esa IP específica.

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/1234567890/otp \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"ipGeo": {"allow": ["IP_DEL_USUARIO"]}}'
```

#### 3.2.5 Configuración Offline (Descarga para Reproducción sin Internet)

Disponible para Android SDK, iOS SDK, React Native y Flutter SDK. Permite descargar videos cifrados para reproducción offline.

```json
{
  "licenseRules": "{"canPersist": true, "rentalDuration": 1296000}"
}
```

- `canPersist`: true para permitir persistencia de la licencia en el dispositivo
- `rentalDuration`: tiempo de validez en segundos (ej: 1296000 = 15 días)

> ⚠️ El valor de `licenseRules` es un **string con JSON serializado**, no un objeto JSON.

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/1234567890/otp \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"licenseRules": "{\"canPersist\": true, \"rentalDuration\": 1296000}"}'
```

#### 3.2.6 Analytics por Usuario (Viewer Analytics)

Permite rastrear la reproducción por usuario individual.

```json
{
  "ttl": 300,
  "userId": "ID_DEL_USUARIO"
}
```

**Reglas para `userId`:**

- Longitud máxima: 36 caracteres
- Solo caracteres alfanuméricos, guiones (`-`) y guiones bajos (`_`)
- Usar la clave primaria existente de tu base de datos
- **NO usar** email, teléfono u información identificable
- **NO enviar** valores estáticos como `"unknown"` o `"guest"` cuando no hay usuario logueado

**Casos de uso:**

- Identificar compartición de contraseñas
- Seguimiento de uso por carpeta
- Rastrear fuentes de piratería
- Analizar patrones de visualización

---

## 4. Subida Automatizada de Videos

### 4.1 Importar Video desde URL

Permite importar un video directamente desde una URL HTTP/HTTPS/FTP.

**Endpoint:**

```
PUT https://dev.vdocipher.com/api/videos/importUrl
```

**Body:**

```json
{
  "url": "https://ejemplo.com/video.mp4",
  "folderId": "ID_DE_LA_CARPETA",
  "title": "Título del Video"
}
```

- `url` es el único campo requerido
- `folderId`: opcional. Usar `"root"` para guardar en el nivel superior
- `title`: opcional, título del video

```bash
curl -X PUT \
  https://dev.vdocipher.com/api/videos/importUrl \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"url": "https://ejemplo.com/video.mp4", "folderId": "root", "title": "Mi Video"}'
```

### 4.2 Subida de Archivo - Paso 1: Obtener Credenciales

El proceso de subida es de dos pasos. Primero se obtienen credenciales temporales.

**Endpoint:**

```
PUT https://dev.vdocipher.com/api/videos?title=TITULO_DEL_VIDEO
```

**Parámetros de query opcionales:**

- `title`: título del video (requerido)
- `folderId`: carpeta destino (por defecto: `root`)

```bash
curl -X PUT \
  'https://dev.vdocipher.com/api/videos?title=mi-video&folderId=ca038407e1b0XXXX' \
  -H 'Authorization: Apisecret a1b2c3d4e5'
```

**Respuesta:**

```json
{
  "clientPayload": {
    "policy": "{{policy}}",
    "key": "{{key}}",
    "x-amz-signature": "{{x-amz-signature}}",
    "x-amz-algorithm": "{{x-amz-algorithm}}",
    "x-amz-date": "{{x-amz-date}}",
    "x-amz-credential": "{{x-amz-credential}}",
    "uploadLink": "https://{s3-bucket-url}.amazonaws.com"
  },
  "videoId": "1234567890"
}
```

> El `videoId` devuelto es el ID del video creado. Las credenciales de `clientPayload` se usan en el Paso 2.

### 4.3 Subida de Archivo - Paso 2a: Upload desde Servidor

Usar las credenciales del Paso 1 para subir el archivo directamente a S3.

**Endpoint:** el valor de `uploadLink` recibido en el Paso 1

```bash
curl -X POST \
  'https://{s3-bucket-url}.amazonaws.com' \
  -H 'Content-Type: multipart/form-data' \
  -F 'policy={{policy}}' \
  -F 'key={{key}}' \
  -F 'x-amz-signature={{x-amz-signature}}' \
  -F 'x-amz-algorithm={{x-amz-algorithm}}' \
  -F 'x-amz-date={{x-amz-date}}' \
  -F 'x-amz-credential={{x-amz-credential}}' \
  -F success_action_status=201 \
  -F 'success_action_redirect=' \
  -F 'file=@/ruta/al/archivo.mp4'
```

**Campos requeridos en el form-data:**

| Campo | Valor |
|-------|-------|
| `policy` | Del clientPayload |
| `key` | Del clientPayload |
| `x-amz-signature` | Del clientPayload |
| `x-amz-algorithm` | Del clientPayload |
| `x-amz-date` | Del clientPayload |
| `x-amz-credential` | Del clientPayload |
| `success_action_status` | 201 |
| `success_action_redirect` | String vacío o URL de redirect |
| `file` | Archivo de video (siempre al final) |

> ⚠️ El campo `file` debe ser siempre el **último** en el form-data.

### 4.4 Subida de Archivo - Paso 2b: Upload desde Navegador (Frontend)

Para permitir que usuarios suban videos directamente desde el navegador de forma segura.

**Flujo de implementación:**

1. **Crear endpoint AJAX en backend**: Llama a la Credentials API (Paso 1) y devuelve `clientPayload`. Este endpoint debe verificar que el usuario está autenticado y tiene permiso de subida. **NUNCA** llamar la Credentials API directamente desde el cliente.

2. **Instalar librería de upload en frontend** (algunas opciones):
   - Dropzone.js
   - Blueimp (jQuery)
   - Fine Uploader
   - HTML Form nativo

3. **Ejemplo con Dropzone.js:**

```html
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css">
</head>
<body>
  <div id="myId" class="dropbox">Subir videos</div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"></script>
  <script src="./myapp.js"></script>
</body>
</html>
```

```javascript
// Obtener credenciales del backend
let getCredentials = function(data, callback) {
  fetch('./upload-endpoint', { method: 'POST', body: data })
    .then(res => res.json())
    .then(uploadCreds => callback(uploadCreds))
    .catch(e => done(e.message));
};

// Configurar Dropzone
Dropzone.autoDiscover = false;
var myDropzone = new Dropzone("div#myId", {
  url: "#",
  maxFilesize: 5120, // MB
  acceptedFiles: 'video/*',
  accept: function(file, done) {
    getCredentials({}, () => {
      this.awsOptions = uploadCreds;
      this.options.url = this.awsOptions.uploadLink;
      done();
    });
  },
  init: function() {
    this.on("sending", function(file, xhr, formData) {
      formData.append("x-amz-credential", this.awsOptions['x-amz-credential']);
      formData.append("x-amz-algorithm", this.awsOptions['x-amz-algorithm']);
      formData.append("x-amz-date", this.awsOptions['x-amz-date']);
      formData.append("x-amz-signature", this.awsOptions['x-amz-signature']);
      formData.append("key", this.awsOptions['key']);
      formData.append("policy", this.awsOptions['policy']);
      formData.append("success_action_status", 201);
      formData.append("success_action_redirect", "");
    });
  }
});
```

> ⚠️ **Límite:** El tamaño máximo de archivo para esta API es **5GB**.

### 4.5 Subida de Archivo - Paso 3: Verificar Estado del Video

Después de subir, verificar el estado de procesamiento del video.

**Endpoint:**

```
GET https://dev.vdocipher.com/api/videos/{videoID}
```

**Estados posibles:**

| Estado | Descripción |
|--------|-------------|
| `Pre-Upload` | Se obtuvo la política de upload pero aún no se subió el archivo |
| `Queued` | Video subido, siendo codificado y cifrado |
| `ready` | Video listo para reproducción |

**Respuesta de ejemplo:**

```json
{
  "id": "1234567890",
  "title": "zoo.mp4",
  "description": "",
  "upload_time": 1519700000,
  "length": 25,
  "status": "ready",
  "posters": [
    {
      "width": 854,
      "height": 480,
      "posterUrl": "https://d1z78r8i505acl.cloudfront.net/poster/123456.480.jpeg"
    }
  ],
  "tags": ["etiqueta1", "etiqueta2"]
}
```

```bash
curl -X GET \
  https://dev.vdocipher.com/api/videos/{{videoID}} \
  -H 'Accept: application/json'
```

---

## 5. Gestión de Videos

### 5.1 Listar y Paginar Videos

**Endpoint:**

```
GET https://dev.vdocipher.com/api/videos
```

**Parámetros de query:**

| Parámetro | Descripción | Ejemplo |
|-----------|-------------|----------|
| `page` | Número de página (default: 1) | `?page=2` |
| `limit` | Videos por página (max: 40, default: 20) | `?limit=40` |
| `tags` | Filtrar por tags (separados por coma, case-sensitive) | `?tags=cursoA,modulo1` |
| `q` | Buscar por videoID o título | `?q=texto` |
| `folderId` | Listar solo videos de una carpeta | `?folderId=ID_CARPETA` |

**Respuesta:**

```json
{
  "count": 3,
  "rows": [
    {
      "id": "videoID",
      "title": "titulo del video",
      "description": "",
      "upload_time": 1519700000,
      "length": 120,
      "status": "ready",
      "posters": [...],
      "tags": ["tag1", "tag2"]
    }
  ]
}
```

> ⚠️ **No usar esta API directamente** como backend de solicitudes HTTP de usuarios finales en producción. Úsarla solo en cron jobs o procesos de sincronización (intervalo recomendado: 20 minutos o más).

```bash
curl -X GET \
  'https://dev.vdocipher.com/api/videos?q=query' \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5'
```

### 5.2 Eliminar Videos

**Endpoint:**

```
DELETE https://dev.vdocipher.com/api/videos?videos={videoID1},{videoID2}
```

- Se pueden eliminar múltiples videos separando los IDs por coma
- La reproducción puede continuar brevemente en caché después de eliminar
- Eliminar un video lo excluye del conteo de almacenamiento

```bash
curl -X DELETE \
  'https://dev.vdocipher.com/api/videos?videos=videoID1,videoID2' \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json'
```

### 5.3 Renombrar Videos

**Endpoint:**

```
POST https://dev.vdocipher.com/api/videos/{videoID}
```

```json
{
  "title": "nuevo-titulo"
}
```

```bash
curl -X POST \
  'https://dev.vdocipher.com/api/videos/TU_VIDEO_ID' \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"title": "Nuevo Título"}'
```

**Respuesta:** `{"message": "Successfully updated"}`

> 💡 Para evitar múltiples llamadas, es mejor pasar el título directamente al momento de subir el video o importarlo.

---

## 6. Carpetas (Folders)

Las carpetas son ubicaciones virtuales para organizar videos. A diferencia de los tags, un video solo puede pertenecer a **una sola carpeta**.

**Estructura de ejemplo:**

```
root/
├── Tutoriales/
│   ├── Lecciones Premium/
│   │   ├── Video 1.mp4
│   │   └── Video 2.mp4
│   ├── Episodio 1.mp4
│   └── Episodio 2.mp4
└── Grabaciones/
    ├── Grabacion 1.mkv
    └── Grabacion 2.flv
```

> El ID del folder raíz (top-level) es `"root"`

### 6.1 Listar Sub-carpetas

```
GET https://dev.vdocipher.com/api/videos/folders/:folderId
```

```bash
curl -X GET \
  https://dev.vdocipher.com/api/videos/folders/root \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5'
```

**Respuesta:**

```json
{
  "folderList": [
    {
      "id": "b3d9f19b3dc4xxxx",
      "name": "Tutoriales",
      "parent": "parentId",
      "videosCount": 270,
      "foldersCount": 2
    }
  ],
  "current": {"id": "currentId", "name": "Videos Publicados", "parent": "parentId"},
  "parent": {"id": "parentId", "name": "Todos los Videos", "parent": "grandparentId"}
}
```

### 6.2 Buscar Carpeta por Nombre

```
POST https://dev.vdocipher.com/api/videos/folders/search
```

```json
{
  "name": "texto de búsqueda",
  "searchExact": false
}
```

- `name`: texto a buscar (hasta 255 caracteres, requerido)
- `searchExact`: si `true`, busca coincidencia exacta; si `false` (default), busca carpetas que comiencen con ese texto

```bash
curl -X POST "https://dev.vdocipher.com/api/videos/folders/search" \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -d '{"name": "Tutoriales"}'
```

**Respuesta:**

```json
{
  "folders": [
    {
      "name": "Tutoriales Premium",
      "id": "abcdef1234567890",
      "folderPath": [
        {"id": "parentId", "name": "Cursos"}
      ],
      "createdAt": "2019-10-27T04:19:19.000Z"
    }
  ]
}
```

> La ruta `folderPath` está en orden inverso: el primer elemento es el padre inmediato. Para carpetas en el nivel raíz es un array vacío `[]`.

### 6.3 Crear Carpeta

```
POST https://dev.vdocipher.com/api/videos/folders
```

```json
{
  "name": "Nombre de la Carpeta",
  "parent": "parentFolderID"
}
```

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/folders \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"name": "Nueva Carpeta", "parent": "root"}'
```

**Respuesta:** `{"id": "newFolderId", "parent": null, "name": "Nueva Carpeta", "videosCount": 0, "foldersCount": 0}`

### 6.4 Eliminar Carpeta

```
DELETE https://dev.vdocipher.com/api/videos/folders/:folderId
```

> ⚠️ Eliminar una carpeta **NO elimina los videos** dentro de ella. Los videos se mueven a la carpeta padre.

```bash
curl -X DELETE \
  https://dev.vdocipher.com/api/videos/folders/FOLDER_ID \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json'
```

**Respuesta:** `{"message": "Folder Deleted"}`

### 6.5 Renombrar Carpeta

```
PUT https://dev.vdocipher.com/api/videos/folders/{folderID}
```

```json
{
  "name": "Nuevo Nombre de Carpeta"
}
```

```bash
curl -X PUT \
  https://dev.vdocipher.com/api/videos/folders/FOLDER_ID \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"name": "Nuevo Nombre"}'
```

**Respuesta:** `{"message": "Folder has been updated"}`

---

## 7. Tags (Etiquetas)

Los tags son palabras clave que facilitan la búsqueda y organización. Un video puede tener **múltiples tags** (a diferencia de las carpetas). Los tags son **case-sensitive**.

### 7.1 Buscar Videos por Tag

```
GET https://dev.vdocipher.com/api/videos?tags={tag}
```

```bash
curl -X GET \
  'https://dev.vdocipher.com/api/videos?tags=cursoA,modulo1' \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5'
```

> Múltiples tags separados por coma aplican un filtro AND (todos los tags deben estar presentes)

### 7.2 Agregar Tags a Videos

```
POST https://dev.vdocipher.com/api/videos/tags
```

```json
{
  "videos": ["videoID1", "videoID2"],
  "tags": ["tag1", "tag2"]
}
```

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/tags \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: application/json' \
  -d '{"videos": ["videoID1"], "tags": ["miTag"]}'
```

### 7.3 Reemplazar y Eliminar Tags

```
PUT https://dev.vdocipher.com/api/videos/tags
```

Reemplaza los tags existentes con los nuevos especificados:

```json
{
  "videos": ["videoID1", "videoID2"],
  "tags": ["nuevoTag1", "nuevoTag2"]
}
```

Para **eliminar todos los tags** de un video:

```json
{
  "videos": ["videoID1"],
  "tags": []
}
```

### 7.4 Listar Todos los Tags

```
GET https://dev.vdocipher.com/api/videos/tags
```

```bash
curl -X GET \
  https://dev.vdocipher.com/api/videos/tags \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5'
```

---

## 8. Captions y Posters

### 8.1 Obtener Información del Video y URLs de Poster

El poster es la imagen mostrada antes de que cargue el video. Por defecto se genera automáticamente desde el video.

```
GET https://dev.vdocipher.com/api/meta/{videoID}
```

```bash
curl -X GET \
  https://dev.vdocipher.com/api/meta/TU_VIDEO_ID \
  -H 'Accept: application/json'
```

**Respuesta:**

```json
{
  "title": "Waves.mp4",
  "duration": 21,
  "aspectRatio": 1.77778,
  "posters": [
    {"url": "https://cdn.cloudfront.net/poster/video.240.jpeg", "height": 240},
    {"url": "https://cdn.cloudfront.net/poster/video.480.jpeg", "height": 480},
    {"url": "https://cdn.cloudfront.net/poster/video.720.jpeg", "height": 720}
  ]
}
```

> Las URLs de poster son permanentes y están respaldadas por CDN. Se pueden guardar directamente para mostrar thumbnails.

### 8.2 Listar Todos los Archivos de un Video

Lista todos los archivos asociados al video (posters, subtítulos, archivo original).

```
GET https://dev.vdocipher.com/api/videos/{videoId}/files/
```

**Campos en la respuesta por archivo:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID único del archivo |
| `size` | integer | Tamaño en bytes |
| `time` | string | Hora de subida (ISO 8601 UTC) |
| `enabled` | integer | 0 o 1 (0 = poster inactivo) |
| `format` | enum | `jpeg` para posters, `vtt` para subtítulos |
| `height` | integer/null | Solo para posters y archivo original |
| `lang` | string | Código ISO-639-1 del idioma (para subtítulos) |
| `isDownloadable` | boolean | Si el owner puede descargarlo |
| `isDeletable` | boolean | Si el owner puede borrarlo |

### 8.3 Subir Nuevo Poster

```
POST https://dev.vdocipher.com/api/videos/{videoID}/files
```

```bash
curl -X POST \
  https://dev.vdocipher.com/api/videos/TU_VIDEO_ID/files \
  -H 'Accept: application/json' \
  -H 'Authorization: Apisecret a1b2c3d4e5' \
  -H 'Content-Type: multipart/form-data' \
  -F file=@/ruta/imagen.png
```

### 8.4 Subir Captions (Subtítulos)

```
POST https://dev.vdocipher.com/api/videos/{videoId}/files/?language={codigoISO}
```

- Formato del archivo: **VTT**
- Parámetro `language`: código ISO 639-1 (ej: `en`, `es`, `fr`, `ar`)

```javascript
// Ejemplo con Node.js
const uploadSubtitle = (videoId, filePath, language) => {
  return rp({
    url: `https://dev.vdocipher.com/api/videos/${videoId}/files/`,
    method: 'POST',
    qs: { language: language },
    headers: { 'Content-type': 'multipart/form-data', authorization: `Apisecret ${apiKey}` },
    formData: { file: { value: fs.createReadStream(filePath), options: {} } },
    json: true
  });
};

uploadSubtitle('videoId', './subtitulos.vtt', 'es').then(console.log);
```

**Respuesta:** `{"id": 12090067, "time": "2021-04-04T09:06:26.925Z", "size": "5 kB", "lang": "es"}`

### 8.5 Eliminar Captions o Poster

> ⚠️ **DESTRUCTIVO**: Los archivos eliminados se borran casi inmediatamente. Probar con cuenta de prueba primero.

```
DELETE https://dev.vdocipher.com/api/videos/{videoId}/files/{id}
```

- Usar el `id` obtenido del API de listado de archivos (Sección 8.2)

```javascript
// Ejemplo con Node.js
const deleteFile = async (videoId, fileId) => {
  await rp({
    url: `https://dev.vdocipher.com/api/videos/${videoId}/files/${fileId}`,
    method: 'DELETE',
    headers: { authorization: `Apisecret ${apiKey}` },
    json: true
  });
};
```

**Respuesta:** `{"message": "Deleted"}`

---

## 9. Web Hooks

Permite recibir notificaciones programáticas cuando cambia el estado de un video.

**Eventos soportados:**

- `video:ready`: El video está listo para reproducción
- `video:readyall`: Todos los videos están listos

**Tipo soportado:** `http`

**El webhook envía un POST a tu URL con este body:**

```json
{
  "hookId": "5d56a5234f99xxxx",
  "event": "video:ready",
  "time": 1538330269833,
  "payload": {
    "id": "8cf4129200a4xxxx",
    "title": "Mi Video.mp4",
    "upload_time": 1538329973,
    "tags": null,
    "length": 60,
    "status": "ready"
  }
}
```

### Listar Webhooks

```bash
curl -i https://dev.vdocipher.com/api/hooks/ \
  -H 'Authorization: Apisecret TU_API_KEY' \
  -H 'Content-Type: application/json'
```

### Crear Webhook

```bash
curl -i -X POST https://dev.vdocipher.com/api/hooks/ \
  -H 'Authorization: Apisecret TU_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"type": "http", "value": "https://tuservidor.com/webhook", "event": "video:ready", "status": 1}'
```

### Eliminar Webhook

```bash
curl -i -X DELETE https://dev.vdocipher.com/api/hooks/ \
  -H 'Authorization: Apisecret TU_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"uuid": "ID_DEL_HOOK"}'
```

---

## 10. Consumo de Ancho de Banda

Permite obtener el consumo de ancho de banda por video en un día específico.

**Endpoint:**

```
POST https://dev.vdocipher.com/api/account/video-usage
```

```json
{
  "date": "2024-01-15"
}
```

**Notas importantes:**

- El cálculo es en UTC, el registro del día anterior se completa al día siguiente a las 04:30 UTC
- Usar después de las **05:30 UTC** para datos del día anterior
- Ejecutar como **cron job diario** entre las 06:00 y 10:00 UTC
- El ancho de banda se reporta en **kilobytes**

**Respuesta (formato CSV):**

```csv
video,bandwidth
bd291afc082dd14391c3a4a112827918,123123
c6651e597c783780f0cc026129c09025,4352345
```

```bash
curl -X POST https://dev.vdocipher.com/api/account/video-usage \
  -H 'Authorization: Apisecret TU_API_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"date": "2024-01-15"}' > bandwidth_2024-01-15.csv
```

---

## 11. Player (Reproductor)

### 11.1 Embed con iFrame

Código de embed estándar para insertar el reproductor en una página web:

```html
<iframe
  src="https://player.vdocipher.com/v2/?otp=[[OTP]]&playbackInfo=[[PLAYBACKINFO]]"
  style="border:0;width:720px;height:405px"
  allow="encrypted-media"
  allowfullscreen
></iframe>
```

**Variables:**

| Variable | Descripción |
|----------|-------------|
| `otp` | Token OTP obtenido del backend |
| `playbackInfo` | Token playbackInfo obtenido junto con el OTP |

**Atributos importantes:**

- `allow="encrypted-media"`: Requerido para reproducir video cifrado en navegadores basados en Chrome
- `allowfullscreen`: Requerido para el botón de pantalla completa

**Embed responsive:**

```html
<div style="padding-top:56.25%;position:relative;">
  <iframe
    src="https://player.vdocipher.com/v2/?otp=OTP&playbackInfo=PLAYBACKINFO"
    style="border:0;max-width:100%;position:absolute;top:0;left:0;height:100%;width:100%;"
    allowfullscreen="true"
    allow="encrypted-media"
  ></iframe>
</div>
```

> El `padding-top: 56.25%` corresponde al aspect ratio 16:9 (9/16 = 0.5625)

### 11.2 Embed con Web Component

Alternativa moderna al iframe, recomendada para nuevas implementaciones:

```html
<!-- Agregar el script en el head o antes de usar el componente -->
<script
  data-cfasync="false"
  data-no-defer="1"
  src="https://player.vdocipher.com/v2/player.js"
></script>

<!-- Usar el componente donde se necesite el video -->
<vdocipher-player
  otp="[[OTP]]"
  playbackInfo="[[PLAYBACKINFO]]"
  style="width: 400px; height: auto;"
></vdocipher-player>
```

> ⚠️ El `<script>` debe cargarse **antes** de que el elemento `<vdocipher-player>` se renderice en el DOM.

**Ejemplos con opciones:**

```html
<!-- Autoplay -->
<vdocipher-player otp="..." playbackInfo="..." autoplay="true"></vdocipher-player>

<!-- Modo lite (no carga medios hasta que el usuario hace click) -->
<vdocipher-player otp="..." playbackInfo="..." litemode="true"></vdocipher-player>

<!-- Caption por defecto en español -->
<vdocipher-player otp="..." playbackInfo="..." cclanguage="es"></vdocipher-player>

<!-- Video en loop -->
<vdocipher-player otp="..." playbackInfo="..." loop="true"></vdocipher-player>
```

**Obtener referencia al player desde Web Component:**

```javascript
const myWebcomponent = document.querySelector('vdocipher-player');
myWebcomponent.getPlayer().then((player) => {
  player.video.addEventListener('play', () => console.log('Reproduciendo'));
  player.api.getTotalPlayed().then(data => console.log('Visto:', data));
});
```

### 11.3 Opciones de Configuración del Player

Se pueden pasar como URL parameters en el `src` del iframe o como atributos en el web component:

| Parámetro | Default | Valores | Descripción |
|-----------|---------|---------|-------------|
| `primaryColor` | FF0000 | ej: `FFFF00` o `yellow` | Color primario del player (HEX sin # en mayúsculas, o nombre en minúsculas) |
| `ccLanguage` | Idioma del navegador | ej: `es`, `en`, `ar` | Idioma por defecto para los subtítulos |
| `litemode` | false | `true` / `false` | Carga en modo lite: no carga archivos hasta que el usuario hace click |
| `autoplay` | false | `true` / `false` | Habilita autoplay (el video se silencia automáticamente) |
| `controls` | on | `on`, `off`, `native` | Muestra/oculta los controles UI del player |
| `loop` | false | `true` / `false` | Repite el video automáticamente al terminar |

**Ejemplo con parámetros en iframe:**

```html
<iframe
  src="https://player.vdocipher.com/v2/?otp=OTP&playbackInfo=PLAYBACKINFO&primaryColor=4245EF&litemode=true"
  style="border:0;width:720px;height:405px"
  allow="encrypted-media"
  allowfullscreen
></iframe>
```

### 11.4 Versiones del Player

- **Recomendado:** `https://player.vdocipher.com/v2/` — recibe todas las actualizaciones de la serie v2
- **No recomendado:** Especificar versión exacta (`v2.3.15`) ya que requiere actualización manual
- En caso de problemas de seguridad críticos, VdoCipher puede publicar actualizaciones directamente

---

## 12. API de JavaScript del Player

### 12.1 Configuración Inicial

```html
<!-- 1. Agregar el script de la API -->
<script src="https://player.vdocipher.com/v2/api.js"></script>

<!-- 2. Embed del iframe -->
<iframe
  id="mi-player"
  src="https://player.vdocipher.com/v2/?otp=OTP&playbackInfo=PLAYBACKINFO"
  frameborder="0"
  allow="encrypted-media"
  allowfullscreen
></iframe>

<script>
  const iframe = document.querySelector('iframe');
  // 3. Obtener instancia del player
  const player = VdoPlayer.getInstance(iframe);

  // Usar HTML5 Video API
  player.video.addEventListener('play', () => console.log('Reproduciendo'));
  player.video.addEventListener('ended', () => console.log('Terminó'));

  // Usar VdoCipher Custom API
  player.api.getTotalPlayed().then(data => console.log('Segundos vistos:', data));
</script>
```

**La instancia `player` tiene dos propiedades:**

```json
{
  "video": "(HTMLVideoElement proxy)",
  "api": "(VdoCipher Custom API)"
}
```

### 12.2 HTML5 Video API (player.video)

**Operaciones básicas:**

```javascript
const player = VdoPlayer.getInstance(iframe);

// Reproducir
player.video.play();

// Pausar
player.video.pause();

// Posición actual (en segundos)
player.video.currentTime;

// Duración total
player.video.duration;

// Volumen (0.0 - 1.0)
player.video.volume;

// Silenciar
player.video.muted = true;
```

**Eventos disponibles:**

```javascript
player.video.addEventListener('play', () => {});
player.video.addEventListener('pause', () => {});
player.video.addEventListener('ended', () => {});
player.video.addEventListener('timeupdate', () => {});
player.video.addEventListener('loadeddata', () => {});
player.video.addEventListener('volumechange', () => {});
player.video.addEventListener('seeking', () => {});
player.video.addEventListener('seeked', () => {});
player.video.addEventListener('error', () => {});

// Remover listener
const handler = () => console.log('pausado');
player.video.addEventListener('pause', handler);
player.video.removeEventListener('pause', handler);
```

> ⚠️ `player.video` es un **proxy**, no un elemento `<video>` real. Todos los métodos retornan Promises.

### 12.3 VdoCipher Custom API (player.api)

| Método | Argumentos | Retorna | Descripción |
|--------|-----------|---------|-------------|
| `getTotalPlayed()` | - | `Promise<number>` | Segundos totales vistos (incluye repeticiones) |
| `getTotalCovered()` | - | `Promise<number>` | Segundos únicos cubiertos |
| `getTotalCoveredArray()` | - | `Promise<number[]>` | Array de cobertura total |
| `getCaptionLanguages()` | - | `Promise<{visible, languages}>` | Lista de subtítulos disponibles con estado de visibilidad |
| `setCaptionLanguage(langCode)` | `string` o `{id}` | `Promise<void>` | Establece el idioma de subtítulos |
| `hideCaptions()` | - | `Promise<void>` | Oculta los subtítulos |
| `getVideoQualities()` | - | `Promise<{adaptive, qualities}>` | Lista de calidades disponibles |
| `setVideoQuality(id)` | `number` | `Promise<void>` | Cambia la calidad del video |
| `enableAdaptiveVideo()` | - | `Promise<void>` | Activa el cambio automático de calidad |
| `setFullscreen(status)` | `boolean` | `Promise<boolean>` | Entra/sale de pantalla completa |
| `addEventListener(event, handler)` | `string, function` | función cancel | Escucha eventos personalizados |
| `loadVideo({otp, playbackInfo})` | objeto | `Promise<void>` | Carga otro video en el mismo player (útil para playlists) |

**Eventos personalizados de `player.api.addEventListener`:**

```javascript
const cancel = player.api.addEventListener('fullscreenChange', (data) => {
  console.log('Pantalla completa:', data);
});

// Eventos disponibles:
// - fullscreenChange
// - videoAdaptivenessChange
// - videoQualityChange
// - statusChange
// - captionVisibilityChange
// - captionLanguageChange

// Para cancelar la suscripción:
cancel();
```

**Ejemplo: Cargar otro video (playlist):**

```javascript
// Cargar un segundo video en el mismo player
player.api.loadVideo({
  otp: 'OTP_DEL_SEGUNDO_VIDEO',
  playbackInfo: 'PLAYBACKINFO_DEL_SEGUNDO_VIDEO'
}).then(() => console.log('Segundo video cargado'));
```

**Ejemplos comunes de uso:**

```javascript
// Obtener progreso del usuario
player.api.getTotalPlayed().then(seconds => {
  console.log(`El usuario ha visto ${seconds} segundos`);
});

// Cambiar subtítulos al español
player.api.setCaptionLanguage('es');

// Cambiar a calidad 480p
player.api.getVideoQualities().then(({qualities}) => {
  const q480 = qualities.find(q => q.height === 480);
  if (q480) player.api.setVideoQuality(q480.id);
});

// Forzar pantalla completa
player.api.setFullscreen(true);
```

---

## Resumen de Endpoints

| Operación | Método | Endpoint |
|-----------|--------|----------|
| Generar OTP | POST | `https://dev.vdocipher.com/api/videos/{videoID}/otp` |
| Importar video desde URL | PUT | `https://dev.vdocipher.com/api/videos/importUrl` |
| Obtener credenciales de upload | PUT | `https://dev.vdocipher.com/api/videos?title={titulo}` |
| Verificar estado de video | GET | `https://dev.vdocipher.com/api/videos/{videoID}` |
| Listar videos | GET | `https://dev.vdocipher.com/api/videos` |
| Eliminar videos | DELETE | `https://dev.vdocipher.com/api/videos?videos={id1},{id2}` |
| Renombrar video | POST | `https://dev.vdocipher.com/api/videos/{videoID}` |
| Metadatos y posters | GET | `https://dev.vdocipher.com/api/meta/{videoID}` |
| Listar archivos de video | GET | `https://dev.vdocipher.com/api/videos/{videoID}/files/` |
| Subir poster | POST | `https://dev.vdocipher.com/api/videos/{videoID}/files` |
| Subir caption (VTT) | POST | `https://dev.vdocipher.com/api/videos/{videoID}/files/?language={lang}` |
| Eliminar caption/poster | DELETE | `https://dev.vdocipher.com/api/videos/{videoID}/files/{fileId}` |
| Buscar videos por tag | GET | `https://dev.vdocipher.com/api/videos?tags={tag}` |
| Agregar tags | POST | `https://dev.vdocipher.com/api/videos/tags` |
| Reemplazar tags | PUT | `https://dev.vdocipher.com/api/videos/tags` |
| Listar todos los tags | GET | `https://dev.vdocipher.com/api/videos/tags` |
| Listar sub-carpetas | GET | `https://dev.vdocipher.com/api/videos/folders/{folderId}` |
| Buscar carpeta | POST | `https://dev.vdocipher.com/api/videos/folders/search` |
| Crear carpeta | POST | `https://dev.vdocipher.com/api/videos/folders` |
| Eliminar carpeta | DELETE | `https://dev.vdocipher.com/api/videos/folders/{folderId}` |
| Renombrar carpeta | PUT | `https://dev.vdocipher.com/api/videos/folders/{folderID}` |
| Listar webhooks | GET | `https://dev.vdocipher.com/api/hooks/` |
| Crear webhook | POST | `https://dev.vdocipher.com/api/hooks/` |
| Eliminar webhook | DELETE | `https://dev.vdocipher.com/api/hooks/` |
| Consumo de ancho de banda | POST | `https://dev.vdocipher.com/api/account/video-usage` |

---

*Documentación generada desde https://www.vdocipher.com/docs/server/ — API v3*
