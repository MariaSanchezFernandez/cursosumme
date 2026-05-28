-- =================================================================
-- Migración: pack_cursos (N:N entre packs y cursos)
-- 2026-05-26
--
-- Hasta ahora un curso solo podía pertenecer a un pack
-- (columna VARCHAR `cursos.pack`). Esto no escala porque hay cursos
-- compartidos entre packs (ej. "Ten un temario de 10",
-- "Técnicas de preparación", "Resolución estratégica").
--
-- `cursos.pack` se conserva por ahora como etiqueta visual
-- (color/agrupación en admin). La pertenencia real a un pack para la
-- compra Stripe pasa a determinarse por la tabla `pack_cursos`.
-- =================================================================

CREATE TABLE IF NOT EXISTS pack_cursos (
  pack_id     INT          NOT NULL,
  curso_id    INT UNSIGNED NOT NULL,
  asignado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (pack_id, curso_id),
  KEY idx_curso (curso_id),
  CONSTRAINT fk_pc_pack  FOREIGN KEY (pack_id)  REFERENCES packs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_pc_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activar el curso "Crea unas SA de 10; Aula específica" (id=13).
-- Estaba en activo=0 y forma parte del Pack Aula Específica.
UPDATE cursos SET activo = 1 WHERE id = 13;
