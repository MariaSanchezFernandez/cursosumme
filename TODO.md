# TODO — CursosUmme

Lo completado está en [DONE.md](DONE.md).

---

## UX del alumno

Auditoría completa realizada el 2026-05-20. Hallazgos organizados por impacto.

### Alta prioridad

- [ ] **FOUC en login**: el formulario aparece un flash antes de que el script compruebe si hay sesión activa. Ocultar `.login-page` por defecto en CSS y mostrarla solo si no hay sesión (`index.astro` script inline).
- [ ] **Progreso guardado en silencio**: cuando termina un vídeo, el POST a `/api/progreso.php` falla sin decirle nada al alumno. Mostrar toast "✓ Progreso guardado" si va bien, y advertencia si falla (`inicio.astro` ~línea 295).
- [ ] **Foto de perfil desincronizada en cabecera**: al cambiar foto en `/perfil`, el header sigue mostrando la antigua hasta recargar (cache `umme_foto` en sessionStorage no se invalida). Limpiar cache tras subida exitosa (`CabeceraApp.astro` + `perfil.astro`).
- [ ] **Badge de soporte con TTL innecesario**: el badge tiene cache de 5 min en sessionStorage — si el admin responde, el alumno no lo ve hasta que expire. Eliminar TTL, refrescar siempre al cargar tickets (`CabeceraApp.astro` línea ~100).
- [x] **Nombre largo rompe el header en mobile**: `.avatar-nombre` sin truncamiento → overflow en pantallas pequeñas. Añadir `max-width` + `text-overflow: ellipsis` (`CabeceraApp.astro` / su CSS).
- [x] **Volver al listado en mobile poco visible**: en móvil al abrir un curso el listado desaparece. El botón `#btnVolver` existe pero no es suficientemente prominente. Hacer más visible o añadir breadcrumb `Mis cursos › Nombre curso` (`inicio.astro` + `inicio.css`).

### Experiencia dentro del curso

- [ ] **Sin animación al completar un tema**: cuando el vídeo termina, el check ✓ aparece en el sidebar sin ninguna transición. Añadir una animación pequeña (fade + scale) al cambiar el icono de ▶ a ✓ para que el alumno perciba claramente que su progreso quedó guardado.
- [ ] **Sin feedback de guardado de progreso**: el POST a `/api/progreso.php` ocurre en silencio. Si falla, el alumno pierde el progreso sin saberlo. Mostrar toast "✓ Progreso guardado" al éxito y advertencia si falla (`inicio.astro` ~línea 295).
- [ ] **Sin forma de volver a la intro del curso**: una vez que abres un tema, la pantalla de intro (descripción + índice) desaparece y no hay botón para recuperarla. Añadir un item "Presentación" o "Inicio del curso" al principio del sidebar de temas.
- [ ] **Sidebar de temas sin scroll visible**: si hay muchos temas, no hay indicador de que se puede scrollear. Añadir `overflow-y: auto` con scrollbar siempre visible o un fade-out en el borde inferior.
- [ ] **Playlist poco visible cuando hay múltiples vídeos**: los botones de la playlist quedan debajo del reproductor y no es obvio que existen. Mejorar su diseño y posición.
- [ ] **Sin indicador de carga al cambiar de vídeo en la playlist**: al cambiar entre vídeos no hay transición, parece que el clic no hizo nada. Añadir spinner o estado de "Cargando vídeo…" entre cambios.

### Media prioridad

- [ ] **Sin feedback de vídeo procesando en VdoCipher**: si `vdo_status !== 'ready'` se muestra "procesando…" pero sin polling — el alumno nunca sabe cuándo estará listo. Hacer polling a `/api/vdocipher-status.php` cada 10 s y mostrar toast al completar (`PanelDetalleTabs.astro`).
- [ ] **Sin fallback si VdoCipher falla**: si el iframe no carga, el área queda vacía. Añadir mensaje "No pudimos cargar el vídeo. Recarga o contacta con soporte" (`PanelDetalleTabs.astro`).
- [ ] **Cerrar ticket sin aclarar consecuencias**: el modal de confirmación no avisa que no se podrá reabrir. Mejorar el texto (`soporte.astro`).
- [ ] **Validación de nueva contraseña débil**: se acepta "123456". Añadir mínimo 8 caracteres y al menos 1 número (`perfil.astro` + `cambiar-password.php`).

### Baja prioridad

- [ ] **Tabs del panel sin `aria-selected`** (accesibilidad).
- [ ] **Skeletons de inicio siempre son 3** independientemente del número real de cursos.
- [ ] **Contador de cursos por filtro** (ej. "Diseño (5)").
- [ ] **Indicador "Vídeo X de Y"** en la playlist.
- [ ] **Ctrl+Enter para enviar** en el textarea de soporte.

---

## Funcionalidad

- [ ] Recuperación de contraseña (email + token de un solo uso).
- [x] Notificaciones por email: credenciales al crear alumno + aviso al responder ticket.
- [x] Integración Stripe: pagos y alta automática de alumnos. Ver `project-stripe.md`.

---

## Robustez de uploads

- [ ] **Reintentos automáticos**: cuando un upload falla por causa transitoria (network error, timeout, 5xx), reintentar hasta 3 veces con backoff (1 s, 3 s, 8 s). Distinguir error transitorio de validación (no reintentar). En chunked: reintentar solo el chunk fallido.
- [ ] **Endpoint de mantenimiento**: botón en `/admin` para listar materiales en BD cuyo archivo físico no exista.
- [ ] Usar `info-limites.php` antes de cada upload grande para avisar al admin si el disco está al 90 %.

---

## Accesibilidad

- [ ] **Auditoría de contraste WCAG AA**: el turquesa `#b3e8da` sobre blanco roza el fallo de AA. Pasar por Wave/axe y ajustar (4.5:1 en texto, 3:1 en componentes UI).
