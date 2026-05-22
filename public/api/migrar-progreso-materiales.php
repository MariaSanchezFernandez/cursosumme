<?php
// Migración única: crear tabla progresos_materiales
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db-connect.php';
if (($_GET['key'] ?? '') !== SETUP_KEY) { http_response_code(403); exit; }
$pdo = obtenerPDO();
$pdo->exec("CREATE TABLE IF NOT EXISTS `progresos_materiales` (
  `usuario_id`  INT NOT NULL,
  `material_id` INT NOT NULL,
  `visto_en`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`, `material_id`),
  KEY `idx_pm_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
echo json_encode(['ok' => true, 'mensaje' => 'Tabla progresos_materiales lista']);
