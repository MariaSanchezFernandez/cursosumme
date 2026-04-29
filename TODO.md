# TODO — CursosUmme

## Funcionalidad pendiente

- [ ] Implementar recuperación de contraseña funcional (email + token de un solo uso)
- [ ] Notificaciones por email: credenciales al crear alumno + aviso al responder ticket
- [x] Barra de progreso real en subida de vídeos (XMLHttpRequest con `progress` event)
- [ ] UI para reordenar temas mediante drag & drop (columna `orden` ya existe en BD)
- [ ] Mostrar al alumno su fecha de expiración de acceso
- [ ] Integración Stripe: pagos y alta automática de alumnos

## Panel admin

- [ ] Añadir búsqueda/filtro de cursos en el listado admin
- [ ] Estadísticas por curso: alumnos inscritos, % progreso medio, temas completados

## Robustez de uploads

Tras incidente del 2026-04-29 (uploads de imagen y vídeo fallando con
"No se pudo guardar el archivo en el servidor" durante un glitch
temporal de IONOS — confirmado que NO era cuota, espacio web ilimitado).

- [ ] **Reintentos automáticos en uploads**: cuando un upload falla
  por causa transitoria (network error, timeout, 5xx, o respuesta
  ok:false con `diagnostico` que no sea de validación), reintentar
  hasta 3 veces con backoff (1s, 3s, 8s) antes de mostrar error al
  usuario. Aplicar a `subirArchivo`, `subirArchivoChunked` y
  `subirImagen` en `admin/cursos/editar.astro` y `crear-curso.astro`.
  Distinguir transitorio (sí reintenta) de validación (no reintenta:
  extensión inválida, tamaño excedido, 4xx con mensaje claro).
  Importante: en chunked sólo reintentar el chunk fallido, no
  recomenzar todo el upload.

- [ ] **Endpoint de mantenimiento desde el panel admin**: botón en
  /admin para "Limpiar archivos temporales y huérfanos" que ejecute
  un PHP que:
    * Borre /uploads/.tmp-uploads/* con mtime > 6 h (esto ya lo hace
      preventivamente upload-chunk.php al iniciar un upload, pero
      conviene un disparador manual para emergencias).
    * Liste materiales en BD cuyo archivo físico no exista (cleanup).
    * Reporte espacio liberado.

- [ ] **Indicador de cuota en el dashboard**: card que muestre
  GB usados, número de archivos vs. límite IONOS (262.144 inodos),
  con barra de color (verde/ámbar/rojo). Sirve para detectar pronto
  un agotamiento de inodos antes de que rompa los uploads.

- [ ] Usar `info-limites.php` (ya devuelve `disco_libre_mb`) en el
  cliente antes de cada upload grande para avisar al admin si el
  filesystem está al 90 %, antes de que empiece a subir.
