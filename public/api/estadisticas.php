<?php
// ─────────────────────────────────────────────────────────────
// api/estadisticas.php  —  Datos agregados para el panel admin
// GET → {
//   ok,
//   resumen: { total_alumnos, activos_7d, activos_30d, sin_avance,
//              avance_medio_global, temas_totales, materiales_vistos_total },
//   alumnos: [{ id, nombre, apellidos, email, fecha_alta, fecha_baja,
//               num_cursos, temas_completados, total_temas, porcentaje,
//               ultima_actividad }],
//   cursos:  [{ id, titulo, num_alumnos, num_temas,
//               completaciones_totales, porcentaje_medio }]
// }
// Solo admin.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo = obtenerPDO();
requireAdmin($pdo);

try {
    // ───────────────────────────────────────────────────────
    // 1) Resumen global
    // ───────────────────────────────────────────────────────
    $totalAlumnos = (int)$pdo->query(
        "SELECT COUNT(*) FROM usuarios WHERE rol = 'alumno' AND activo = 1"
    )->fetchColumn();

    // Actividad reciente: max(visto_en) de progresos_materiales, progresos
    // Y max(creado_en) de sesiones (login reciente, aunque no marque vídeos).
    $activos7 = (int)$pdo->query("
        SELECT COUNT(DISTINCT usuario_id) FROM (
            SELECT usuario_id, MAX(visto_en) AS ult
              FROM progresos_materiales
             GROUP BY usuario_id
            UNION ALL
            SELECT usuario_id, MAX(visto_en) AS ult
              FROM progresos
             GROUP BY usuario_id
            UNION ALL
            SELECT usuario_id, MAX(ultimo_uso) AS ult
              FROM sesiones
             GROUP BY usuario_id
        ) t
        WHERE ult >= (NOW() - INTERVAL 7 DAY)
    ")->fetchColumn();

    $activos30 = (int)$pdo->query("
        SELECT COUNT(DISTINCT usuario_id) FROM (
            SELECT usuario_id, MAX(visto_en) AS ult
              FROM progresos_materiales
             GROUP BY usuario_id
            UNION ALL
            SELECT usuario_id, MAX(visto_en) AS ult
              FROM progresos
             GROUP BY usuario_id
            UNION ALL
            SELECT usuario_id, MAX(ultimo_uso) AS ult
              FROM sesiones
             GROUP BY usuario_id
        ) t
        WHERE ult >= (NOW() - INTERVAL 30 DAY)
    ")->fetchColumn();

    $temasTotales = (int)$pdo->query("
        SELECT COUNT(*) FROM temas t
        INNER JOIN cursos c ON c.id = t.curso_id
        WHERE c.activo = 1
    ")->fetchColumn();

    $materialesVistosTotal = (int)$pdo->query(
        "SELECT COUNT(*) FROM progresos_materiales"
    )->fetchColumn();

    // ───────────────────────────────────────────────────────
    // 2) Avance por alumno
    //    Para cada alumno activo:
    //      - num_cursos asignados (activos)
    //      - total_temas: temas pertenecientes a sus cursos activos
    //      - temas_completados: filas en `progresos` cuyo tema está en
    //        un curso asignado y activo
    //      - ultima_actividad: max(visto_en) entre progresos y progresos_materiales
    // ───────────────────────────────────────────────────────
    $sqlAlumnos = "
        SELECT
          u.id, u.nombre, u.apellidos, u.email,
          u.fecha_alta, u.fecha_baja,
          (SELECT COUNT(*)
             FROM usuarios_cursos uc
             INNER JOIN cursos c ON c.id = uc.curso_id
            WHERE uc.usuario_id = u.id AND c.activo = 1
          ) AS num_cursos,
          (SELECT COUNT(*)
             FROM temas t
             INNER JOIN cursos c  ON c.id  = t.curso_id
             INNER JOIN usuarios_cursos uc ON uc.curso_id = c.id
            WHERE uc.usuario_id = u.id AND c.activo = 1
          ) AS total_temas,
          (SELECT COUNT(*)
             FROM progresos p
             INNER JOIN temas t ON t.id = p.tema_id
             INNER JOIN cursos c ON c.id = t.curso_id
             INNER JOIN usuarios_cursos uc
                  ON uc.curso_id = c.id AND uc.usuario_id = p.usuario_id
            WHERE p.usuario_id = u.id AND c.activo = 1
          ) AS temas_completados,
          (SELECT COUNT(*)
             FROM materiales m
             INNER JOIN temas  t ON t.id = m.tema_id
             INNER JOIN cursos c ON c.id = t.curso_id
             INNER JOIN usuarios_cursos uc ON uc.curso_id = c.id
            WHERE uc.usuario_id = u.id AND c.activo = 1 AND m.tipo = 'video'
          ) AS total_videos,
          (SELECT COUNT(*)
             FROM progresos_materiales pm
             INNER JOIN materiales m ON m.id = pm.material_id
             INNER JOIN temas  t ON t.id = m.tema_id
             INNER JOIN cursos c ON c.id = t.curso_id
             INNER JOIN usuarios_cursos uc
                  ON uc.curso_id = c.id AND uc.usuario_id = pm.usuario_id
            WHERE pm.usuario_id = u.id AND c.activo = 1 AND m.tipo = 'video'
          ) AS videos_vistos,
          GREATEST(
            COALESCE((SELECT MAX(visto_en)   FROM progresos_materiales WHERE usuario_id = u.id), '1970-01-01'),
            COALESCE((SELECT MAX(visto_en)   FROM progresos             WHERE usuario_id = u.id), '1970-01-01'),
            COALESCE((SELECT MAX(ultimo_uso) FROM sesiones              WHERE usuario_id = u.id), '1970-01-01')
          ) AS ultima_actividad_raw
        FROM usuarios u
        WHERE u.rol = 'alumno' AND u.activo = 1
        ORDER BY u.fecha_alta DESC
    ";
    $alumnos = $pdo->query($sqlAlumnos)->fetchAll(PDO::FETCH_ASSOC);

    $sumPorcentajes  = 0.0;
    $cuentaConTemas  = 0;
    $sinAvance       = 0;

    foreach ($alumnos as &$a) {
        $a['num_cursos']        = (int)$a['num_cursos'];
        $a['total_temas']       = (int)$a['total_temas'];
        $a['temas_completados'] = (int)$a['temas_completados'];
        $a['total_videos']      = (int)$a['total_videos'];
        $a['videos_vistos']     = (int)$a['videos_vistos'];
        $a['porcentaje'] = $a['total_temas'] > 0
            ? round(($a['temas_completados'] / $a['total_temas']) * 100, 1)
            : 0.0;

        // 1970 = sin actividad jamás
        $a['ultima_actividad'] = (strpos($a['ultima_actividad_raw'] ?? '', '1970') === 0)
            ? null
            : $a['ultima_actividad_raw'];
        unset($a['ultima_actividad_raw']);

        if ($a['total_temas'] > 0) {
            $sumPorcentajes += $a['porcentaje'];
            $cuentaConTemas++;
            if ($a['temas_completados'] === 0) {
                $sinAvance++;
            }
        }
    }
    unset($a);

    $avanceMedio = $cuentaConTemas > 0
        ? round($sumPorcentajes / $cuentaConTemas, 1)
        : 0.0;

    // ───────────────────────────────────────────────────────
    // 3) Avance por curso
    //    Para cada curso activo:
    //      - num_alumnos asignados
    //      - num_temas
    //      - completaciones_totales: suma de temas completados en `progresos`
    //        sumando solo alumnos que tienen el curso asignado
    //      - porcentaje_medio: completaciones / (num_alumnos * num_temas) * 100
    // ───────────────────────────────────────────────────────
    $sqlCursos = "
        SELECT
          c.id, c.titulo,
          (SELECT COUNT(*) FROM usuarios_cursos uc
            WHERE uc.curso_id = c.id) AS num_alumnos,
          (SELECT COUNT(*) FROM temas t WHERE t.curso_id = c.id) AS num_temas,
          (SELECT COUNT(*)
             FROM progresos p
             INNER JOIN temas t ON t.id = p.tema_id
             INNER JOIN usuarios_cursos uc
               ON uc.curso_id = c.id AND uc.usuario_id = p.usuario_id
            WHERE t.curso_id = c.id
          ) AS completaciones_totales
        FROM cursos c
        WHERE c.activo = 1
        ORDER BY c.titulo ASC
    ";
    $cursos = $pdo->query($sqlCursos)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cursos as &$c) {
        $c['num_alumnos']            = (int)$c['num_alumnos'];
        $c['num_temas']              = (int)$c['num_temas'];
        $c['completaciones_totales'] = (int)$c['completaciones_totales'];
        $denominador = $c['num_alumnos'] * $c['num_temas'];
        $c['porcentaje_medio'] = $denominador > 0
            ? round(($c['completaciones_totales'] / $denominador) * 100, 1)
            : 0.0;
    }
    unset($c);

    echo json_encode([
        'ok'      => true,
        'resumen' => [
            'total_alumnos'           => $totalAlumnos,
            'activos_7d'              => $activos7,
            'activos_30d'             => $activos30,
            'sin_avance'              => $sinAvance,
            'avance_medio_global'     => $avanceMedio,
            'temas_totales'           => $temasTotales,
            'materiales_vistos_total' => $materialesVistosTotal,
        ],
        'alumnos' => $alumnos,
        'cursos'  => $cursos,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error al calcular estadísticas',
        'detalle' => $e->getMessage(),
    ]);
}
