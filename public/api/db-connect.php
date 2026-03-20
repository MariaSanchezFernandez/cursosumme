<?php
// ─────────────────────────────────────────────────────────────
// db-connect.php  —  Helper de conexión PDO compartido
// Uso: require_once __DIR__ . '/db-connect.php';  $pdo = obtenerPDO();
// ─────────────────────────────────────────────────────────────

require_once __DIR__ . '/db-config.php';

function obtenerPDO(): PDO {
    @ini_set('default_socket_timeout', 5);
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NOMBRE . ";";
        return new PDO($dsn, DB_USUARIO, DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'mensaje' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
}
