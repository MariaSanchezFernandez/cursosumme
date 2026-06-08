# CursosUmme — Plataforma E-Learning

Plataforma de formación online para la academia **Umme**. Incluye una web comercial pública con venta de cursos y packs, pasarela de pago con Stripe, área privada para estudiantes con reproductor de vídeo protegido por DRM, y un panel de administración completo para gestionar cursos, materiales, alumnado, pagos y soporte.

🌐 **En producción:** [https://cursosumme.es](https://cursosumme.es)

---

## Idea general

Umme necesitaba vender su formación y servirla online de forma segura, sin depender de plataformas de terceros que se llevan comisión y limitan el control sobre el contenido. CursosUmme resuelve todo el ciclo:

1. **Capta y vende** — web comercial pública con catálogo, precios, packs y checkout con Stripe (incluyendo datos fiscales e IVA).
2. **Da de alta automáticamente** — al completarse el pago, un webhook crea la cuenta, asigna los cursos comprados y envía las credenciales por email.
3. **Sirve el contenido protegido** — los vídeos se reproducen con DRM (Widevine + FairPlay) y marca de agua dinámica, evitando la descarga y la captura de pantalla.
4. **Se autogestiona** — la administración (una sola persona) controla cursos, temas, materiales, alumnado, pagos y soporte desde un panel propio, con auditoría y captura de errores.

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | [Astro](https://astro.build/) v5 + TypeScript |
| Estilos / animación | CSS puro con variables + [GSAP](https://gsap.com/) |
| API | PHP 8 + PDO (sin framework) |
| Base de datos | MySQL / MariaDB (hosting IONOS) |
| Vídeo | [VdoCipher](https://www.vdocipher.com/) — DRM Widevine + FairPlay |
| Pagos | [Stripe](https://stripe.com/) Checkout + webhooks |
| Email | SMTP de IONOS vía cURL |
| Testing | [Playwright](https://playwright.dev/) (E2E) |
| Despliegue | SFTP a IONOS (`ssh2-sftp-client`) |

---

## Funcionalidades

### Web pública (visitante)
- **Landing comercial** (`/`) con animaciones GSAP, sección de quiénes somos y catálogo.
- **Precios** (`/precios`): cursos individuales y packs, con etiquetas y descripciones.
- **Checkout** (`/checkout`): formulario de compra con datos fiscales (nombre, DNI/NIF, dirección, persona o empresa) e IVA, más la renuncia expresa al derecho de desistimiento (art. 103.m LGDCU) con registro del texto exacto mostrado.
- **Pago con Stripe**: redirección a Stripe Checkout y retorno a `/pago-ok` o `/pago-ko`. La página de éxito **sondea el servidor** hasta confirmar el pago real (no muestra éxito por llegar a la URL manualmente).
- **Contacto** (`/contacto`): formulario que envía email a la academia.
- **Páginas legales**: aviso legal, condiciones de contratación, privacidad y cookies.

### Estudiantes (área privada)
- **Login** con sesión por token y opción "Recordarme".
- **Mis cursos**: cursos asignados agrupados por categoría/pack, con portada y barra de progreso.
- **Reproductor de vídeo seguro**: streaming con DRM (VdoCipher), marca de agua dinámica con el email, y bloqueo de descarga.
- **Bloqueo secuencial de temas**: un tema puede quedar bloqueado hasta una fecha (drip content).
- **Progreso** automático al terminar cada vídeo.
- **Materiales**: descarga de documentos, PDFs y audios por tema.
- **Perfil**: foto, datos personales, datos fiscales y cambio de contraseña.
- **Soporte**: creación de tickets y conversación con la administración.

### Administración
- **Dashboard / estadísticas**: alumnado, cursos activos, packs, tickets y **última actividad real** de cada persona (incluso con "Recordarme" activo).
- **Alumnado**: listado con búsqueda, alta/edición, asignación de cursos, fechas de acceso y de baja.
- **Cursos**: CRUD completo con editor enriquecido (H2/H3/negrita/listas), portada, nivel, etiqueta, pack, precio, `stripe_price_id` y colores.
- **Temas y materiales**: añadir/reordenar/eliminar temas; subida de vídeos por chunks (hasta 3,5 GB) a VdoCipher y de documentos/audios; bloqueo por fechas.
- **Packs**: CRUD de packs comerciales y asignación N:N de cursos.
- **Soporte**: gestión de todos los tickets y respuestas.
- **Auditoría**: log de cambios (alumnos, cursos, tickets) y log de errores JS del frontend de todos los usuarios.
- **Herramientas**: vista previa como alumno, guía de formato de contenido, auditoría de materiales y backups de BD.

---

## Arquitectura

Frontend estático generado con **Astro** (HTML + JS hidratado puntual) que consume una **API REST en PHP** desplegada en el mismo hosting. No hay framework de backend: cada endpoint es un archivo PHP en `public/api/` que valida la sesión, opera sobre MySQL vía PDO y devuelve JSON.

```
Navegador (Astro build)  ──fetch /api/*.php (header X-Token)──▶  API PHP + PDO  ──▶  MySQL (IONOS)
        │                                                              │
        ├── Vídeo: iframe VdoCipher (OTP + DRM)  ◀──OTP firmado────────┤
        └── Pago:  Stripe Checkout  ──webhook──▶  /api/stripe-webhook.php
```

### Estructura del proyecto

```
.
├── public/
│   ├── api/                      # API REST en PHP (un archivo por recurso)
│   │   ├── db-config.php         # Credenciales + constantes + helpers — NO se versiona
│   │   ├── db-connect.php        # Conexión PDO
│   │   ├── login.php / logout.php / sesiones.php
│   │   ├── alumnos.php / cursos.php / temas.php / materiales.php
│   │   ├── packs.php / pack-cursos.php
│   │   ├── upload.php / upload-chunk.php / upload-imagen.php
│   │   ├── vdocipher-upload.php / vdocipher-status.php / vdocipher-otp.php
│   │   ├── stripe-checkout.php / stripe-webhook.php / pago-status.php
│   │   ├── validar-fiscal.php / email-helper.php / contacto.php
│   │   ├── mis-cursos.php / progreso.php / tema-bloqueo.php
│   │   ├── tickets.php / logs.php / errores.php / log-error.php
│   │   ├── estadisticas.php / perfil-admin.php / cambiar-password.php
│   │   ├── backup-db.php / info-limites.php
│   │   └── ...migraciones y utilidades puntuales
│   └── uploads/                  # Documentos/imágenes subidas — en .gitignore
├── src/
│   ├── components/               # Componentes Astro reutilizables
│   ├── layouts/                  # Plantilla (alumno) y PlantillaAdmin (sidebar)
│   ├── pages/                    # Rutas: pública, /admin/**, área alumno
│   ├── lib/                      # Helpers de cliente (auth, fetch interceptor)
│   └── styles/                   # CSS por componente / página
├── base-de-datos/                # estructura.sql + migraciones incrementales
├── scripts/                      # deploy, migrar, backup-db, hash-password
├── tests/e2e/                    # Tests Playwright de los flujos críticos
├── astro.config.mjs
└── package.json
```

---

## Base de datos

| Tabla | Descripción |
|-------|------------|
| `usuarios` | Alumnado y administración (email, hash bcrypt, rol, fechas de acceso/baja, datos fiscales) |
| `sesiones` | Un registro por dispositivo activo (token, expiración, `ultimo_uso`) |
| `cursos` | Cursos (título, descripción HTML, etiqueta, nivel, pack, precio, `stripe_price_id`, colores) |
| `temas` | Temas de cada curso (título, duración, orden, descripción, color, `bloqueado_hasta`) |
| `materiales` | Archivos por tema (vídeo VdoCipher o documento) |
| `usuarios_cursos` | Asignación alumno ↔ curso |
| `packs` | Packs comerciales (nombre, descripción, precio, `stripe_price_id`, etiqueta) |
| `pack_cursos` | Relación N:N pack ↔ curso |
| `pagos` | Pagos Stripe (sesión, email, importe, estado, datos fiscales y evidencia de desistimiento) |
| `progresos` | Temas completados por cada alumno |
| `temas_bloqueos_alumno` | Bloqueos de tema personalizados por alumno |
| `tickets` / `ticket_respuestas` | Soporte: consultas y conversación |
| `logs` | Auditoría de acciones de administración |
| `errores` | Errores JS del frontend capturados (tipo, mensaje, url, stack, usuario) |
| `api_rate_limits` | Control de intentos por IP para rate limiting |

El esquema vive en [base-de-datos/estructura.sql](base-de-datos/estructura.sql) con migraciones incrementales (`ALTER TABLE ... IF NOT EXISTS`), ejecutables con `npm run migrar`.

---

## Seguridad

- **Contraseñas** con **bcrypt** (cost 12), nunca en texto plano. Migración transparente desde hashes SHA-256 legacy al loguear.
- **Sesiones por dispositivo** en la tabla `sesiones` (no en columnas de `usuarios`). El token de 64 caracteres viaja como header `X-Token`; el backend lo valida con fallback a `Authorization: Bearer` y a `?token=` para rutas donde el hosting elimina los headers (uploads grandes). Toda llamada protegida pasa por `requireAuth()` / `requireAdmin()`.
- **Rate limiting** de login por IP (tabla `api_rate_limits`).
- **Vídeo con DRM**: VdoCipher (Widevine L1 + FairPlay) con OTP firmado por petición y marca de agua dinámica con el email del alumno, para disuadir la captura y la redistribución.
- **Stripe**: el webhook **verifica la firma** (`STRIPE_WEBHOOK_SECRET`) y rechaza eventos si no es válida; idempotencia mediante `stripe_session_id` único.
- **CORS** del checkout limitado al dominio propio.
- **Cumplimiento legal**: registro de la renuncia al desistimiento por compra (texto exacto + fecha + IP), datos fiscales para facturación, páginas legales y RGPD.
- **Secretos fuera del repositorio**: `db-config.php`, `.env`, `uploads/` y `backups/` están en `.gitignore` y nunca se versionan.

---

## Puesta en marcha

### Requisitos
- Node.js 18+
- Servidor PHP 8 + MySQL / MariaDB

### 1. Instalar dependencias

```bash
npm install
```

### 2. Configurar `public/api/db-config.php`

Este archivo **no está en el repositorio** (contiene secretos). Debe definir, como mínimo, estas constantes y los helpers de autenticación:

```php
<?php
// Base de datos
define('DB_HOST', '...'); define('DB_PORT', '3306');
define('DB_NOMBRE', '...'); define('DB_USUARIO', '...'); define('DB_PASSWORD', '...');

// Pagos
define('STRIPE_SECRET_KEY', '...');     define('STRIPE_PUBLISHABLE_KEY', '...');
define('STRIPE_WEBHOOK_SECRET', '...'); define('STRIPE_TAX_RATE_IVA', '...');

// Email (SMTP IONOS)
define('SMTP_HOST', '...'); define('SMTP_PORT', '...');
define('SMTP_USER', '...'); define('SMTP_PASS', '...'); define('SMTP_FROM_NAME', '...');

// Vídeo
define('VDOCIPHER_API_KEY', '...'); define('VDOCIPHER_PLAYER_ID', '...');

// Backups
define('BACKUP_SECRET', '...');

// + helpers: requireAuth(), requireAdmin(), sanitizeText(), rateLimit()
```

### 3. Configurar `.env` (para migraciones y despliegue)

```
DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
SFTP_HOST, SFTP_PORT, SFTP_USER, SFTP_PASS, SFTP_REMOTE_PATH
```

### 4. Crear las tablas

```bash
npm run migrar
```

Ejecuta `base-de-datos/estructura.sql` y las migraciones. Es idempotente (`IF NOT EXISTS`).

### 5. Desarrollo

```bash
npm run dev      # http://localhost:4321
```

Las llamadas a `/api/*.php` apuntan al servidor real, así que se necesita conexión (o un PHP + MySQL local con la misma BD).

### 6. Producción

```bash
npm run build    # genera dist/
npm run deploy   # build + subida SFTP a IONOS
```

---

## Servicios externos necesarios

| Servicio | Para qué | Configuración |
|----------|----------|---------------|
| **IONOS** (hosting + MySQL) | Web, API PHP y base de datos | `.env` + `db-config.php` |
| **Stripe** | Cobro de cursos y packs | Claves en `db-config.php`; webhook a `/api/stripe-webhook.php` |
| **VdoCipher** | Alojamiento y reproducción de vídeo con DRM | `VDOCIPHER_API_KEY` |
| **SMTP IONOS** | Emails de bienvenida y contacto | Credenciales `SMTP_*` |

> El proyecto se entrega con Stripe en **modo test**. Para cobrar de verdad hay que sustituir las claves por `sk_live_*` y regenerar el secreto del webhook en el panel de Stripe.

---

## Testing

Tests E2E con Playwright de los flujos críticos (login, "recordarme", subida por chunks, autorización de vídeo, estadísticas, responsive, etc.):

```bash
npm test           # ejecuta todos los tests
npm run test:ui    # modo interactivo
npm run test:report
```

Los tests usan un usuario de prueba dedicado, nunca datos reales de alumnado.

---

## Scripts disponibles

| Comando | Descripción |
|---------|------------|
| `npm run dev` | Servidor de desarrollo (`http://localhost:4321`) |
| `npm run build` | Build de producción en `dist/` |
| `npm run preview` | Previsualiza el build |
| `npm run deploy` | Build + subida SFTP a IONOS |
| `npm run migrar` | Crea/actualiza las tablas de la BD |
| `npm run backup-db` | Backup de la BD (conserva los últimos 10) |
| `npm run hash-password` | Genera un hash bcrypt para una contraseña |
| `npm test` | Tests E2E con Playwright |

---

## Autoría y licencia

Proyecto de **María Sánchez Fernández** y **José Manuel Borrás Rodríguez**, desarrollado como Trabajo de Fin de Máster.

Todos los derechos reservados. El repositorio se publica con fines de portfolio y entrega académica: la consulta del código está permitida; su reutilización, redistribución o uso comercial, no. Ver [LICENSE](LICENSE).
