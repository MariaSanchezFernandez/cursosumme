<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAuth($pdo);

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Admin: todos los tickets con info del alumno
    if (isset($_GET['admin'])) {
        if ($user['rol'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
            exit;
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
                SELECT r.id, r.ticket_id, r.admin_id, r.alumno_id, r.mensaje, r.creado_en,
                       CASE WHEN r.alumno_id IS NOT NULL THEN CONCAT(ua.nombre, ' ', ua.apellidos) ELSE NULL END AS alumno_nombre
                FROM ticket_respuestas r
                LEFT JOIN usuarios ua ON ua.id = r.alumno_id
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
        if ($usuarioId !== (int)$user['id'] && $user['rol'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
            exit;
        }
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
                SELECT r.id, r.ticket_id, r.admin_id, r.alumno_id, r.mensaje, r.creado_en
                FROM ticket_respuestas r
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
    rateLimit($pdo, 'tickets_post', 5);
    $body      = json_decode(file_get_contents('php://input'), true);
    $usuarioId = (int)$user['id'];
    $asunto    = sanitizeText($body['asunto']  ?? '', 200);
    $mensaje   = sanitizeText($body['mensaje'] ?? '', 5000);

    if ($asunto === '' || $mensaje === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Faltan campos: asunto y mensaje']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tickets (usuario_id, asunto, mensaje)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$usuarioId, $asunto, $mensaje]);
        $newTId = (int)$pdo->lastInsertId();
        registrar_log($pdo, 'ticket_creado', "Ticket \"" . mb_substr($asunto, 0, 60) . "\" abierto por usuario ID {$usuarioId}", $usuarioId);
        echo json_encode(['ok' => true, 'id' => $newTId]);
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
        // Verificar que el ticket pertenece al usuario (o es admin)
        if ($user['rol'] !== 'admin') {
            $chkOwn = $pdo->prepare('SELECT usuario_id FROM tickets WHERE id = ?');
            $chkOwn->execute([$ticketId]);
            $ownerId = (int)$chkOwn->fetchColumn();
            if ($ownerId !== (int)$user['id']) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
                exit;
            }
        }
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

    // Alumno responde (reply en conversación)
    if (isset($body['alumno_id']) && isset($body['mensaje']) && !isset($body['admin_id'])) {
        $alumnoId = (int)$user['id'];
        if ($alumnoId !== (int)$body['alumno_id'] && $user['rol'] !== 'admin') {
            http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
        }
        $mensaje  = sanitizeText($body['mensaje'] ?? '', 5000);
        if (!$alumnoId || $mensaje === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Faltan campos: alumno_id, mensaje']);
            exit;
        }
        try {
            $stmtR = $pdo->prepare("
                INSERT INTO ticket_respuestas (ticket_id, admin_id, alumno_id, mensaje)
                VALUES (?, 0, ?, ?)
            ");
            $stmtR->execute([$ticketId, $alumnoId, $mensaje]);
            // Reabrir ticket si estaba respondido
            $pdo->prepare("UPDATE tickets SET estado = 'abierto' WHERE id = ? AND estado = 'respondido'")
                ->execute([$ticketId]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Admin responde + actualiza estado
    if ($user['rol'] !== 'admin') {
        http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
    }
    $adminId = (int)$user['id'];
    $mensaje  = sanitizeText($body['mensaje'] ?? '', 5000);
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
        registrar_log($pdo, 'ticket_respondido', "Admin respondió al ticket ID {$ticketId}", $adminId);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


// ── DELETE: eliminar ticket ───────────────────────────────────
if ($method === 'DELETE') {
    if ($user['rol'] !== 'admin') {
        http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Acceso denegado']); exit;
    }
    $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$ticketId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Falta id del ticket']);
        exit;
    }
    try {
        $asuntoRow = $pdo->prepare('SELECT asunto FROM tickets WHERE id = ?');
        $asuntoRow->execute([$ticketId]);
        $asunto = $asuntoRow->fetchColumn() ?: "ID {$ticketId}";
        $pdo->prepare('DELETE FROM ticket_respuestas WHERE ticket_id = ?')->execute([$ticketId]);
        $pdo->prepare('DELETE FROM tickets WHERE id = ?')->execute([$ticketId]);
        registrar_log($pdo, 'ticket_eliminado', "Ticket \"{$asunto}\" eliminado", 0);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
