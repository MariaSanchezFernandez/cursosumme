<?php
// ─────────────────────────────────────────────────────────────
// api/cursos.php  —  CRUD de cursos
// GET    → lista todos los cursos con número de temas
// POST   → crea un nuevo curso  { titulo, etiqueta, nivel, duracion }
// PUT    → actualiza un curso   { id, titulo, etiqueta, nivel, duracion, activo }
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
require_once __DIR__ . '/html-helper.php';
$pdo = obtenerPDO();

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────
if ($metodo === 'GET') {
    // Modo público: solo cursos activos con precio para la página de precios (sin auth)
    if (!empty($_GET['publicos'])) {
        $stmt = $pdo->query(
            'SELECT c.id, c.titulo, c.etiqueta, c.color, c.imagen, c.precio, c.stripe_price_id,
                    COUNT(t.id) AS num_temas
             FROM cursos c
             LEFT JOIN temas t ON t.curso_id = c.id
             WHERE c.activo = 1 AND c.precio IS NOT NULL
             GROUP BY c.id
             ORDER BY c.etiqueta, c.creado_en DESC'
        );
        echo json_encode(['ok' => true, 'cursos' => $stmt->fetchAll()]);
        exit;
    }

    requireAdmin($pdo);

    $cursos = $pdo->query(
        'SELECT c.id, c.titulo, c.descripcion, c.etiqueta, c.nivel, c.duracion, c.pack, c.pack_color, c.color, c.imagen, c.activo, c.creado_en,
                c.precio, c.stripe_price_id,
                COUNT(t.id) AS num_temas,
                (SELECT COALESCE(SUM(m.duracion_seg), 0)
                 FROM materiales m
                 INNER JOIN temas tt ON tt.id = m.tema_id
                 WHERE tt.curso_id = c.id) AS duracion_seg
         FROM cursos c
         LEFT JOIN temas t ON t.curso_id = c.id
         GROUP BY c.id
         ORDER BY c.pack IS NULL, c.pack, c.creado_en DESC'
    )->fetchAll();

    // Estadísticas de alumnos y progreso medio por curso.
    // progreso_medio = total temas completados / (alumnos × temas totales) × 100
    $statsRaw = $pdo->query(
        'SELECT t.curso_id,
                COUNT(DISTINCT uc.usuario_id)                                              AS alumnos_count,
                COALESCE(ROUND(
                    COUNT(p.tema_id) /
                    NULLIF(COUNT(DISTINCT uc.usuario_id) * COUNT(DISTINCT t.id), 0) * 100
                ), 0)                                                                      AS progreso_medio
         FROM temas t
         JOIN usuarios_cursos uc ON uc.curso_id = t.curso_id
         LEFT JOIN progresos p   ON p.tema_id = t.id AND p.usuario_id = uc.usuario_id
         GROUP BY t.curso_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $stats = [];
    foreach ($statsRaw as $row) {
        $stats[(int)$row['curso_id']] = $row;
    }

    foreach ($cursos as &$c) {
        $s = $stats[(int)$c['id']] ?? [];
        $c['alumnos_count']  = (int)($s['alumnos_count']  ?? 0);
        $c['progreso_medio'] = (int)($s['progreso_medio'] ?? 0);
    }
    unset($c);

    echo json_encode(['ok' => true, 'cursos' => $cursos]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────
if ($metodo === 'POST') {
    requireAdmin($pdo);
    $body = json_decode(file_get_contents('php://input'), true);

    // ── Duplicar curso ──────────────────────────────────────
    if (($body['accion'] ?? '') === 'duplicar') {
        $idOrigen = (int)($body['id'] ?? 0);
        if (!$idOrigen) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del curso a duplicar']);
            exit;
        }

        $origen = $pdo->prepare('SELECT * FROM cursos WHERE id = :id');
        $origen->execute([':id' => $idOrigen]);
        $c = $origen->fetch();
        if (!$c) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensaje' => 'Curso no encontrado']);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // 1. Duplicar curso
            $pdo->prepare(
                'INSERT INTO cursos (titulo, descripcion, etiqueta, nivel, duracion, pack, pack_color, color, imagen, activo)
                 VALUES (:titulo, :descripcion, :etiqueta, :nivel, :duracion, :pack, :pack_color, :color, :imagen, 0)'
            )->execute([
                ':titulo'      => $c['titulo'] . ' (Copia)',
                ':descripcion' => $c['descripcion'],
                ':etiqueta'    => $c['etiqueta'],
                ':nivel'       => $c['nivel'],
                ':duracion'    => $c['duracion'],
                ':pack'        => $c['pack'],
                ':pack_color'  => $c['pack_color'],
                ':color'       => $c['color'],
                ':imagen'      => $c['imagen'],
            ]);
            $nuevoCursoId = (int)$pdo->lastInsertId();

            // 2. Duplicar temas
            $temas = $pdo->prepare('SELECT * FROM temas WHERE curso_id = :id ORDER BY orden ASC');
            $temas->execute([':id' => $idOrigen]);
            foreach ($temas->fetchAll() as $t) {
                $pdo->prepare(
                    'INSERT INTO temas (curso_id, titulo, descripcion, duracion, orden, color)
                     VALUES (:curso_id, :titulo, :descripcion, :duracion, :orden, :color)'
                )->execute([
                    ':curso_id'    => $nuevoCursoId,
                    ':titulo'      => $t['titulo'],
                    ':descripcion' => $t['descripcion'],
                    ':duracion'    => $t['duracion'],
                    ':orden'       => $t['orden'],
                    ':color'       => $t['color'],
                ]);
                $nuevoTemaId = (int)$pdo->lastInsertId();

                // 3. Duplicar materiales (mismos archivos físicos, solo nuevas filas en BD)
                $mats = $pdo->prepare('SELECT * FROM materiales WHERE tema_id = :tema_id');
                $mats->execute([':tema_id' => $t['id']]);
                foreach ($mats->fetchAll() as $m) {
                    $pdo->prepare(
                        'INSERT INTO materiales (tema_id, tipo, nombre, ruta, tamano_kb, duracion_seg)
                         VALUES (:tema_id, :tipo, :nombre, :ruta, :tamano_kb, :duracion_seg)'
                    )->execute([
                        ':tema_id'     => $nuevoTemaId,
                        ':tipo'        => $m['tipo'],
                        ':nombre'      => $m['nombre'],
                        ':ruta'        => $m['ruta'],
                        ':tamano_kb'   => $m['tamano_kb'],
                        ':duracion_seg'=> $m['duracion_seg'],
                    ]);
                }
            }

            $pdo->commit();
            registrar_log($pdo, 'curso_duplicado', 'Curso "' . $c['titulo'] . '" duplicado (nuevo ID ' . $nuevoCursoId . ')', 0);
            echo json_encode(['ok' => true, 'id' => $nuevoCursoId]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al duplicar el curso']);
        }
        exit;
    }

    if (empty($body['titulo']) || empty($body['etiqueta'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Faltan campos obligatorios']);
        exit;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO cursos (titulo, descripcion, etiqueta, nivel, duracion, pack, imagen) VALUES (:titulo, :descripcion, :etiqueta, :nivel, :duracion, :pack, :imagen)'
    );
    $stmt->execute([
        ':titulo'      => trim($body['titulo']),
        ':descripcion' => !empty($body['descripcion']) ? limpiarHtml($body['descripcion']) : null,
        ':etiqueta'    => trim($body['etiqueta']),
        ':nivel'       => trim($body['nivel']),
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':pack'        => !empty($body['pack']) ? trim($body['pack']) : null,
        ':imagen'      => !empty($body['imagen']) ? trim($body['imagen']) : null,
    ]);
    $newId = $pdo->lastInsertId();
    $adminIdPost = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'curso_creado', "Curso \"" . trim($body['titulo']) . "\" creado", $adminIdPost);
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// ── PUT ──────────────────────────────────────────────────────
if ($metodo === 'PUT') {
    requireAdmin($pdo);
    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del curso']);
        exit;
    }
    if (!empty($body['color_only'])) {
        $stmt = $pdo->prepare('UPDATE cursos SET color=:color WHERE id=:id');
        $stmt->execute([':color' => !empty($body['color']) ? trim($body['color']) : null, ':id' => (int)$body['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }
    // `precio` y `stripe_price_id` se actualizan SOLO si vienen en el body.
    // Si no se incluyen, conservan el valor actual (evita que acciones del admin
    // que no tocan el pricing — como cambiar el color del pack — los borren).
    $sets = [
        'titulo=:titulo', 'descripcion=:descripcion', 'etiqueta=:etiqueta', 'nivel=:nivel',
        'duracion=:duracion', 'pack=:pack', 'pack_color=:pack_color', 'color=:color',
        'imagen=:imagen', 'activo=:activo',
    ];
    $params = [
        ':titulo'      => trim($body['titulo'] ?? ''),
        ':descripcion' => isset($body['descripcion']) ? limpiarHtml($body['descripcion']) : null,
        ':etiqueta'    => trim($body['etiqueta'] ?? ''),
        ':nivel'       => trim($body['nivel'] ?? ''),
        ':duracion'    => trim($body['duracion'] ?? ''),
        ':pack'        => !empty($body['pack']) ? trim($body['pack']) : null,
        ':pack_color'  => !empty($body['pack_color']) ? trim($body['pack_color']) : null,
        ':color'       => !empty($body['color']) ? trim($body['color']) : null,
        ':imagen'      => !empty($body['imagen']) ? trim($body['imagen']) : null,
        ':activo'      => isset($body['activo']) ? (int)$body['activo'] : 1,
        ':id'          => (int)$body['id'],
    ];
    if (array_key_exists('precio', $body)) {
        $sets[] = 'precio=:precio';
        $params[':precio'] = ($body['precio'] !== '' && $body['precio'] !== null) ? (float)$body['precio'] : null;
    }
    if (array_key_exists('stripe_price_id', $body)) {
        $sets[] = 'stripe_price_id=:spid';
        $params[':spid']   = !empty($body['stripe_price_id']) ? trim($body['stripe_price_id']) : null;
    }
    $stmt = $pdo->prepare('UPDATE cursos SET ' . implode(', ', $sets) . ' WHERE id=:id');
    $stmt->execute($params);
    $adminIdPut = isset($body['admin_id']) ? (int)$body['admin_id'] : 0;
    registrar_log($pdo, 'curso_editado', "Curso \"" . trim($body['titulo'] ?? '') . "\" editado", $adminIdPut);
    echo json_encode(['ok' => true]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    requireAdmin($pdo);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'Falta el id del curso']);
        exit;
    }
    // Borrar materiales físicos y registros de temas/materiales en cascada
    // (la BD debe tener ON DELETE CASCADE o lo borramos manualmente)
    $temas = $pdo->prepare('SELECT id FROM temas WHERE curso_id = :id');
    $temas->execute([':id' => $id]);
    foreach ($temas->fetchAll() as $t) {
        $pdo->prepare('DELETE FROM materiales WHERE tema_id = :tid')->execute([':tid' => $t['id']]);
    }
    $pdo->prepare('DELETE FROM temas WHERE curso_id = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM usuarios_cursos WHERE curso_id = :id')->execute([':id' => $id]);
    $rowTitulo = $pdo->prepare('SELECT titulo FROM cursos WHERE id = ?');
    $rowTitulo->execute([$id]);
    $tituloCurso = $rowTitulo->fetchColumn() ?: "ID {$id}";
    $pdo->prepare('DELETE FROM cursos WHERE id = :id')->execute([':id' => $id]);
    registrar_log($pdo, 'curso_eliminado', "Curso \"{$tituloCurso}\" eliminado", 0);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
