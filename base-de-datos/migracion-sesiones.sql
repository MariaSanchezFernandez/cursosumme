-- ─────────────────────────────────────────────────────────────
-- Migración: sistema de sesiones múltiples por usuario
-- Ejecutar UNA vez antes de desplegar el código correspondiente.
--
-- IMPORTANTE: esta migración invalida todas las sesiones activas.
-- Los usuarios deberán iniciar sesión de nuevo tras aplicarla.
-- ─────────────────────────────────────────────────────────────

-- 1. Añadir límite de sesiones simultáneas a cada usuario (por defecto: 2)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS max_sesiones TINYINT UNSIGNED NOT NULL DEFAULT 2;

-- 2. Tabla de sesiones activas (reemplaza token_sesion / token_expira)
CREATE TABLE IF NOT EXISTS sesiones (
  id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT UNSIGNED     NOT NULL,
  token       VARCHAR(128)     NOT NULL,
  ip          VARCHAR(45)      DEFAULT NULL,
  creado_en   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en   DATETIME         NOT NULL,
  UNIQUE KEY  uk_token  (token),
  INDEX       idx_usuario (usuario_id, expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
