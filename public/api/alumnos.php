<?php
// ─────────────────────────────────────────────────────────────
// api/alumnos.php  —  Gestión de alumnos
// GET  → lista todos los alumnos con número de cursos asignados
// POST → crea un alumno { nombre, apellidos, email, fecha_alta, cursos: [id,...] }
// PUT  → actualiza cursos asignados { id, cursos: [id,...] }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $stmt = $pdo->query(
        'SELECT u.id, u.nombre, u.apellidos, u.email, u.fecha_alta, u.fecha_baja, u.activo, u.foto_perfil,
                SUM(CASE WHEN c.activo = 1 THEN 1 ELSE 0 END) AS num_cursos
         FROM usuarios u
         LEFT JOIN usuarios_cursos uc ON uc.usuario_id = u.id
         LEFT JOIN cursos c ON c.id = uc.curso_id
         WHERE u.rol = \'alumno\'
         GROUP BY u.id
         ORDER BY u.fecha_alta DESC'
    );
    echo json_encode(['ok' => true, 'alumnos' => $stmt->fetchAll()]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $requeridos = ['nombre', 'apellidos', 'email'];
    foreach ($requeridos as $campo) {
        if (empty($body[$campo])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => "Falta el campo: {$campo}"]);
            exit;
        }
    }

    $email = trim(strtolower($body['email']));

    // Verificar que no exista ya
    $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'Ya existe un usuario con ese email']);
        exit;
    }

    // Contraseña por defecto: Umme@2024
    $hashDefault = '4b906bf418f949f42ecb103c146e6ee3cafc4ad4cbb5a4349be0a326fb1ccfaa';
    $fechaAlta   = !empty($body['fecha_alta']) ? $body['fecha_alta'] : date('Y-m-d');
    // fecha_baja: por defecto 1 año desde fecha_alta
    $fechaBaja   = !empty($body['fecha_baja'])
        ? $body['fecha_baja']
        : date('Y-m-d', strtotime($fechaAlta . ' +1 year'));

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellidos, email, contrasena, rol, fecha_alta, fecha_baja)
         VALUES (:nombre, :apellidos, :email, :contrasena, \'alumno\', :fecha_alta, :fecha_baja)'
    );
    $stmt->execute([
        ':nombre'    => trim($body['nombre']),
        ':apellidos' => trim($body['apellidos']),
        ':email'     => $email,
        ':contrasena'=> $hashDefault,
        ':fecha_alta'=> $fechaAlta,
        ':fecha_baja'=> $fechaBaja,
    ]);
    $nuevoId = $pdo->lastInsertId();

    // Asignar cursos
    $cursos = is_array($body['cursos'] ?? null) ? $body['cursos'] : [];
    foreach ($cursos as $cursoId) {
        $stmtUc = $pdo->prepare(
            'INSERT IGNORE INTO usuarios_cursos (usuario_id, curso_id) VALUES (:uid, :cid)'
        );
        $stmtUc->execute([':uid' => $nuevoId, ':cid' => (int)$cursoId]);
    }

    echo json_encode(['ok' => true, 'id' => $nuevoId]);
    exit;
}

// ── PUT  (actualizar alumno: fechas y/o cursos) ───────────────
if ($metodo === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del alumno']);
        exit;
    }
    $uid = (int)$body['id'];

    // Actualizar fechas si se proporcionan
    if (array_key_exists('fecha_baja', $body) || array_key_exists('fecha_alta', $body)) {
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET
               fecha_alta = COALESCE(:fecha_alta, fecha_alta),
               fecha_baja = :fecha_baja
             WHERE id = :id'
        );
        $stmt->execute([
            ':fecha_alta' => !empty($body['fecha_alta']) ? $body['fecha_alta'] : null,
            ':fecha_baja' => !empty($body['fecha_baja']) ? $body['fecha_baja'] : null,
            ':id'         => $uid,
        ]);
    }

    // Actualizar cursos si se proporcionan
    if (isset($body['cursos']) && is_array($body['cursos'])) {
        $cursos = $body['cursos'];
        $pdo->prepare(
            'DELETE uc FROM usuarios_cursos uc
             INNER JOIN cursos c ON c.id = uc.curso_id
             WHERE uc.usuario_id = :uid AND c.activo = 1'
        )->execute([':uid' => $uid]);
        foreach ($cursos as $cursoId) {
            $stmtUc = $pdo->prepare(
                'INSERT IGNORE INTO usuarios_cursos (usuario_id, curso_id) VALUES (:uid, :cid)'
            );
            $stmtUc->execute([':uid' => $uid, ':cid' => (int)$cursoId]);
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
