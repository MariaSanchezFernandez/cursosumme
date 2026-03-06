# Cursos Umme

Sitio web construido con [Astro](https://astro.build/), un framework moderno para construir webs rápidas y orientadas al contenido.

---

## Requisitos previos

Antes de empezar, asegúrate de tener instalado en tu máquina:

- [Node.js](https://nodejs.org/) versión 18 o superior
- npm (viene incluido con Node.js)

Puedes comprobar las versiones con:

```bash
node --version
npm --version
```

---

## Estructura del proyecto

```
cursosumme/
├── public/          # Archivos estáticos (imágenes, fuentes, favicon...)
├── src/
│   └── pages/       # Cada archivo .astro aquí se convierte en una página
│       └── index.astro
├── astro.config.mjs # Configuración de Astro
├── tsconfig.json    # Configuración de TypeScript
└── package.json     # Dependencias y scripts del proyecto
```

---

## Puesta en marcha

### 1. Clonar el repositorio

```bash
git clone <url-del-repositorio>
cd cursosumme
```

### 2. Instalar dependencias

```bash
npm install
```

### 3. Arrancar el servidor de desarrollo

```bash
npm run dev
```

Esto arranca un servidor local. Abre tu navegador en `http://localhost:4321` para ver el resultado.

El servidor tiene **hot reload**: cualquier cambio que hagas en el código se refleja en el navegador al instante, sin necesidad de recargar manualmente.

### 4. Construir para producción

Cuando el proyecto esté listo para publicar:

```bash
npm run build
```

Esto genera una carpeta `dist/` con todos los archivos optimizados listos para subir al servidor.

### 5. Previsualizar la build de producción

Para revisar el resultado final antes de subirlo:

```bash
npm run preview
```

---

## Scripts disponibles

| Comando           | Descripción                                      |
|-------------------|--------------------------------------------------|
| `npm run dev`     | Arranca el servidor de desarrollo en puerto 4321 |
| `npm run build`   | Genera los archivos optimizados en `dist/`       |
| `npm run preview` | Previsualiza la build de producción localmente   |

---

## Más información

- [Documentación oficial de Astro](https://docs.astro.build/)
- [Comunidad de Astro en Discord](https://astro.build/chat)
