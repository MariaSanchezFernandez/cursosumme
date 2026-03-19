# Cursos Umme

Sitio web construido con [Astro](https://astro.build/), un framework moderno para construir webs rápidas y orientadas al contenido.

---

## Requisitos previos

- [Node.js](https://nodejs.org/) v18 o superior
- npm (incluido con Node.js)

```bash
node --version
npm --version
```

---

## Estructura del proyecto

```
cursosumme/
├── public/                  # Archivos estáticos (favicon, imágenes, fuentes...)
├── scripts/
│   └── deploy.mjs           # Script de despliegue SFTP automático
├── src/
│   ├── components/          # Componentes reutilizables (.astro)
│   │   ├── Boton.astro
│   │   ├── CabeceraApp.astro
│   │   ├── CampoFormulario.astro
│   │   ├── FondoLogin.astro
│   │   ├── PanelDetalle.astro
│   │   ├── PiePagina.astro
│   │   ├── TarjetaCurso.astro
│   │   └── TarjetaLogin.astro
│   ├── data/                # Datos y tipos TypeScript
│   │   ├── cursos.ts
│   │   └── usuarios.ts
│   ├── layouts/             # Plantillas base de página
│   │   ├── Plantilla.astro
│   │   └── PlantillaAdmin.astro
│   ├── pages/               # Páginas (cada archivo = una ruta)
│   │   ├── index.astro
│   │   ├── inicio.astro
│   │   ├── recuperar-contrasena.astro
│   │   └── admin/
│   │       ├── index.astro
│   │       ├── cursos.astro
│   │       └── alumnos/
│   │           ├── index.astro
│   │           ├── nuevo.astro
│   │           └── [id].astro
│   └── styles/              # Hojas de estilo CSS por componente/página
├── .env.example             # Plantilla de configuración SFTP (copiar como .env)
├── astro.config.mjs         # Configuración de Astro
├── tsconfig.json            # Configuración de TypeScript
└── package.json             # Dependencias y scripts del proyecto
```

---

## Puesta en marcha

### 1. Instalar dependencias

```bash
npm install
```

### 2. Arrancar el servidor de desarrollo

```bash
npm run dev
```

Abre `http://localhost:4321` en el navegador. Los cambios se reflejan al instante (hot reload).

### 3. Construir para producción

```bash
npm run build
```

Genera la carpeta `dist/` con todos los archivos optimizados listos para publicar.

### 4. Previsualizar la build antes de subir

```bash
npm run preview
```

---

## Scripts disponibles

| Comando                  | Descripción                                        |
|--------------------------|----------------------------------------------------|
| `npm run dev`            | Servidor de desarrollo en `http://localhost:4321`  |
| `npm run build`          | Genera los archivos optimizados en `dist/`         |
| `npm run preview`        | Previsualiza la build de producción localmente     |
| `npm run deploy`         | Hace build y sube el sitio al servidor por SFTP    |
| `npm run hash-password`  | Genera el hash de una nueva contraseña             |

---

## Despliegue en el servidor (SFTP)

### Configuración (solo la primera vez)

1. Copia el archivo de ejemplo:

```bash
cp .env.example .env
```

2. Abre `.env` y ajusta los valores si es necesario. Los datos del servidor ya están preconfigurados; solo necesitas añadir la contraseña si quieres evitar escribirla cada vez:

```env
SFTP_HOST=home335171042.1and1-data.host
SFTP_PORT=22
SFTP_USER=acc190978561
SFTP_PASS=tu_contraseña          # opcional, se pedirá si no está definida
SFTP_REMOTE_PATH=/               # ruta del servidor donde se sube el sitio
```

> El archivo `.env` **nunca se sube al repositorio** — está en `.gitignore`.

### Subir el sitio

```bash
npm run deploy
```

El script hará automáticamente:
1. Build del proyecto (`npm run build`)
2. Conexión al servidor por SFTP
3. Subida de todos los archivos de `dist/` a la ruta remota configurada

Si no has puesto la contraseña en `.env`, te la pedirá al ejecutar.

---

## Seguridad y gestión de accesos

### Cómo funciona

- Las contraseñas se almacenan como **hashes SHA-256**, nunca en texto plano.
- Al hacer login se crea una **sesión en `sessionStorage`** que expira a las 8 horas.
- Todas las páginas protegidas comprueban la sesión al cargar y redirigen al login si no es válida o ha expirado.
- El área `/admin` solo es accesible para usuarios con rol `admin`.

### Credenciales iniciales

| Usuario | Email | Contraseña inicial |
|---------|-------|--------------------|
| Rocío (admin) | rocio@cursosumme.com | `Umme@Admin24` |
| Alumnos | (cada email) | `Umme@2024` |

> **Cambia las contraseñas iniciales tras el primer acceso.**

### Cambiar una contraseña

1. Genera el nuevo hash:

```bash
npm run hash-password
```

2. Copia el hash que aparece en pantalla.
3. Abre [src/data/usuarios.ts](src/data/usuarios.ts) y pega el hash en el campo `hashAcceso` del usuario correspondiente.
4. Vuelve a publicar el sitio:

```bash
npm run deploy
```

---

## Más información

- [Documentación oficial de Astro](https://docs.astro.build/)
- [Comunidad de Astro en Discord](https://astro.build/chat)
