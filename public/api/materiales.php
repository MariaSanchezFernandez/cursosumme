<?php
// ─────────────────────────────────────────────────────────────
// api/materiales.php  —  Gestión de materiales de un tema
// GET    ?tema_id=X  → lista materiales del tema
// DELETE ?id=X       → elimina material (archivo + registro BD)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $temaId = isset($_GET['tema_id']) ? (int)$_GET['tema_id'] : 0;
    if (!$temaId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta tema_id']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT id, tipo, nombre, ruta, tamano_kb, subido_en FROM materiales WHERE tema_id = :tema_id ORDER BY subido_en ASC'
    );
    $stmt->execute([':tema_id' => $temaId]);
    echo json_encode(['ok' => true, 'materiales' => $stmt->fetchAll()]);
    exit;
}

// ── PUT (renombrar) ──────────────────────────────────────────
if ($metodo === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE materiales SET nombre=:nombre WHERE id=:id');
    $stmt->execute([':nombre' => trim($body['nombre'] ?? ''), ':id' => (int)$body['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        $body = json_decode(file_get_contents('php://input'), true);
        $id = (int)($body['id'] ?? 0);
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del material']);
        exit;
    }
    // Obtener nombre/tipo/ruta para log y eliminar archivo físico
    $stmt = $pdo->prepare('SELECT nombre, tipo, ruta FROM materiales WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $mat = $stmt->fetch();
    if ($mat) {
        $rutaFisica = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $mat['ruta'];
        if (file_exists($rutaFisica)) { @unlink($rutaFisica); }
    }
    $stmt = $pdo->prepare('DELETE FROM materiales WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($mat) {
        registrar_log($pdo, 'material_eliminado', ucfirst($mat['tipo']) . ' "' . $mat['nombre'] . '" eliminado', 0);
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
