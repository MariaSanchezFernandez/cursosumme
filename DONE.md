# DONE — CursosUmme

Todo lo que ya está en producción o mergeado.

---

## Funcionalidad

- Barra de progreso real en subida de vídeos (XMLHttpRequest con `progress` event).
- UI para reordenar temas mediante drag & drop (columna `orden` ya existe en BD).
- Mostrar al alumno su fecha de expiración de acceso (`/inicio` banner + `/perfil` campo).
- Límite de sesiones simultáneas por usuario: admin configura el máximo por alumno; tabla `sesiones` reemplaza `token_sesion`.

## Panel admin

- Búsqueda / filtro de cursos en el listado admin.
- Estadísticas por curso: alumnos inscritos y % progreso medio en la tarjeta del listado admin.

## Robustez de uploads

Tras el incidente del 2026-04-29: raw body streaming (`php://input` → destino directo, sin `/tmp`).

- Raw body streaming en uploads de vídeo y documento.
- Limpieza automática de `.part` huérfanos (>6 h) al iniciar cada nuevo upload.
- Indicador de cuota en el dashboard (inodos usados vs. límite IONOS 262.144).

## UX y usabilidad

- **Toasts reutilizables**: `window.mostrarToast` / `toast.*`. 4 tipos con paleta Umme. 8 `alert()` sustituidos.
- **Estados de carga en botones**: `window.botonCargando`. Anti-doble-click, accesible, spinner. Aplicado en 10+ sitios.
- **Skeleton screens** en 5 listados (inicio, admin/alumnos, admin/cursos, tickets, errores).
- **Página `/preview`**: playground de componentes UX sin API ni login.
- **Estados de carga en `editar.astro`**: 7 botones críticos del editor de curso.
- **Modales reutilizables**: `window.confirmar` + `window.pedirDato`. 10 `confirm()` y 2 `prompt()` sustituidos.
- **Empty states con jerarquía y CTA** en todas las listas que pueden estar vacías.
- **Estados de error en formularios**: `CampoFormulario` con prop `error`, borde rojo, foco automático.
- **Tokens de diseño centralizados** en `global.css` (`--space-*`, `--text-*`, `--radius-*`, `--shadow-*`).
- **Microcopy con identidad Umme**: errores útiles ("Sin conexión…", "No se pudo…"), textos de acción específicos ("Actualizar contraseña", "Actualizando…").
- **Mobile responsive**: touch targets ≥44×44 px, breakpoint tablet (769–1024 px), login adaptado.
- **Focus management en modales**: trap de foco, cierre con Escape, devolución al disparador.
- **Refactor monolitos UI**: `SidebarAdmin.astro` extraído de `PlantillaAdmin`, `PanelDetalleTabs.astro` extraído de `PanelDetalle`.
