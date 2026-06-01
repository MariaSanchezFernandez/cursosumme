-- Migración: bloqueo de temas por alumna (en lugar de global)
-- 2026-05-31
--
-- Sustituye el bloqueo global por tema (columna `temas.bloqueado_hasta`)
-- por un sistema per-alumna: solo las usuarias marcadas como `es_alumna_rocio`
-- pueden tener temas bloqueados, y el bloqueo se configura una a una desde
-- la ficha del alumno en el panel admin. El resto de alumnos ven todos los
-- temas siempre.
--
-- Se puede ejecutar a mano vía phpMyAdmin de IONOS o vía el endpoint
-- /api/migrar-bloqueos-por-alumna.php.

-- 1. Flag en usuarios: ¿es alumna de Rocío? (defecto: NO)
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS es_alumna_rocio TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Tabla de bloqueos (par alumna ↔ tema).
-- Sin FKs explícitas: la BD de IONOS rechaza la creación con
-- errno 150 por discrepancia de tipo/charset entre las columnas.
-- La integridad se mantiene desde la aplicación (alumnos.php y
-- temas.php borran las filas asociadas al eliminar usuario/tema).
CREATE TABLE IF NOT EXISTS temas_bloqueos_alumno (
  usuario_id        INT      NOT NULL,
  tema_id           INT      NOT NULL,
  bloqueado_hasta   DATETIME NOT NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, tema_id),
  KEY idx_bloqueado_hasta (bloqueado_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Limpieza: vaciar los bloqueos globales preexistentes (todos eran pruebas)
UPDATE temas SET bloqueado_hasta = NULL;
