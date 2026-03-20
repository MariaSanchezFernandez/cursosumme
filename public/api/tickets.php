<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// ── Auto-crear tablas ─────────────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id  INT NOT NULL,
            asunto      VARCHAR(255) NOT NULL,
            mensaje     TEXT NOT NULL,
            estado      ENUM('abierto','respondido','cerrado') NOT NULL DEFAULT 'abierto',
            creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ticket_respuestas (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id   INT NOT NULL,
            admin_id    INT NOT NULL,
            mensaje     TEXT NOT NULL,
            creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Error al crear tablas: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: todos los tickets con info del alumno
    if (isset($_GET['admin'])) {
        // Verificar que el solicitante es realmente admin
        $adminId = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
        if ($adminId) {
            $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $chk->execute([$adminId]);
            $rol = $chk->fetchColumn();
            if ($rol !== 'admin') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
                exit;
            }
        }
        try {
            $stmt = $pdo->query("
                SELECT t.id, t.asunto, t.mensaje, t.estado, t.creado_en,
                       t.usuario_id,
                       CONCAT(u.nombre, ' ', u.apellidos) AS alumno_nombre,
                       u.email AS alumno_email
                FROM tickets t
                JOIN usuarios u ON u.id = t.usuario_id
                ORDER BY t.creado_en DESC
            ");
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tickets)) {
                echo json_encode(['ok' => true, 'tickets' => []]);
                exit;
            }

            $ids = array_map(fn($t) => (int)$t['id'], $tickets);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtR = $pdo->prepare("
                SELECT r.id, r.ticket_id, r.admin_id, r.mensaje, r.creado_en,
                       CONCAT(u.nombre, ' ', u.apellidos) AS admin_nombre
                FROM ticket_respuestas r
                JOIN usuarios u ON u.id = r.admin_id
                WHERE r.ticket_id IN ($placeholders)
                ORDER BY r.creado_en ASC
            ");
            $stmtR->execute($ids);
            $respuestas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar respuestas por ticket_id
            $byTicket = [];
            foreach ($respuestas as $r) {
                $byTicket[(int)$r['ticket_id']][] = $r;
            }

            foreach ($tickets as &$t) {
                $t['respuestas'] = $byTicket[(int)$t['id']] ?? [];
            }
            unset($t);

            echo json_encode(['ok' => true, 'tickets' => $tickets]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Alumno: sus propios tickets
    if (isset($_GET['usuario_id'])) {
        $usuarioId = (int)$_GET['usuario_id'];
        try {
            $stmt = $pdo->prepare("
                SELECT id, usuario_id, asunto, mensaje, estado, creado_en
                FROM tickets
                WHERE usuario_id = ?
                ORDER BY creado_en DESC
            ");
            $stmt->execute([$usuarioId]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tickets)) {
                echo json_encode(['ok' => true, 'tickets' => []]);
                exit;
            }

            $ids = array_map(fn($t) => (int)$t['id'], $tickets);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtR = $pdo->prepare("
                SELECT r.id, r.ticket_id, r.admin_id, r.mensaje, r.creado_en,
                       CONCAT(u.nombre, ' ', u.apellidos) AS admin_nombre
                FROM ticket_respuestas r
                JOIN usuarios u ON u.id = r.admin_id
                WHERE r.ticket_id IN ($placeholders)
                ORDER BY r.creado_en ASC
            ");
            $stmtR->execute($ids);
            $respuestas = $stmtR->fetchAll(PDO::FETCH_ASSOC);

            $byTicket = [];
            foreach ($respuestas as $r) {
                $byTicket[(int)$r['ticket_id']][] = $r;
            }

            foreach ($tickets as &$t) {
                $t['respuestas'] = $byTicket[(int)$t['id']] ?? [];
            }
            unset($t);

            echo json_encode(['ok' => true, 'tickets' => $tickets]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetro requerido: usuario_id o admin']);
    exit;
}

// ── POST: crear ticket ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $usuarioId = isset($body['usuario_id']) ? (int)$body['usuario_id'] : 0;
    $asunto    = isset($body['asunto'])     ? trim($body['asunto'])     : '';
    $mensaje   = isset($body['mensaje'])    ? trim($body['mensaje'])    : '';

    if (!$usuarioId || $asunto === '' || $mensaje === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Faltan campos: usuario_id, asunto, mensaje']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tickets (usuario_id, asunto, mensaje)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$usuarioId, $asunto, $mensaje]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── PUT: responder / cambiar estado ──────────────────────────────────────────
if ($method === 'PUT') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $ticketId = isset($body['ticket_id']) ? (int)$body['ticket_id'] : 0;

    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Falta ticket_id']);
        exit;
    }

    // Solo cambio de estado (alumno cierra ticket)
    if (isset($body['estado']) && !isset($body['mensaje'])) {
        $estadosValidos = ['abierto', 'respondido', 'cerrado'];
        $estado = in_array($body['estado'], $estadosValidos) ? $body['estado'] : null;
        if (!$estado) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Estado no válido']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $ticketId]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Admin responde + actualiza estado
    $adminId = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    $mensaje  = isset($body['mensaje'])  ? trim($body['mensaje'])  : '';
    $estado   = isset($body['estado'])   ? $body['estado']         : 'respondido';

    $estadosValidos = ['abierto', 'respondido', 'cerrado'];
    if (!in_array($estado, $estadosValidos)) $estado = 'respondido';

    if (!$adminId || $mensaje === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Faltan campos: admin_id, mensaje']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtR = $pdo->prepare("
            INSERT INTO ticket_respuestas (ticket_id, admin_id, mensaje)
            VALUES (?, ?, ?)
        ");
        $stmtR->execute([$ticketId, $adminId, $mensaje]);

        $stmtT = $pdo->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmtT->execute([$estado, $ticketId]);

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
