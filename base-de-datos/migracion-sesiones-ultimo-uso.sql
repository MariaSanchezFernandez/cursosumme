-- ─────────────────────────────────────────────────────────────
-- Migración: añadir `ultimo_uso` a la tabla sesiones.
--
-- Motivación:
--   "Última actividad" en el panel admin de estadísticas debe
--   reflejar la última vez que la persona usó la web, no solo
--   la última vez que marcó un vídeo. Antes solo se podía
--   derivar de `progresos`/`progresos_materiales` (vídeos) y de
--   `sesiones.creado_en` (login). Eso deja fuera a quien tiene
--   "Recordarme" activo: entra sin volver a logarse y, si no
--   marca nada, parece inactiva.
--
-- Cambio:
--   `ultimo_uso` se actualizará en `requireAuth()` cada vez que
--   se valide el token. Cualquier petición autenticada (cargar
--   /inicio, /admin, etc.) refresca este campo.
--
-- Compatibilidad:
--   - Se añade con DEFAULT CURRENT_TIMESTAMP → todas las filas
--     existentes quedan con la fecha del momento de aplicar la
--     migración (no NULL).
--   - El código de `requireAuth()` envuelve el UPDATE en try/catch
--     → si por algún motivo la columna no existiera, la petición
--     no rompe.
--
-- Aplicar UNA vez sobre la BD del servidor.
-- Equivalente vía npm: `npm run migrar` (si está enganchado al
-- runner) o manualmente vía phpMyAdmin / consola MySQL.
-- ─────────────────────────────────────────────────────────────

ALTER TABLE sesiones
  ADD COLUMN IF NOT EXISTS ultimo_uso DATETIME
    NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Índice para filtrar "activos en los últimos X días" rápido
ALTER TABLE sesiones
  ADD INDEX IF NOT EXISTS idx_ultimo_uso (ultimo_uso);
