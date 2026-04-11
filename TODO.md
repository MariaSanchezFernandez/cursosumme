# TODO — CursosUmme

## Seguridad

- [ ] Cambiar hash de contraseñas de SHA-256 a bcrypt/Argon2 (BD + `login.php` + `cambiar-password.php`)
- [ ] Añadir autenticación de servidor en las APIs PHP (token de sesión validado en backend)
- [ ] Proteger o eliminar `setup.php` del acceso público
- [ ] Limitar intentos de login (bloqueo temporal por IP tras N fallos)

## Funcionalidad pendiente

- [ ] Implementar recuperación de contraseña funcional (email + token de un solo uso)
- [ ] Notificaciones por email: credenciales al crear alumno + aviso al responder ticket
- [ ] Barra de progreso real en subida de vídeos (XMLHttpRequest con `progress` event)
- [ ] UI para reordenar temas mediante drag & drop (columna `orden` ya existe en BD)
- [ ] Mostrar al alumno su fecha de expiración de acceso
- [ ] Integración Stripe: pagos y alta automática de alumnos

## Panel admin

- [ ] Añadir búsqueda/filtro de cursos en el listado admin
- [ ] Estadísticas por curso: alumnos inscritos, % progreso medio, temas completados
