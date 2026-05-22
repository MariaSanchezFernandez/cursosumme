-- ─────────────────────────────────────────────────────────────
-- migracion-rate-limits.sql
-- Tabla para el rate limiting genérico por IP y endpoint.
-- Ejecutar una sola vez: npm run migrar  (o desde phpMyAdmin)
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS api_rate_limits (
    ip             VARCHAR(45)  NOT NULL,
    endpoint       VARCHAR(100) NOT NULL,
    intentos       INT          NOT NULL DEFAULT 1,
    ventana_inicio DATETIME     NOT NULL,
    PRIMARY KEY (ip, endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
