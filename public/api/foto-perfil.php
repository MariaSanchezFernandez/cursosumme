<?php
// ─────────────────────────────────────────────────────────────
// api/foto-perfil.php  —  Foto de perfil del alumno
// GET  ?usuario_id=X → { ok, url } URL de la foto actual (o null)
// POST multipart: archivo + usuario_id → sube foto, devuelve { ok, url }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

// Migración automática
try { $pdo->exec('ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) DEFAULT NULL'); } catch (PDOException $e) {}

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $uid = (int)($_GET['usuario_id'] ?? 0);
    if (!$uid) { http_response_code(400); echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']); exit; }
    $stmt = $pdo->prepare('SELECT foto_perfil FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $uid]);
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'url' => $row ? $row['foto_perfil'] : null]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $uid = (int)($_POST['usuario_id'] ?? 0);
    if (!$uid) { http_response_code(400); echo json_encode(['ok' => false, 'mensaje' => 'Falta usuario_id']); exit; }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'mensaje' => 'No se recibió ningún archivo']);
        exit;
    }

    $ext_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $ext_permitidas)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Solo se permiten imágenes (jpg, png, webp)']);
        exit;
    }

    // Máximo 5 MB
    if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'mensaje' => 'La imagen no puede superar 5 MB']);
        exit;
    }

    $dirDestino = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/fotos/';
    if (!is_dir($dirDestino)) { mkdir($dirDestino, 0755, true); }

    $nombreFinal = 'usuario_' . $uid . '.' . $extension;
    $rutaFisica  = $dirDestino . $nombreFinal;
    $rutaWeb     = '/uploads/fotos/' . $nombreFinal;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
        echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la imagen']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET foto_perfil = :url WHERE id = :id');
    $stmt->execute([':url' => $rutaWeb, ':id' => $uid]);

    echo json_encode(['ok' => true, 'url' => $rutaWeb . '?v=' . time()]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
