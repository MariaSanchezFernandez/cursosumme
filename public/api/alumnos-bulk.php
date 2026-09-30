<?php
// ─────────────────────────────────────────────────────────────
// api/alumnos-bulk.php  —  Acciones en bloque sobre varios alumnos
// (solo admin). Se usa desde /admin/alumnos al seleccionar filas.
//
// POST { accion, ids: [usuario_id,...], cursos: [curso_id,...], temas: [tema_id,...] }
//
//   accion = 'asignar_cursos'     → da acceso a `cursos` (INSERT IGNORE)
//   accion = 'quitar_cursos'      → retira el acceso a `cursos`
//   accion = 'desbloquear_temas'  → borra los bloqueos de temas por alumna
//                                   (temas_bloqueos_alumno): de todos los temas
//                                   de `cursos` + de los `temas` sueltos; si
//                                   ambos van vacíos, de TODOS
//   accion = 'activar'            → usuarios.activo = 1
//   accion = 'desactivar'         → usuarios.activo = 0 y cierra sus sesiones
//
// Solo actúa sobre usuarios con rol 'alumno' (nunca sobre admins, aunque
// se cuele su id). Todo va en una transacción: o se aplica a todos o a
// ninguno. Devuelve { ok, afectados } con el nº de filas tocadas.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/log-helper.php';
$pdo  = obtenerPDO();
$user = requireAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$accion = (string)($body['accion'] ?? '');
$acciones = ['asignar_cursos', 'quitar_cursos', 'desbloquear_temas', 'activar', 'desactivar'];
if (!in_array($accion, $acciones, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Acción no válida']);
    exit;
}

// Normalizar listas de ids: enteros positivos, sin duplicados
$normalizar = function ($lista): array {
    if (!is_array($lista)) return [];
    return array_values(array_unique(array_filter(array_map('intval', $lista), fn($n) => $n > 0)));
};
$ids    = $normalizar($body['ids'] ?? []);
$cursos = $normalizar($body['cursos'] ?? []);
// Temas sueltos: solo se usan en 'desbloquear_temas'
$temas  = $accion === 'desbloquear_temas' ? $normalizar($body['temas'] ?? []) : [];

if (!$ids) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'No hay alumnos seleccionados']);
    exit;
}
if (count($ids) > 1000 || count($cursos) > 200 || count($temas) > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Demasiados elementos en una sola operación']);
    exit;
}
if (in_array($accion, ['asignar_cursos', 'quitar_cursos'], true) && !$cursos) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Selecciona al menos un curso']);
    exit;
}

// Quedarse solo con ids que sean realmente alumnos
$in = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'alumno' AND id IN ($in)");
$st->execute($ids);
$ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
if (!$ids) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Ninguno de los seleccionados es alumno']);
    exit;
}
$inIds = implode(',', array_fill(0, count($ids), '?'));

// Los cursos deben existir
if ($cursos) {
    $inC = implode(',', array_fill(0, count($cursos), '?'));
    $st = $pdo->prepare("SELECT id FROM cursos WHERE id IN ($inC)");
    $st->execute($cursos);
    $cursos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!$cursos) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Cursos no encontrados']);
        exit;
    }
}
$inCursos = $cursos ? implode(',', array_fill(0, count($cursos), '?')) : '';

$afectados = 0;
$pdo->beginTransaction();
try {
    switch ($accion) {
        case 'asignar_cursos':
            $ins = $pdo->prepare('INSERT IGNORE INTO usuarios_cursos (usuario_id, curso_id) VALUES (:uid, :cid)');
            foreach ($ids as $uid) {
                foreach ($cursos as $cid) {
                    $ins->execute([':uid' => $uid, ':cid' => $cid]);
                    $afectados += $ins->rowCount();
                }
            }
            break;

        case 'quitar_cursos':
            $del = $pdo->prepare("DELETE FROM usuarios_cursos WHERE usuario_id IN ($inIds) AND curso_id IN ($inCursos)");
            $del->execute(array_merge($ids, $cursos));
            $afectados = $del->rowCount();
            break;

        case 'desbloquear_temas':
            if ($cursos || $temas) {
                // Curso entero y/o temas concretos (OR entre ambos)
                $filtros = [];
                $params  = $ids;
                if ($cursos) {
                    $filtros[] = "tema_id IN (SELECT id FROM temas WHERE curso_id IN ($inCursos))";
                    $params = array_merge($params, $cursos);
                }
                if ($temas) {
                    $filtros[] = 'tema_id IN (' . implode(',', array_fill(0, count($temas), '?')) . ')';
                    $params = array_merge($params, $temas);
                }
                $del = $pdo->prepare(
                    "DELETE FROM temas_bloqueos_alumno
                      WHERE usuario_id IN ($inIds) AND (" . implode(' OR ', $filtros) . ')'
                );
                $del->execute($params);
            } else {
                $del = $pdo->prepare("DELETE FROM temas_bloqueos_alumno WHERE usuario_id IN ($inIds)");
                $del->execute($ids);
            }
            $afectados = $del->rowCount();
            break;

        case 'activar':
        case 'desactivar':
            $valor = $accion === 'activar' ? 1 : 0;
            $up = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id IN ($inIds)");
            $up->execute(array_merge([$valor], $ids));
            $afectados = $up->rowCount();
            // Una cuenta desactivada no debe conservar sesiones abiertas
            if ($valor === 0) {
                $pdo->prepare("DELETE FROM sesiones WHERE usuario_id IN ($inIds)")->execute($ids);
            }
            break;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error aplicando la acción en bloque']);
    exit;
}

$detalleCursos = ($cursos ? ' · cursos [' . implode(',', $cursos) . ']' : '')
               . ($temas ? ' · temas [' . implode(',', $temas) . ']' : '');
registrar_log(
    $pdo,
    'alumnos_bulk_' . $accion,
    "Acción en bloque \"{$accion}\" sobre " . count($ids) . ' alumnos [' . implode(',', $ids) . "]{$detalleCursos}. Filas afectadas: {$afectados}",
    (int)($user['id'] ?? 0)
);

echo json_encode(['ok' => true, 'afectados' => $afectados, 'alumnos' => count($ids)]);
