# CursosUmme — Plataforma E-Learning

Plataforma de formación online privada para la academia Umme. Permite gestionar cursos, alumnos, materiales multimedia y soporte, con panel de administración completo y área protegida para alumnos.

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | [Astro](https://astro.build/) v5 + TypeScript |
| Estilos | CSS puro con variables CSS |
| API | PHP 8 + PDO (MySQL) |
| Base de datos | MySQL / MariaDB (IONOS hosting) |
| Despliegue | SFTP a IONOS / 1&1 |

---

## Funcionalidades

### Alumnos
- **Login** con sesión en `sessionStorage` + token Bearer (expira 8 h)
- **Mis cursos**: cursos asignados agrupados por categoría/pack con imagen de portada y barra de progreso
- **Reproductor de vídeo**: streaming seguro con marca de agua del email, bloqueo de descarga y clic derecho
- **Progreso**: porcentaje de temas completados; se marca automáticamente al terminar un vídeo
- **Materiales**: descarga de documentos, PDFs y audios adjuntos a cada tema
- **Perfil**: cambio de foto, nombre, email y contraseña
- **Soporte**: creación de tickets, conversación con el admin, cierre de consulta

### Administradores
- **Dashboard**: estadísticas en tiempo real (alumnos, cursos activos, packs, tickets abiertos)
- **Alumnos**: listado con búsqueda, creación, edición, asignación de cursos y control de fechas de acceso
- **Cursos**: CRUD completo con título, descripción con editor enriquecido (H2/H3/negrita/listas), imagen de portada, nivel, etiqueta, pack y colores personalizables
- **Temas y materiales**: añadir/reordenar/eliminar temas; subida de vídeos (hasta 2 GB) y documentos/audios (hasta 100 MB) con barra de progreso real
- **Soporte**: ver todos los tickets, responder, cambiar estado (abierto → respondido → cerrado), eliminar conversaciones
- **Log de cambios**: auditoría de todas las acciones (alumnos, cursos, tickets)
- **Log de errores JS**: captura global de errores de frontend de todos los usuarios, con filtros y detalle
- **Perfil admin**: editar nombre, apellidos y email
- **Vista alumno**: previsualizar la plataforma tal como la ve un alumno

---

## Estructura del proyecto

```
cursosumme/
├── public/
│   ├── api/                         # API REST en PHP
│   │   ├── .user.ini                # Límites PHP (upload_max_filesize, post_max_size, memoria)
│   │   ├── db-config.php            # Credenciales BD + SETUP_KEY — NO versionar
│   │   ├── db-connect.php           # Conexión PDO + requireAuth/requireAdmin (Bearer token)
│   │   ├── log-helper.php           # Helper de auditoría
│   │   ├── login.php                # Autenticación con bcrypt + rate limiting + emisión de token
│   │   ├── alumnos.php              # CRUD alumnos
│   │   ├── cursos.php               # CRUD cursos (incluye imagen de portada)
│   │   ├── temas.php                # CRUD temas
│   │   ├── materiales.php           # CRUD materiales
│   │   ├── upload.php               # Subida de vídeos (2 GB) y documentos/audios (100 MB)
│   │   ├── upload-imagen.php        # Subida de imágenes de portada de curso (5 MB)
│   │   ├── video.php                # Proxy seguro de streaming (range requests)
│   │   ├── mis-cursos.php           # Cursos del alumno (GET)
│   │   ├── progreso.php             # Progreso por tema (GET/POST)
│   │   ├── tickets.php              # Sistema de soporte (GET/POST/PUT/DELETE)
│   │   ├── logs.php                 # Log de auditoría (GET)
│   │   ├── log-error.php            # Captura de errores JS del frontend (POST)
│   │   ├── errores.php              # Lectura/borrado de errores JS (GET/DELETE)
│   │   ├── perfil-admin.php         # Perfil admin (GET/PUT)
│   │   ├── cambiar-password.php     # Cambio de contraseña con bcrypt (POST)
│   │   ├── foto-perfil.php          # Foto de perfil (GET/POST)
│   │   ├── info-limites.php         # Diagnóstico de límites PHP del servidor
│   │   └── setup.php                # Migraciones de BD (protegido con ?key=SETUP_KEY)
│   └── uploads/                     # Archivos subidos — en .gitignore
│       ├── videos/
│       ├── documentos/
│       └── imagenes/                # Portadas de curso y fotos de perfil
├── src/
│   ├── components/
│   │   ├── CabeceraApp.astro        # Cabecera alumno
│   │   ├── PanelDetalle.astro       # Panel lateral de detalle de curso
│   │   ├── TarjetaCurso.astro       # Tarjeta de curso en listado
│   │   ├── TarjetaLogin.astro       # Formulario de login
│   │   ├── Boton.astro
│   │   ├── CampoFormulario.astro
│   │   ├── FondoLogin.astro
│   │   └── PiePagina.astro
│   ├── layouts/
│   │   ├── Plantilla.astro          # Layout base (alumno)
│   │   └── PlantillaAdmin.astro     # Layout con sidebar (admin)
│   ├── pages/
│   │   ├── index.astro              # Login (/)
│   │   ├── inicio.astro             # Panel del alumno (/inicio)
│   │   ├── perfil.astro             # Perfil alumno (/perfil)
│   │   ├── soporte.astro            # Soporte alumno (/soporte)
│   │   ├── recuperar-contrasena.astro
│   │   └── admin/
│   │       ├── index.astro          # Dashboard (/admin)
│   │       ├── cursos.astro         # Listado de cursos (/admin/cursos)
│   │       ├── tickets.astro        # Soporte admin (/admin/tickets)
│   │       ├── perfil.astro         # Perfil admin (/admin/perfil)
│   │       ├── logs.astro           # Log de cambios (/admin/logs)
│   │       ├── errores.astro        # Log de errores JS (/admin/errores)
│   │       ├── alumnos/
│   │       │   ├── index.astro      # Listado alumnos
│   │       │   ├── crear-alumno.astro
│   │       │   └── detalle.astro
│   │       └── cursos/
│   │           ├── crear-curso.astro
│   │           └── editar.astro     # Editor de temas y materiales
│   └── styles/                      # CSS por componente / página
├── scripts/
│   └── deploy.mjs                   # Script de despliegue SFTP
├── base-de-datos/                   # SQL de estructura inicial
├── .env.example
├── astro.config.mjs
├── tsconfig.json
└── package.json
```

---

## Base de datos

| Tabla | Descripción |
|-------|------------|
| `usuarios` | Alumnos y admins (email, hash bcrypt, rol, fechas de acceso, `token_sesion`, `token_expira`) |
| `cursos` | Cursos (título, descripción HTML, imagen de portada, etiqueta, pack, nivel, colores) |
| `temas` | Temas de cada curso (título, descripción HTML, duración, orden) |
| `materiales` | Archivos por tema (tipo: video/documento, ruta, tamaño KB) |
| `usuarios_cursos` | Relación alumno ↔ curso asignado |
| `progresos` | Temas vistos por cada alumno |
| `tickets` | Consultas de soporte de los alumnos |
| `ticket_respuestas` | Conversación admin ↔ alumno en cada ticket |
| `logs` | Auditoría de acciones (creación, edición, eliminación) |
| `errores` | Errores JS capturados del frontend (tipo, mensaje, url, línea, stack, usuario) |
| `login_intentos` | Intentos de login por IP para rate limiting (5 fallos = 15 min bloqueo) |

---

## Autenticación

### Contraseñas
- Hasheadas con **bcrypt** (`password_hash` con `PASSWORD_BCRYPT`, cost 12) en el servidor
- Migración transparente desde SHA-256 legacy: al loguearse un usuario antiguo se detecta el hash (64 hex sin prefijo `$2y$`), se verifica y se re-hashea a bcrypt automáticamente
- Contraseña por defecto para nuevos alumnos: `Umme@2024`

### Rate limiting
- Tabla `login_intentos` rastrea intentos por IP
- Tras **5 fallos** consecutivos, la IP queda bloqueada durante **15 minutos**
- Un login correcto resetea el contador

### Tokens de sesión
- Al hacer login el servidor genera un token aleatorio de 64 caracteres (`bin2hex(random_bytes(32))`)
- El token se guarda en `usuarios.token_sesion` con `token_expira` a **8 horas**
- El cliente lo almacena en `sessionStorage` (key: `umme_session`) junto con `userId`, `rol`, `nombre`, `email` y `exp`
- Todas las llamadas a la API envían el header `Authorization: Bearer <token>` (ver helper `authHeaders()` en `src/lib/auth.ts`)
- Los endpoints sensibles del backend validan el token con `requireAuth($pdo)` o `requireAdmin($pdo)` (en `db-config.php`)
- Al cerrar pestaña o navegador, `sessionStorage` desaparece — el token queda huérfano en BD hasta que expire

---

## Roles y accesos

| Ruta | Alumno | Admin |
|------|--------|-------|
| `/` (login) | ✓ | ✓ |
| `/inicio` | ✓ | ✓ (modo vista) |
| `/perfil` | ✓ | — |
| `/soporte` | ✓ | — |
| `/admin/**` | ✗ | ✓ |

---

## Puesta en marcha

### Requisitos
- Node.js 18+
- Servidor PHP 8 + MySQL (o MariaDB)

### 1. Instalar dependencias

```bash
npm install
```

### 2. Configurar BD

Crear `public/api/db-config.php` (no está en el repositorio):

```php
<?php
define('DB_HOST',     'tu-host-mysql');
define('DB_NOMBRE',   'nombre_bd');
define('DB_USUARIO',  'usuario_bd');
define('DB_PASSWORD', 'contraseña_bd');
define('SETUP_KEY',   'una_clave_secreta_larga');
```

`SETUP_KEY` protege la ejecución de `setup.php`: sin `?key=...` el endpoint devuelve 403.

### 3. Inicializar tablas

Acceder una vez a `/api/setup.php?key=<SETUP_KEY>` en el servidor para crear todas las tablas y migraciones (incluye `errores`, `login_intentos` y las columnas `token_sesion`/`token_expira`). Es seguro ejecutarlo varias veces (`IF NOT EXISTS`).

### 4. Desarrollo local

```bash
npm run dev
```

Las llamadas a `/api/*.php` apuntan al servidor real. Se necesita conexión o un servidor PHP local con la misma BD.

### 5. Build de producción

```bash
npm run build
```

Genera `dist/` con todos los archivos optimizados.

---

## Despliegue

```bash
npm run deploy
```

Hace build y sube `dist/` al servidor por SFTP automáticamente.

**Importante:** al modificar cualquier `.astro`, los nombres de los bundles en `dist/_astro/` cambian (llevan hash del contenido). Siempre desplegar `_astro/` completo junto con los HTML modificados.

---

## Límites de subida

| Tipo | Límite en nuestro código | Notas |
|------|--------------------------|-------|
| Vídeo | 2 GB | Validación en cliente y servidor |
| Documento / audio | 100 MB | Validación en cliente y servidor |
| Imagen de portada de curso | 5 MB | Solo jpg/jpeg/png/webp |
| Foto de perfil | (gestionada por `foto-perfil.php`) | |

El límite **real** viene impuesto por PHP del servidor (`upload_max_filesize`, `post_max_size`). En IONOS hosting compartido el valor por defecto suele ser 128 MB. El archivo [`public/api/.user.ini`](public/api/.user.ini) solicita 2048 MB / 512 MB de memoria / 600 s de timeout — IONOS lo recorta al máximo permitido por tu plan.

Para comprobar los límites efectivos en producción, acceder a:

```
https://<tu-dominio>/api/info-limites.php
```

---

## Scripts disponibles

| Comando | Descripción |
|---------|------------|
| `npm run dev` | Servidor de desarrollo en `http://localhost:4321` |
| `npm run build` | Genera archivos optimizados en `dist/` |
| `npm run preview` | Previsualiza el build de producción |
| `npm run deploy` | Build + subida SFTP al servidor |

---

## Seguridad

- Contraseñas hasheadas con **bcrypt** (cost 12), nunca en texto plano; migración transparente desde SHA-256 legacy
- **Rate limiting** de login: 5 fallos por IP bloquean 15 minutos (tabla `login_intentos`)
- Autenticación vía **token Bearer** de 64 caracteres emitido por el servidor y validado en cada endpoint sensible (`requireAuth` / `requireAdmin`)
- Sesión en `sessionStorage` (no persiste al cerrar el navegador), token expira a las **8 horas**
- Vídeos servidos siempre por proxy PHP (`video.php`) que verifica acceso antes de enviar los bytes
- Soporte de range requests para streaming correcto en todos los navegadores
- Marca de agua del email del alumno superpuesta en los vídeos
- Bloqueo de clic derecho y arrastre en el reproductor
- Área `/admin` solo accesible para usuarios con `rol = 'admin'`
- `setup.php` protegido con `SETUP_KEY`: sin la clave devuelve 403
- Captura global de errores JS del frontend (`window.onerror` + `unhandledrejection`) en la tabla `errores`, visible para admins
- Archivos sensibles (`db-config.php`, `uploads/`, `.env`) excluidos del repositorio

---

## Más información

- [Documentación oficial de Astro](https://docs.astro.build/)
- [PDO — PHP Data Objects](https://www.php.net/manual/es/book.pdo.php)
