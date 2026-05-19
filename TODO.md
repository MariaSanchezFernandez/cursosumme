# TODO — CursosUmme

Organizado en dos bloques: **Hecho** (lo que ya está en producción o
mergeado) y **Pendiente** (próximos pasos). Dentro de cada bloque,
agrupado por área para mantener el contexto.

---

## Hecho

### Funcionalidad

- [x] Barra de progreso real en subida de vídeos (XMLHttpRequest con
  `progress` event).
- [x] UI para reordenar temas mediante drag & drop (columna `orden` ya
  existe en BD).

### Panel admin

- [x] Búsqueda / filtro de cursos en el listado admin.
- [x] Estadísticas por curso: alumnos inscritos y % progreso medio en la
  tarjeta del listado admin.

### Robustez de uploads

Tras el incidente del 2026-04-29, los uploads de vídeo y documento
fallaban con "No se pudo guardar el archivo en el servidor". Causa
confirmada (2026-05-01): IONOS escribe primero en `/tmp` y luego copia
al servidor web, necesitando el doble de espacio temporalmente —
excedía la cuota de la cuenta. Solución aplicada: raw body streaming
(`php://input` → destino directo, sin `/tmp`). Ver
`feedback-uploads.md`.

- [x] **Raw body streaming en uploads**: vídeos y documentos se
  escriben directamente al destino sin pasar por `/tmp`, eliminando el
  doble-write que causaba los fallos.
- [x] **Limpieza automática de archivos temporales**: `upload-chunk.php`
  borra archivos `.part` huérfanos (>6 h) al iniciar cada nuevo upload.
  Ya no existe el directorio `.tmp-uploads/`.
- [x] **Indicador de cuota en el dashboard**: card con nº de archivos en
  `uploads/` vs. límite de inodos de IONOS (262.144), barra
  verde/ámbar/rojo.

### UX y usabilidad

- [x] **Sistema de toasts reutilizable** (2026-05-04): componente
  `src/components/Toast.astro` + `src/styles/Toast.css` montado en
  `Plantilla.astro` y `PlantillaAdmin.astro`. API global
  `window.mostrarToast(mensaje, tipo, duracion?)` y alias
  `toast.success/error/info/warning(...)`. 4 tipos con la paleta Umme
  (turquesa, coral, amarillo/oro, blanco-con-borde). Auto-cierre 4 s,
  pausa al hover, cierre con ×, accesible (`role=status` +
  `aria-live`). Tipos declarados en `src/env.d.ts`. Test E2E en
  `tests/e2e/toast.spec.ts`. **8 `alert()` sustituidos** en
  `admin/cursos.astro` (×2), `admin/alumnos/detalle.astro`,
  `components/CabeceraApp.astro` (×2), `pages/perfil.astro` (×2) y
  `pages/precios.astro`.

- [x] **Estados de carga en botones** (2026-05-04): helper global
  `src/components/BotonCargando.astro` + `src/styles/BotonCargando.css`
  montado en ambos layouts. API
  `await window.botonCargando(btn, texto, accion)`: guarda el HTML,
  `disabled` y ancho del botón, lo deja con spinner + texto contextual
  mientras dura `accion`, y restaura todo en `finally` (también si la
  acción lanza). Anti-doble-click (segunda llamada se ignora), respeta
  `prefers-reduced-motion`, accesible (`aria-busy`). Tipos en
  `src/env.d.ts`. Test E2E en `tests/e2e/boton-cargando.spec.ts` (estado
  intermedio + restauración, anti-doble-click, restauración tras
  excepción). **Aplicado en 10 sitios críticos**: login
  (`TarjetaLogin.astro`), duplicar/eliminar curso, eliminar alumno,
  crear alumno, crear curso, añadir tema, responder/cerrar/crear
  ticket, cambiar contraseña (admin + alumno), comprar curso.

