---
name: project-device-limit
description: Cómo se cuenta el límite de dispositivos simultáneos por alumno (device_id + tabla sesiones) y el bug de slots fantasma resuelto el 2026-06-29
metadata:
  type: project
---

# Límite de dispositivos simultáneos

Cada usuario tiene `usuarios.max_sesiones` (default 3, admin sin límite). `public/api/login.php` cuenta dispositivos activos vía un `device_id` (UUID v4) que el front genera y persiste en `localStorage` (`umme_device_id`, ver [TarjetaLogin.astro](../../src/components/TarjetaLogin.astro)) y reutiliza en cada login.

## Bug resuelto (2026-06-29): slots fantasma por device_id no persistente

**Síntoma:** alumnos que solo usaban un dispositivo recibían "ya hay 3 dispositivos con sesión activa" y no podían entrar.

**Causa raíz:** cuando el cliente no mandaba un `device_id` válido (Safari ITP borra `localStorage` tras inactividad, modo privado, storage bloqueado…), `login.php` generaba un UUID **aleatorio en el servidor** y lo insertaba en `sesiones.device_id` como si fuera un dispositivo real. Cada visita de ese mismo dispositivo "no persistente" creaba una fila nueva (hasta 15 días de vida, `expira_en`), acumulando slots fantasma hasta agotar `max_sesiones` — todo desde el mismo aparato físico.

**Fix:** si el `device_id` del body no pasa la regex de UUID, ahora se guarda `NULL` (no se genera uno aleatorio). Antes de insertar, se borran TODAS las sesiones con `device_id IS NULL` de ese usuario, y el conteo usa `COALESCE(device_id, 'sin-device')` — así todas las sesiones "no rastreables" de una cuenta comparten un único slot en vez de uno por IP/dispositivo. Trade-off aceptado: varios dispositivos *distintos* que ninguno persiste `device_id` competirán por ese único slot compartido (caso raro) — preferible a bloquear usuarios legítimos de un solo dispositivo.

## Inconsistencia resuelta (2026-06-29)

[`public/api/sesiones.php`](../../public/api/sesiones.php) (panel admin "sesiones activas" en [detalle.astro](../../src/pages/admin/alumnos/detalle.astro)) tenía un `GROUP BY dispositivo, ip` que no coincidía con la clave que usa `login.php` para contar (`COALESCE(device_id, 'sin-device')`) — el admin podía ver menos sesiones de las que el login realmente contaba. Se igualó la clave en ambos sitios: `sesiones.php` ahora agrupa por slot (`COALESCE(device_id, 'sin-device')`) y muestra, por cada slot, la fila más reciente (ip/dispositivo pueden variar entre logins del mismo `device_id`, p.ej. móvil cambiando de red).

## How to apply

- Nunca generar un `device_id` aleatorio en el servidor y guardarlo como si fuera persistente — si el cliente no manda uno válido, usar `NULL` y dejar que el conteo lo trate como un slot único compartido.
- Si se vuelve a tocar el conteo de sesiones en `login.php`, replicar el cambio en `sesiones.php` — deben usar siempre la misma clave de agrupación o se repetirá esta inconsistencia.
- Test E2E de este flujo: `tests/e2e/login.spec.ts` → "logins repetidos sin device_id no acumulan slots fantasma".
