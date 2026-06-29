-- ─────────────────────────────────────────────────────────────
-- Migración: device_id persistente para identificación de dispositivos
-- Ejecutar UNA vez antes de desplegar el código correspondiente.
--
-- Objetivo: identificar dispositivos por UUID generado en el navegador
-- (localStorage), independientemente de la IP. Resuelve el problema de
-- IPs dinámicas que consumían slots de dispositivo de forma fantasma.
-- ─────────────────────────────────────────────────────────────

-- 1. Añadir device_id a sesiones (nullable — sesiones antiguas no lo tienen)
ALTER TABLE sesiones
  ADD COLUMN IF NOT EXISTS device_id VARCHAR(64) DEFAULT NULL,
  ADD INDEX  IF NOT EXISTS idx_device_id (usuario_id, device_id);

-- 2. Subir el límite por defecto de 2 a 3 dispositivos
ALTER TABLE usuarios
  MODIFY COLUMN max_sesiones TINYINT UNSIGNED NOT NULL DEFAULT 3;

-- Actualizar usuarios existentes que tenían el límite por defecto (2) → 3
UPDATE usuarios SET max_sesiones = 3 WHERE max_sesiones = 2;
