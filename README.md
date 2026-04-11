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
- **Login** con sesión en `sessionStorage` (expira 8 h)
- **Mis cursos**: cursos asignados agrupados por categoría/pack con barra de progreso
- **Reproductor de vídeo**: streaming seguro con marca de agua del email, bloqueo de descarga y clic derecho
- **Progreso**: porcentaje de temas completados; se marca automáticamente al terminar un vídeo
- **Materiales**: descarga de documentos, PDFs y audios adjuntos a cada tema
- **Perfil**: cambio de foto, nombre, email y contraseña
- **Soporte**: creación de tickets, conversación con el admin, cierre de consulta

### Administradores
- **Dashboard**: estadísticas en tiempo real (alumnos, cursos activos, packs, tickets abiertos)
- **Alumnos**: listado con búsqueda, creación, edición, asignación de cursos y control de fechas de acceso
- **Cursos**: CRUD completo con título, descripción, nivel, etiqueta, pack y colores personalizables
- **Temas y materiales**: añadir/reordenar/eliminar temas; subida de vídeos (hasta 500 MB), documentos y audios (hasta 50 MB)
- **Soporte**: ver todos los tickets, responder, cambiar estado (abierto → respondido → cerrado), eliminar conversaciones
- **Log de cambios**: auditoría de todas las acciones (alumnos, cursos, tickets)
- **Perfil admin**: editar nombre, apellidos y email
- **Vista alumno**: previsualizar la plataforma tal como la ve un alumno

---

## Estructura del proyecto

```
cursosumme/
├── public/
│   ├── api/                         # API REST en PHP
│   │   ├── db-config.php            # Credenciales BD — NO versionar
│   │   ├── db-connect.php           # Conexión PDO centralizada
│   │   ├── log-helper.php           # Helper de auditoría
│   │   ├── login.php                # Autenticación (POST)
│   │   ├── alumnos.php              # CRUD alumnos
│   │   ├── cursos.php               # CRUD cursos
│   │   ├── temas.php                # CRUD temas
│   │   ├── materiales.php           # CRUD materiales
│   │   ├── upload.php               # Subida de vídeos, documentos y audios
│   │   ├── video.php                # Proxy seguro de streaming
│   │   ├── mis-cursos.php           # Cursos del alumno (GET)
│   │   ├── progreso.php             # Progreso por tema (GET/POST)
│   │   ├── tickets.php              # Sistema de soporte (GET/POST/PUT/DELETE)
│   │   ├── logs.php                 # Log de auditoría (GET)
│   │   ├── perfil-admin.php         # Perfil admin (GET/PUT)
│   │   ├── cambiar-password.php     # Cambio de contraseña (POST)
│   │   ├── foto-perfil.php          # Foto de perfil (GET/POST)
│   │   └── setup.php               # Migraciones de BD (ejecutar una vez)
│   └── uploads/                     # Archivos subidos — en .gitignore
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
| `usuarios` | Alumnos y admins (email, hash SHA-256, rol, fechas de acceso) |
| `cursos` | Cursos (título, descripción, etiqueta, pack, nivel, colores) |
| `temas` | Temas de cada curso (título, descripción, duración, orden) |
| `materiales` | Archivos por tema (tipo: video/documento, ruta, tamaño KB) |
| `usuarios_cursos` | Relación alumno ↔ curso asignado |
| `progresos` | Temas vistos por cada alumno |
| `tickets` | Consultas de soporte de los alumnos |
| `ticket_respuestas` | Conversación admin ↔ alumno en cada ticket |
| `logs` | Auditoría de acciones (creación, edición, eliminación) |

---

## Autenticación

- La contraseña se hashea con **SHA-256** en el cliente (Web Crypto API) antes de enviarse
- El backend compara el hash almacenado en BD
- La sesión se guarda en `sessionStorage` (key: `umme_session`) con **8 horas de expiración**
- Al cerrar pestaña o navegador, la sesión desaparece automáticamente
- Cada página protegida verifica la sesión antes de cargar

**Contraseña por defecto para nuevos alumnos:** `Umme@2024`

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
```

### 3. Inicializar tablas

Acceder una vez a `/api/setup.php` en el servidor para crear todas las tablas y migraciones. Es seguro ejecutarlo varias veces (`IF NOT EXISTS`).

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

## Scripts disponibles

| Comando | Descripción |
|---------|------------|
| `npm run dev` | Servidor de desarrollo en `http://localhost:4321` |
| `npm run build` | Genera archivos optimizados en `dist/` |
| `npm run preview` | Previsualiza el build de producción |
| `npm run deploy` | Build + subida SFTP al servidor |

---

## Seguridad

- Contraseñas almacenadas como **hashes SHA-256**, nunca en texto plano
- Sesión en `sessionStorage` (no persiste al cerrar el navegador), expira a las **8 horas**
- Vídeos servidos siempre por proxy PHP (`video.php`) que verifica acceso antes de enviar los bytes
- Soporte de range requests para streaming correcto en todos los navegadores
- Marca de agua del email del alumno superpuesta en los vídeos
- Bloqueo de clic derecho y arrastre en el reproductor
- Área `/admin` solo accesible para usuarios con `rol = 'admin'`
- Archivos sensibles (`db-config.php`, `uploads/`, `.env`) excluidos del repositorio

---

## Más información

- [Documentación oficial de Astro](https://docs.astro.build/)
- [PDO — PHP Data Objects](https://www.php.net/manual/es/book.pdo.php)
