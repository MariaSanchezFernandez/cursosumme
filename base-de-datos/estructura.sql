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

-- Columnas añadidas en migraciones posteriores (también se autocrean en API)
ALTER TABLE temas ADD COLUMN IF NOT EXISTS color           VARCHAR(20)  DEFAULT NULL;
ALTER TABLE temas ADD COLUMN IF NOT EXISTS descripcion     TEXT         DEFAULT NULL;
ALTER TABLE temas ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME     DEFAULT NULL;


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

-- Campos fiscales y de cumplimiento (RGPD + derecho desistimiento art. 103.m LGDCU).
-- Guardamos en `pagos` (no en tabla aparte) porque la evidencia legal de
-- la renuncia al desistimiento es siempre por-compra. La columna
-- `desistimiento_texto` conserva el texto EXACTO mostrado a la usuaria
-- en el momento de marcar la casilla; si la redacción del checkout
-- cambiase, cada pago histórico mantiene la versión vigente entonces.
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS nombre_completo      VARCHAR(200) DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS telefono             VARCHAR(40)  DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_calle      VARCHAR(255) DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_ciudad     VARCHAR(120) DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_provincia  VARCHAR(120) DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_cp         VARCHAR(20)  DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_pais       VARCHAR(2)   DEFAULT 'ES';
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS dni_nif              VARCHAR(20)  DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS es_empresa           TINYINT(1)   DEFAULT 0;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS ip_cliente           VARCHAR(45)  DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS user_agent           VARCHAR(500) DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS desistimiento_texto  TEXT         DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS desistimiento_en     DATETIME     DEFAULT NULL;
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS stripe_customer_id   VARCHAR(100) DEFAULT NULL;

-- Datos fiscales también en `usuarios` para que el alumno los vea/edite
-- en su perfil y el admin pueda emitir facturas correctamente.
-- (Las columnas viven aquí porque son del alumno, no de cada compra.)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telefono            VARCHAR(40)  DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_calle     VARCHAR(255) DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_ciudad    VARCHAR(120) DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_provincia VARCHAR(120) DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_cp        VARCHAR(20)  DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_pais      VARCHAR(2)   DEFAULT 'ES';
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS dni_nif             VARCHAR(20)  DEFAULT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS es_empresa          TINYINT(1)   DEFAULT 0;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS stripe_customer_id  VARCHAR(100) DEFAULT NULL;


-- -----------------------------------------------------------------
-- Tabla: pack_cursos
-- Relación N:N entre packs y cursos (un curso puede estar en varios packs)
-- `cursos.pack` se mantiene como etiqueta visual (color/agrupación);
-- la pertenencia real al pack para la compra Stripe se lee de aquí.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pack_cursos (
  pack_id     INT          NOT NULL,
  curso_id    INT UNSIGNED NOT NULL,
  asignado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (pack_id, curso_id),
  KEY idx_curso (curso_id),
  CONSTRAINT fk_pc_pack  FOREIGN KEY (pack_id)  REFERENCES packs(id)  ON DELETE CASCADE,
  CONSTRAINT fk_pc_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
