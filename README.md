# Cursos Umme

Plataforma e-learning construida con **Astro** (frontend estático) + **PHP** (API REST) sobre hosting compartido IONOS.

---

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Frontend | [Astro](https://astro.build/) v5 + TypeScript |
| Estilos | CSS puro con variables CSS |
| API | PHP 8 + PDO (MySQL) |
| Base de datos | MySQL (IONOS hosting) |
| Despliegue | SFTP a IONOS / 1&1 |

---

## Funcionalidades

### Alumnos
- **Login** con sesión en `sessionStorage` (expira 8 h)
- **Mis cursos**: listado de cursos asignados con tarjetas, nivel, etiqueta y color
- **Panel de curso**: temas con materiales (vídeos y documentos), descripción y duración
- **Progreso**: porcentaje de temas completados; se marca al terminar un vídeo
- **Temas completados**: icono de check verde en la barra lateral
- **Foto de perfil**: subida y cambio desde la cabecera (JPEG / PNG / WebP / GIF)
- **Perfil**: cambio de contraseña
- **Soporte**: creación de tickets, seguimiento de respuestas, cierre de consulta

### Administradores
- **Dashboard**: panel de control principal
- **Alumnos**: listado, creación, detalle y gestión de acceso
- **Cursos**: listado, creación con título, descripción, nivel, etiqueta, duración y colores
- **Editar curso**: gestión de temas (añadir / reordenar / eliminar) con descripción y duración; carga de materiales (vídeos y documentos) por tema
- **Tickets / Soporte**: listado de consultas con filtros por estado, respuesta directa y cambio de estado

---

## Estructura del proyecto

```
cursosumme/
├── public/
│   ├── api/                        # API REST en PHP
│   │   ├── db-connect.php          # Conexión PDO centralizada
│   │   ├── login.php               # Autenticación (POST)
│   │   ├── alumnos.php             # CRUD alumnos (admin)
│   │   ├── cursos.php              # CRUD cursos (admin)
│   │   ├── temas.php               # CRUD temas
│   │   ├── materiales.php          # CRUD materiales
│   │   ├── mis-cursos.php          # Cursos asignados a un alumno (GET)
│   │   ├── progreso.php            # Registro de temas completados
│   │   ├── foto-perfil.php         # Subida y consulta de foto de perfil
│   │   ├── cambiar-password.php    # Cambio de contraseña del alumno
│   │   ├── upload.php              # Subida de vídeos y documentos
│   │   ├── video.php               # Streaming de vídeo
│   │   ├── tickets.php             # Sistema de tickets / soporte
│   │   └── setup.php              # Creación inicial de tablas (ejecutar una vez)
│   └── uploads/                    # Archivos subidos — en .gitignore
├── src/
│   ├── components/
│   │   ├── CabeceraApp.astro       # Cabecera alumno (logo → /inicio, avatar, soporte, salir)
│   │   ├── PanelDetalle.astro      # Panel lateral de detalle de curso
│   │   ├── PiePagina.astro         # Footer
│   │   ├── TarjetaCurso.astro      # Tarjeta de curso en listado
│   │   ├── TarjetaLogin.astro      # Tarjeta del formulario de login
│   │   ├── Boton.astro
│   │   ├── CampoFormulario.astro
│   │   └── FondoLogin.astro
│   ├── layouts/
│   │   ├── Plantilla.astro         # Layout base páginas de alumno
│   │   └── PlantillaAdmin.astro    # Layout con sidebar para admin
│   ├── pages/
│   │   ├── index.astro             # Login (/)
│   │   ├── inicio.astro            # Mis cursos (/inicio)
│   │   ├── soporte.astro           # Soporte / tickets alumno (/soporte)
│   │   ├── perfil.astro            # Perfil y cambio de contraseña (/perfil)
│   │   ├── recuperar-contrasena.astro
│   │   └── admin/
│   │       ├── index.astro         # Dashboard (/admin)
│   │       ├── cursos.astro        # Listado de cursos (/admin/cursos)
│   │       ├── tickets.astro       # Panel de soporte (/admin/tickets)
│   │       ├── alumnos/
│   │       │   ├── index.astro
│   │       │   ├── crear-alumno.astro
│   │       │   └── detalle.astro
│   │       └── cursos/
│   │           ├── crear-curso.astro
│   │           └── editar.astro    # Editor de temas y materiales
│   └── styles/                     # CSS por componente / página
├── scripts/
│   └── deploy.mjs                  # Script de despliegue SFTP
├── .env.example
├── astro.config.mjs
├── tsconfig.json
└── package.json
```

---

## Puesta en marcha

### Requisitos

- Node.js v18 o superior
- Servidor PHP 8 + MySQL (o MariaDB)

### 1. Instalar dependencias

```bash
npm install
```

### 2. Configurar el entorno

```bash
cp .env.example .env
```

Edita `.env` con los datos de tu servidor SFTP:

```env
SFTP_HOST=home335171042.1and1-data.host
SFTP_PORT=22
SFTP_USER=acc190978561
SFTP_PASS=                    # opcional, se pedirá si falta
SFTP_REMOTE_PATH=/
```

Los datos de conexión a la BD van en `public/api/db-config.php` (excluido del repositorio):

```php
<?php
define('DB_HOST', 'tu-host-mysql');
define('DB_NAME', 'nombre_bd');
define('DB_USER', 'usuario_bd');
define('DB_PASS', 'contraseña_bd');
```

### 3. Inicializar la base de datos

Accede una sola vez a `/api/setup.php` desde el navegador (en el servidor) para crear todas las tablas.

### 4. Desarrollo local

```bash
npm run dev
```

Abre `http://localhost:4321`.

> Las llamadas a `/api/*.php` apuntan al servidor real. Necesitas conexión o un servidor local PHP.

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

---

## Scripts disponibles

| Comando | Descripción |
|---------|------------|
| `npm run dev` | Servidor de desarrollo en `http://localhost:4321` |
| `npm run build` | Genera los archivos optimizados en `dist/` |
| `npm run preview` | Previsualiza la build de producción |
| `npm run deploy` | Build + subida SFTP al servidor |

---

## Seguridad

- Contraseñas almacenadas como **hashes SHA-256**.
- Sesión en `sessionStorage` (no persiste al cerrar el navegador), expira a las **8 horas**.
- Todas las rutas protegidas validan la sesión al cargar.
- El área `/admin` solo es accesible para `rol = 'admin'`.
- Ficheros sensibles (`db-config.php`, `uploads/`, `.env`) excluidos del repositorio.

---

## Base de datos — tablas principales

| Tabla | Descripción |
|-------|------------|
| `usuarios` | Alumnos y admins (email, hash SHA-256, rol, foto) |
| `cursos` | Cursos disponibles (título, descripción, nivel, duración, colores) |
| `temas` | Temas de cada curso (título, descripción, duración, orden) |
| `materiales` | Archivos de cada tema (tipo vídeo/doc, ruta, tamaño KB) |
| `usuarios_cursos` | Relación alumno ↔ curso asignado |
| `progreso` | Temas completados por cada alumno |
| `tickets` | Consultas de soporte creadas por alumnos |
| `ticket_respuestas` | Respuestas de admin a cada ticket |

---

## Más información

- [Documentación oficial de Astro](https://docs.astro.build/)
- [PDO — PHP Data Objects](https://www.php.net/manual/es/book.pdo.php)