- [x] **Página de preview `/preview`** (2026-05-04): playground de los
  componentes UX (`src/pages/preview.astro`) para validar visualmente
  toasts, botones cargando y skeleton screens **sin tocar la API**.
  Todas las acciones son simuladas con `setTimeout`. Útil como
  referencia para futuros componentes y para hacer demo del pack
  "polish UX" sin necesidad de login ni BD.

- [x] **Skeleton screens en listados** (2026-05-04): primitivas
  reutilizables en `src/styles/Skeleton.css` (`.skeleton`,
  `.skeleton-text`/`-sm`/`-lg`, `.skeleton-circle`, `.skeleton-box` +
  modificadores `.skeleton-w-25`/`50`/`75`/`90`) con shimmer animado
  de 1.4 s y respeto a `prefers-reduced-motion`. Cargado globalmente
  desde `global.css`. **Aplicado en 5 listados**: `/inicio` (tarjetas
  de curso del alumno), `/admin/alumnos` (filas con avatar + nombre
  + email), `/admin/cursos` (grid de tarjetas de curso),
  `/admin/tickets` (3 tickets con cabecera + cuerpo),
  `/admin/errores` (filas con columnas fecha/tipo/usuario/mensaje).
  Cada página tiene su composición de skeletons que imita la forma
  del contenido real. Test E2E en `tests/e2e/skeleton.spec.ts`.

- [x] **Estados de carga en `editar.astro`** (2026-05-19): aplicado
  `window.botonCargando` en los 7 botones críticos del editor de curso:
  "Guardar cambios" (con bucle de temas dirty dentro), "Añadir tema",
  "Guardar tema" del panel de edición, "Eliminar tema" (icono, solo
  spinner), "Subir vídeo" (manteniendo la barra de progreso separada
  para el %), "Subir archivo" documento (igual que vídeo), "Eliminar
  material" (icono ×, solo spinner). Pequeña mejora al helper: ahora
  `botonCargando(btn, '', accion)` muestra solo el spinner sin texto
  por defecto — necesario para botones icono pequeños.

---

## Pendiente

### Funcionalidad

- [ ] Implementar recuperación de contraseña funcional (email + token
  de un solo uso).
- [ ] Notificaciones por email: credenciales al crear alumno + aviso al
  responder ticket.
- [ ] Mostrar al alumno su fecha de expiración de acceso.
- [ ] Integración Stripe: pagos y alta automática de alumnos.
- [ ] Límite de personas que pueden iniciar sesión con una misma cuenta.

### Robustez de uploads

- [ ] **Reintentos automáticos en uploads**: cuando un upload falla
  por causa transitoria (network error, timeout, 5xx, o respuesta
  ok:false con `diagnostico` que no sea de validación), reintentar
  hasta 3 veces con backoff (1 s, 3 s, 8 s) antes de mostrar error al
  usuario. Aplicar a `subirArchivo`, `subirArchivoChunked` y
  `subirImagen` en `editar.astro` y `crear-curso.astro`. Distinguir
  transitorio (sí reintenta) de validación (no reintenta: extensión
  inválida, tamaño excedido, 4xx con mensaje claro). En chunked:
  reintentar solo el chunk fallido, no recomenzar el upload completo.

- [ ] **Endpoint de mantenimiento desde el panel admin**: botón en
  `/admin` para "Limpiar archivos huérfanos" que liste materiales en
  BD cuyo archivo físico no exista y reporte el estado. (La limpieza
  de `.part` ya es automática.)

- [ ] Usar `info-limites.php` (ya devuelve `disco_libre_mb`) en el
  cliente antes de cada upload grande para avisar al admin si el
  filesystem está al 90 %, antes de que empiece a subir.

### UX y usabilidad — Pack "polish UX" (alta prioridad)

Auditoría hecha el 2026-05-04 sobre toda la superficie de UI (alumno +
admin). Este pack es el más visible en demo y el más defendible como
mejora del TFM.

