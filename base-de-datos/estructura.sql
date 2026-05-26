-- =================================================================
-- Cursos Umme — Estructura de la base de datos
-- Ejecutar con: npm run migrar
-- =================================================================

-- -----------------------------------------------------------------
-- Tabla: cursos
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cursos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(255) NOT NULL,
  etiqueta        VARCHAR(100) NOT NULL,
  nivel           VARCHAR(100) NOT NULL,
  duracion        VARCHAR(50)  NOT NULL,
  pack            VARCHAR(120) DEFAULT NULL,
  precio          DECIMAL(8,2) DEFAULT NULL,
  stripe_price_id VARCHAR(100) DEFAULT NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migraciones incrementales para instalaciones existentes
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack            VARCHAR(120) DEFAULT NULL;
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS precio          DECIMAL(8,2) DEFAULT NULL;
ALTER TABLE cursos ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(100) DEFAULT NULL;


-- -----------------------------------------------------------------
-- Tabla: temas
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS temas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  curso_id    INT UNSIGNED NOT NULL,
  titulo      VARCHAR(255) NOT NULL,
  duracion    VARCHAR(50)  NOT NULL DEFAULT '',
  orden       SMALLINT     NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  KEY idx_curso (curso_id),
  CONSTRAINT fk_temas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------
-- Tabla: materiales
-- Archivos por tema: video o documento
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS materiales (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tema_id     INT UNSIGNED NOT NULL,
  tipo        ENUM('video','documento') NOT NULL,
  nombre      VARCHAR(255) NOT NULL,
  ruta        VARCHAR(500) NOT NULL,
  tamano_kb   INT UNSIGNED NOT NULL DEFAULT 0,
  subido_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_tema (tema_id),
  CONSTRAINT fk_materiales_tema FOREIGN KEY (tema_id) REFERENCES temas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------
-- Tabla: usuarios_cursos
-- Asignaciones alumno ↔ curso
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios_cursos (
  usuario_id  INT UNSIGNED NOT NULL,
  curso_id    INT UNSIGNED NOT NULL,
  asignado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (usuario_id, curso_id),
  KEY idx_curso (curso_id),
  CONSTRAINT fk_uc_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------
-- Tabla: packs
-- Packs comerciales: agrupan cursos por `cursos.pack` = `packs.nombre`
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS packs (
  id              INT NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(200) NOT NULL,
  descripcion     TEXT         DEFAULT NULL,
  precio          DECIMAL(8,2) DEFAULT NULL,
  stripe_price_id VARCHAR(100) DEFAULT NULL,
  etiqueta        VARCHAR(100) NOT NULL,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------
-- Tabla: pagos
-- Registro de pagos Stripe (uno por sesión Checkout)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
  id                INT NOT NULL AUTO_INCREMENT,
  stripe_session_id VARCHAR(200) NOT NULL,
  email             VARCHAR(200) NOT NULL,
  cursos_ids        TEXT         NOT NULL,
  alumno_id         INT          DEFAULT NULL,
  estado            ENUM('pendiente','completado','fallido') NOT NULL DEFAULT 'pendiente',
  importe           DECIMAL(8,2) DEFAULT NULL,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_stripe_session_id (stripe_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
