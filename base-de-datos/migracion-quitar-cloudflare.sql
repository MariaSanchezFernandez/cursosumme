-- Migración: eliminar columnas residuales de Cloudflare Stream
-- 2026-05-30
--
-- Tras la migración a VdoCipher en mayo 2026, la tabla `materiales`
-- mantuvo las columnas cf_video_id y cf_status por precaución. Ya no
-- hay código que las lea ni las escriba, así que las eliminamos.
--
-- Para ejecutarla a mano via phpMyAdmin de IONOS o vía el endpoint
-- /api/migrar-quitar-cloudflare.php.

ALTER TABLE materiales DROP COLUMN cf_video_id;
ALTER TABLE materiales DROP COLUMN cf_status;