- [ ] **Empty states con jerarquía y CTA**: para cada lista que pueda
  estar vacía, diseñar un estado vacío con: icono SVG suave (mismo
  lenguaje cromático), título corto ("Aún no tienes cursos"), texto
  explicativo ("Tu administrador te asignará cursos en breve") y,
  donde aplique, un CTA primario ("Crear primer curso", "Crear primer
  alumno"). No dejar páginas en blanco.

- [ ] **Estados de error en formularios**: cada `CampoFormulario` debe
  soportar `error="mensaje"` que pinta el borde en rojo, muestra el
  mensaje bajo el campo con un icono de aviso y mueve el foco al
  primer campo con error al hacer submit. Crítico antes de Stripe (un
  formulario de pago sin validación visible es caso de soporte
  directo).

### UX y usabilidad — Medio plazo

- [ ] **Tokens de diseño centralizados**: ampliar `global.css` con
  escalas reutilizables — `--space-1`/`--space-2`/`--space-3`/`--space-4`,
  `--text-sm`/`--text-base`/`--text-lg`, `--radius-sm`/`--radius-md`,
  `--shadow-sm`/`--shadow-md`. Sustituir los valores sueltos (0.5 rem,
  0.75 rem, 1.25 rem…) en CSS de componentes. Vacuna contra
  inconsistencias futuras y facilita un eventual rediseño.

- [ ] **Auditoría de contraste WCAG AA**: el turquesa `#b3e8da` sobre
  blanco y los tonos pastel rozan el fallo de AA. Pasar todas las
  combinaciones por Wave/axe y ajustar (oscurecer levemente el texto
  sobre fondos pastel, garantizar 4.5:1 en texto y 3:1 en componentes
  UI).

- [ ] **Microcopy con identidad Umme**: los mensajes genéricos
  ("Cargando…", "Error", "Guardar") podrían tener tono propio. Pasada
  global: textos de botones, mensajes vacíos, toasts y errores.
  Coherente con la marca, sin perder claridad.

- [ ] **Mobile — tamaños touch y breakpoint tablet**: hoy solo hay un
  breakpoint (768 px). Auditar que todos los botones e iconos
  clicables tengan **mínimo 44×44 px** y añadir breakpoint intermedio
  para tablet (768–1024). Probar en iPad y móvil real.

- [ ] **Focus management en modales**: el modal de soporte ya tiene
  `role=dialog` y `aria-modal`, pero falta: trap del foco dentro del
  modal, cierre con Escape, y devolución del foco al botón que lo
  abrió al cerrarlo.

- [ ] **Refactor de monolitos UI (solo si los tocamos)**:
  `PanelDetalle.astro` (~600 líneas) y `PlantillaAdmin.css` (~921
  líneas) son monolíticos. No es bug, pero ralentiza iterar UX. Si
  vamos a meter mano fuerte en esas zonas, partir en piezas más
  pequeñas (`PanelDetalleTabs`, `PanelDetalleMaterial`, sidebar admin
  como componente con su CSS).

- [ ] **Modales reutilizables para confirmar y pedir datos**
  (sustituir `confirm()` y `prompt()` nativos): los diálogos del
  navegador rompen visualmente la experiencia (estilo del SO, no de
  Umme) y no son accesibles ni personalizables. Crear dos componentes:
    - `confirmar(mensaje, opciones?) → Promise<boolean>` para
      reemplazar `confirm()` en eliminar/duplicar curso, eliminar
      alumno, etc. Permite título, mensaje, texto de los botones y
      variante "peligro" (botón rojo) para acciones destructivas.
    - `pedirDato(campos, opciones?) → Promise<datos | null>` para
      reemplazar `prompt()` en edición rápida (precio curso,
      `stripe_price_id`, etc.). Admite varios campos a la vez.
  Ambos basados en el mismo `<Modal />` reutilizable con
  `role=dialog`, `aria-modal`, trap de foco, Escape para cerrar y
  devolución del foco al disparador. Estética Umme (mismo lenguaje
  que toasts: tarjeta pastel, DM Sans, paleta de marca).
