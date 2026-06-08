<?php
// ─────────────────────────────────────────────────────────────
// api/alumnos.php  —  Gestión de alumnos
// GET  → lista todos los alumnos con número de cursos asignados
// POST → crea un alumno { nombre, apellidos, email, fecha_alta, cursos: [id,...] }
// PUT  → actualiza datos personales (nombre, apellidos, email), fechas,
//        flag "alumna de Rocío" y/o cursos asignados { id, ... }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo = obtenerPDO();
requireAdmin($pdo);

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $stmt = $pdo->query(
        'SELECT u.id, u.nombre, u.apellidos, u.email, u.fecha_alta, u.fecha_baja, u.activo, u.foto_perfil,
                u.es_alumna_rocio,
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
    $hashDefault = password_hash('Umme@2024', PASSWORD_BCRYPT, ['cost' => 12]);
    $fechaAlta   = !empty($body['fecha_alta']) ? $body['fecha_alta'] : date('Y-m-d');
    // fecha_baja: por defecto 1 año desde fecha_alta
    $fechaBaja   = !empty($body['fecha_baja'])
        ? $body['fecha_baja']
        : date('Y-m-d', strtotime($fechaAlta . ' +1 year'));

    $esRocio = !empty($body['es_alumna_rocio']) ? 1 : 0;

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, apellidos, email, contrasena, rol, fecha_alta, fecha_baja, es_alumna_rocio)
         VALUES (:nombre, :apellidos, :email, :contrasena, \'alumno\', :fecha_alta, :fecha_baja, :es_rocio)'
    );
    $stmt->execute([
        ':nombre'    => trim($body['nombre']),
        ':apellidos' => trim($body['apellidos']),
        ':email'     => $email,
        ':contrasena'=> $hashDefault,
        ':fecha_alta'=> $fechaAlta,
        ':fecha_baja'=> $fechaBaja,
        ':es_rocio'  => $esRocio,
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

    $adminId = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'alumno_creado', "Alumno {$body['nombre']} {$body['apellidos']} ({$email}) creado", $adminId);
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

    // ── Datos personales (nombre, apellidos, email) ──────────────
    // Solo se tocan los campos enviados. El email es el identificador de
    // acceso: se normaliza, se valida y se comprueba que no colisione con
    // otro usuario antes de guardarlo.
    if (array_key_exists('nombre', $body) || array_key_exists('apellidos', $body) || array_key_exists('email', $body)) {
        $campos = [];
        $params = [':id' => $uid];

        if (array_key_exists('nombre', $body)) {
            $nombre = trim((string)$body['nombre']);
            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'mensaje' => 'El nombre no puede estar vacío']);
                exit;
            }
            $campos[] = 'nombre = :nombre';
            $params[':nombre'] = $nombre;
        }

        if (array_key_exists('apellidos', $body)) {
            $campos[] = 'apellidos = :apellidos';
            $params[':apellidos'] = trim((string)$body['apellidos']);
        }

        $emailAntiguo = null;
        if (array_key_exists('email', $body)) {
            $email = trim(strtolower((string)$body['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'mensaje' => 'El email no es válido']);
                exit;
            }
            // No permitir colisión con OTRO usuario
            $dup = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
            $dup->execute([':email' => $email, ':id' => $uid]);
            if ($dup->fetch()) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'mensaje' => 'Ya existe otro usuario con ese email']);
                exit;
            }
            // Guardar el email anterior para el log de auditoría
            $stOld = $pdo->prepare('SELECT email FROM usuarios WHERE id = :id LIMIT 1');
            $stOld->execute([':id' => $uid]);
            $emailAntiguo = $stOld->fetchColumn() ?: null;

            $campos[] = 'email = :email';
            $params[':email'] = $email;
        }

        if ($campos) {
            $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);

            // El cambio de email es sensible (afecta al acceso) → log dedicado
            if ($emailAntiguo !== null && $emailAntiguo !== $email) {
                $adminIdEmail = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
                registrar_log($pdo, 'alumno_email_cambiado',
                    "Email del alumno ID {$uid} cambiado de {$emailAntiguo} a {$email}", $adminIdEmail);
            }
        }
    }

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

    // Actualizar flag "alumna de Rocío" si se proporciona
    if (array_key_exists('es_alumna_rocio', $body)) {
        $pdo->prepare('UPDATE usuarios SET es_alumna_rocio = :v WHERE id = :id')
            ->execute([':v' => !empty($body['es_alumna_rocio']) ? 1 : 0, ':id' => $uid]);
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

    $adminIdPut = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'alumno_actualizado', "Alumno ID {$uid} actualizado", $adminIdPut);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id']);
        exit;
    }
    $alumno = $pdo->prepare('SELECT nombre, email FROM usuarios WHERE id=:id AND rol="alumno" LIMIT 1');
    $alumno->execute([':id' => $id]);
    $a = $alumno->fetch();
    if (!$a) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Alumno no encontrado']);
        exit;
    }
    $pdo->prepare('DELETE FROM usuarios_cursos WHERE usuario_id=:id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM progresos WHERE usuario_id=:id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM usuarios WHERE id=:id')->execute([':id' => $id]);
    registrar_log($pdo, 'alumno_eliminado', "Alumno \"{$a['nombre']}\" ({$a['email']}) eliminado", 0);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
