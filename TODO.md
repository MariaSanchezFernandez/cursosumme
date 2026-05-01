# TODO — CursosUmme

## Funcionalidad pendiente

- [ ] Implementar recuperación de contraseña funcional (email + token de un solo uso)
- [ ] Notificaciones por email: credenciales al crear alumno + aviso al responder ticket
- [x] Barra de progreso real en subida de vídeos (XMLHttpRequest con `progress` event)
- [x] UI para reordenar temas mediante drag & drop (columna `orden` ya existe en BD)
- [ ] Mostrar al alumno su fecha de expiración de acceso
- [ ] Integración Stripe: pagos y alta automática de alumnos

## Panel admin

- [x] Añadir búsqueda/filtro de cursos en el listado admin
- [x] Estadísticas por curso: alumnos inscritos y % progreso medio en la tarjeta del listado admin

## Robustez de uploads

Tras el incidente del 2026-04-29, los uploads de vídeo y documento fallaban con
"No se pudo guardar el archivo en el servidor". Causa confirmada (2026-05-01):
IONOS escribe primero en `/tmp` y luego copia al servidor web, necesitando el
doble de espacio temporalmente — excedía la cuota de la cuenta.

**Solución aplicada (2026-05-01):** ambos endpoints usan ahora raw body streaming
(`php://input` → destino directo, sin `/tmp`). Ver `feedback-uploads.md`.

- [x] **Raw body streaming en uploads**: vídeos y documentos se escriben directamente
  al destino sin pasar por `/tmp`, eliminando el doble-write que causaba los fallos.

- [x] **Limpieza automática de archivos temporales**: `upload-chunk.php` borra
  archivos `.part` huérfanos (> 6 h) al iniciar cada nuevo upload. Ya no existe
  el directorio `.tmp-uploads/`.

- [ ] **Reintentos automáticos en uploads**: cuando un upload falla por causa
  transitoria (network error, timeout, 5xx, o respuesta ok:false con `diagnostico`
  que no sea de validación), reintentar hasta 3 veces con backoff (1s, 3s, 8s)
  antes de mostrar error al usuario. Aplicar a `subirArchivo`, `subirArchivoChunked`
  y `subirImagen` en `editar.astro` y `crear-curso.astro`.
  Distinguir transitorio (sí reintenta) de validación (no reintenta: extensión
  inválida, tamaño excedido, 4xx con mensaje claro).
  En chunked: reintentar solo el chunk fallido, no recomenzar el upload completo.

- [ ] **Endpoint de mantenimiento desde el panel admin**: botón en /admin para
  "Limpiar archivos huérfanos" que liste materiales en BD cuyo archivo físico
  no exista y reporte el estado. (La limpieza de `.part` ya es automática.)

- [x] **Indicador de cuota en el dashboard**: card con nº de archivos en uploads/
  vs. límite de inodos de IONOS (262.144), barra verde/ámbar/rojo.

- [ ] Usar `info-limites.php` (ya devuelve `disco_libre_mb`) en el cliente antes
  de cada upload grande para avisar al admin si el filesystem está al 90 %,
  antes de que empiece a subir.
