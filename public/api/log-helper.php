<?php
// ─────────────────────────────────────────────────────────────
// log-helper.php  —  Función para registrar acciones en el log
// Uso: require_once __DIR__ . '/log-helper.php';
//      registrar_log($pdo, 'curso_creado', 'Curso "X" creado', $adminId);
// ─────────────────────────────────────────────────────────────

function registrar_log(PDO $pdo, string $tipo, string $descripcion, int $usuario_id = 0): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs (tipo, descripcion, usuario_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$tipo, $descripcion, $usuario_id]);
    } catch (Throwable $e) {
        // No interrumpir la operación principal si falla el log
    }
}
