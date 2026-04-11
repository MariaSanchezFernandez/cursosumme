<?php
// ─────────────────────────────────────────────────────────────
// api/setup.php  —  Migraciones e índices de la BD
// Visitar una vez tras cada despliegue que modifique la BD.
// Seguro: usa IF NOT EXISTS, no destruye datos.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';

// ── Protección: requiere ?key=SETUP_KEY ───────────────────────
$keyProvided = trim($_GET['key'] ?? '');
if (!defined('SETUP_KEY') || !hash_equals(SETUP_KEY, $keyProvided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado. Usa ?key=TU_CLAVE']);
    exit;
}

$pdo = obtenerPDO();

$resultados = [];

function ejecutar(PDO $pdo, string $sql, string $descripcion, array &$log): void {
    try {
        $pdo->exec($sql);
        $log[] = ['ok' => true, 'msg' => $descripcion];
    } catch (Exception $e) {
        $log[] = ['ok' => false, 'msg' => $descripcion . ': ' . $e->getMessage()];
    }
}

// ── Columnas ──────────────────────────────────────────────────
ejecutar($pdo, 'ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack        VARCHAR(120) DEFAULT NULL', 'pack en cursos', $resultados);
ejecutar($pdo, 'ALTER TABLE cursos ADD COLUMN IF NOT EXISTS pack_color  VARCHAR(20)  DEFAULT NULL', 'pack_color en cursos', $resultados);
ejecutar($pdo, 'ALTER TABLE cursos ADD COLUMN IF NOT EXISTS color       VARCHAR(20)  DEFAULT NULL', 'color en cursos', $resultados);
ejecutar($pdo, 'ALTER TABLE cursos ADD COLUMN IF NOT EXISTS descripcion TEXT         DEFAULT NULL', 'descripcion en cursos', $resultados);
ejecutar($pdo, 'ALTER TABLE temas  ADD COLUMN IF NOT EXISTS descripcion TEXT         DEFAULT NULL', 'descripcion en temas', $resultados);
ejecutar($pdo, 'ALTER TABLE temas  ADD COLUMN IF NOT EXISTS color       VARCHAR(20)  DEFAULT NULL', 'color en temas', $resultados);
ejecutar($pdo, 'ALTER TABLE temas  ADD COLUMN IF NOT EXISTS duracion    VARCHAR(50)  NOT NULL DEFAULT \'\'', 'duracion en temas', $resultados);
ejecutar($pdo, 'ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fecha_baja DATE DEFAULT NULL', 'fecha_baja en usuarios', $resultados);
ejecutar($pdo, 'ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) DEFAULT NULL', 'foto_perfil en usuarios', $resultados);
ejecutar($pdo, 'ALTER TABLE cursos  ADD COLUMN IF NOT EXISTS imagen      VARCHAR(500) DEFAULT NULL', 'imagen en cursos', $resultados);

// ── Tabla progresos ───────────────────────────────────────────
ejecutar($pdo, "CREATE TABLE IF NOT EXISTS progresos (
    usuario_id INT NOT NULL,
    tema_id    INT NOT NULL,
    visto_en   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, tema_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla progresos', $resultados);

// ── Tablas de tickets ─────────────────────────────────────────
ejecutar($pdo, "CREATE TABLE IF NOT EXISTS tickets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    asunto     VARCHAR(255) NOT NULL,
    mensaje    TEXT NOT NULL,
    estado     ENUM('abierto','respondido','cerrado') NOT NULL DEFAULT 'abierto',
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla tickets', $resultados);

ejecutar($pdo, "CREATE TABLE IF NOT EXISTS ticket_respuestas (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    admin_id  INT NOT NULL,
    mensaje   TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla ticket_respuestas', $resultados);

// ── Columna para conversación alumno ─────────────────────────
ejecutar($pdo, 'ALTER TABLE ticket_respuestas ADD COLUMN IF NOT EXISTS alumno_id INT DEFAULT NULL', 'alumno_id en ticket_respuestas', $resultados);

// ── Tabla de logs ─────────────────────────────────────────────
ejecutar($pdo, "CREATE TABLE IF NOT EXISTS logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo        VARCHAR(60) NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    usuario_id  INT NOT NULL DEFAULT 0,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_tipo (tipo),
    INDEX idx_logs_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla logs', $resultados);

// ── Charset utf8mb4 para soporte de emoji ─────────────────────
ejecutar($pdo, "ALTER TABLE cursos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", 'utf8mb4 en cursos', $resultados);
ejecutar($pdo, "ALTER TABLE temas  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", 'utf8mb4 en temas', $resultados);

// ── Columnas para tokens de sesión y bcrypt ───────────────────
ejecutar($pdo, 'ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS token_sesion VARCHAR(128) DEFAULT NULL', 'token_sesion en usuarios', $resultados);
ejecutar($pdo, 'ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS token_expira DATETIME DEFAULT NULL',     'token_expira en usuarios', $resultados);
ejecutar($pdo, 'ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_token (token_sesion(32))', 'índice token_sesion', $resultados);

// ── Tabla de intentos de login (rate limiting) ────────────────
ejecutar($pdo, "CREATE TABLE IF NOT EXISTS login_intentos (
    ip              VARCHAR(45)  NOT NULL,
    intentos        TINYINT      NOT NULL DEFAULT 1,
    bloqueado_hasta DATETIME     DEFAULT NULL,
    ultimo_intento  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla login_intentos', $resultados);

// ── Tabla de errores de frontend ─────────────────────────────
ejecutar($pdo, "CREATE TABLE IF NOT EXISTS errores (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tipo           VARCHAR(30)  NOT NULL DEFAULT 'js',
    mensaje        TEXT         NOT NULL,
    url_pagina     VARCHAR(500) DEFAULT NULL,
    linea          INT          DEFAULT NULL,
    columna        INT          DEFAULT NULL,
    stack          TEXT         DEFAULT NULL,
    usuario_id     INT          DEFAULT NULL,
    usuario_email  VARCHAR(255) DEFAULT NULL,
    user_agent     VARCHAR(500) DEFAULT NULL,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_err_creado (creado_en),
    INDEX idx_err_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla errores', $resultados);

// ── Índices (mejoran rendimiento en JOINs y filtros frecuentes) ──
ejecutar($pdo, 'ALTER TABLE usuarios_cursos ADD INDEX IF NOT EXISTS idx_uc_usuario (usuario_id)', 'índice usuarios_cursos.usuario_id', $resultados);
ejecutar($pdo, 'ALTER TABLE usuarios_cursos ADD INDEX IF NOT EXISTS idx_uc_curso   (curso_id)',   'índice usuarios_cursos.curso_id', $resultados);
ejecutar($pdo, 'ALTER TABLE temas           ADD INDEX IF NOT EXISTS idx_temas_curso (curso_id)',  'índice temas.curso_id', $resultados);
ejecutar($pdo, 'ALTER TABLE materiales      ADD INDEX IF NOT EXISTS idx_mat_tema    (tema_id)',   'índice materiales.tema_id', $resultados);
ejecutar($pdo, 'ALTER TABLE progresos       ADD INDEX IF NOT EXISTS idx_prog_usuario (usuario_id)', 'índice progresos.usuario_id', $resultados);
ejecutar($pdo, 'ALTER TABLE tickets         ADD INDEX IF NOT EXISTS idx_tick_usuario (usuario_id)', 'índice tickets.usuario_id', $resultados);
ejecutar($pdo, 'ALTER TABLE tickets         ADD INDEX IF NOT EXISTS idx_tick_estado  (estado)',     'índice tickets.estado', $resultados);

echo json_encode(['ok' => true, 'resultados' => $resultados], JSON_PRETTY_PRINT);
