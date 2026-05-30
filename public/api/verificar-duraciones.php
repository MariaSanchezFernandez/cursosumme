<?php
// ─────────────────────────────────────────────────────────────
// api/verificar-duraciones.php
// SOLO LECTURA. No modifica nada.
//
// Audita el pipeline de duración:
//   1. Para cada material vídeo, compara su duracion_seg en BD con
//      el length que VdoCipher reporta ahora mismo para ese videoId.
//   2. Para cada tema, comprueba que SUM(materiales.duracion_seg)
//      coincide con el cálculo manual.
//   3. Para cada curso, comprueba el total del curso desde dos vías:
//      la subquery de cursos.php (servidor) y la suma directa.
//
// POST { "key": "<BACKUP_SECRET>" }
//
// Auth: BACKUP_SECRET.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}
$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!defined('BACKUP_SECRET') || !hash_equals(BACKUP_SECRET, (string)($body['key'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}

$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no configurada']);
    exit;
}

function vdoListar(string $apiKey): array {
    $videos = [];
    $page = 1;
    do {
        $ch = curl_init("https://dev.vdocipher.com/api/videos?page={$page}&limit=40");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Apisecret ' . $apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            throw new RuntimeException("VdoCipher list HTTP {$code}: " . substr((string)$res, 0, 300));
        }
        $data = json_decode($res, true);
        $rows = $data['rows'] ?? [];
        foreach ($rows as $v) $videos[$v['id']] = $v;
        $page++;
    } while (count($rows) === 40);
    return $videos;
}

try {
    $pdo = obtenerPDO();

    // 1. Todos los vídeos de VdoCipher (length por ID)
    $vdo = vdoListar($apiKey);

    // 2. Todos los materiales vídeo con su contexto
    $mats = $pdo->query(
        "SELECT m.id AS mat_id, m.nombre, m.duracion_seg, m.vdocipher_video_id, m.vdo_status,
                t.id AS tema_id, t.titulo AS tema_titulo,
                c.id AS curso_id, c.titulo AS curso_titulo
         FROM materiales m
         INNER JOIN temas t ON t.id = m.tema_id
         INNER JOIN cursos c ON c.id = t.curso_id
         WHERE m.tipo = 'video'
         ORDER BY c.id, t.orden, m.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    // 3. Discrepancias material → vs VdoCipher
    $discrepanciasMat = [];
    foreach ($mats as $m) {
        $vid = $m['vdocipher_video_id'];
        if (!$vid || !isset($vdo[$vid])) continue;
        $bd  = $m['duracion_seg'] !== null ? (int)$m['duracion_seg'] : null;
        $vdoLen = (int)($vdo[$vid]['length'] ?? 0);
        if ($vdoLen <= 0) continue;
        if ($bd === null) {
            $discrepanciasMat[] = [
                'tipo'      => 'BD_nula_pero_VDO_tiene',
                'curso'     => $m['curso_titulo'],
                'tema'      => $m['tema_titulo'],
                'material'  => $m['nombre'],
                'mat_id'    => (int)$m['mat_id'],
                'bd_seg'    => null,
                'vdo_seg'   => $vdoLen,
            ];
        } elseif (abs($bd - $vdoLen) > 2) {
            $discrepanciasMat[] = [
                'tipo'      => 'desfase_BD_VDO',
                'curso'     => $m['curso_titulo'],
                'tema'      => $m['tema_titulo'],
                'material'  => $m['nombre'],
                'mat_id'    => (int)$m['mat_id'],
                'bd_seg'    => $bd,
                'vdo_seg'   => $vdoLen,
                'delta_seg' => $bd - $vdoLen,
            ];
        }
    }

    // 4. SUM por tema desde dos vías
    $sumTemaApi = $pdo->query(
        "SELECT t.id, t.titulo, t.curso_id,
                COALESCE(SUM(m.duracion_seg), 0) AS api_seg
         FROM temas t
         LEFT JOIN materiales m ON m.tema_id = t.id AND m.tipo = 'video'
         GROUP BY t.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $sumTemaManual = [];
    foreach ($mats as $m) {
        $tid = (int)$m['tema_id'];
        if ($m['duracion_seg'] !== null) {
            $sumTemaManual[$tid] = ($sumTemaManual[$tid] ?? 0) + (int)$m['duracion_seg'];
        } else {
            $sumTemaManual[$tid] = $sumTemaManual[$tid] ?? 0;
        }
    }

    $discrepanciasTema = [];
    foreach ($sumTemaApi as $t) {
        $tid = (int)$t['id'];
        $api = (int)$t['api_seg'];
        $man = $sumTemaManual[$tid] ?? 0;
        if ($api !== $man) {
            $discrepanciasTema[] = [
                'tema_id' => $tid, 'titulo' => $t['titulo'],
                'api_seg' => $api, 'manual_seg' => $man,
            ];
        }
    }

    // 5. SUM por curso desde dos vías: subquery (igual que cursos.php)
    //    y sumando temas
    $sumCursoApi = $pdo->query(
        "SELECT c.id, c.titulo,
                (SELECT COALESCE(SUM(m.duracion_seg), 0)
                 FROM materiales m
                 INNER JOIN temas tt ON tt.id = m.tema_id
                 WHERE tt.curso_id = c.id AND m.tipo = 'video') AS api_seg
         FROM cursos c
         ORDER BY c.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $sumCursoPorTema = [];
    foreach ($sumTemaApi as $t) {
        $cid = (int)$t['curso_id'];
        $sumCursoPorTema[$cid] = ($sumCursoPorTema[$cid] ?? 0) + (int)$t['api_seg'];
    }

    $discrepanciasCurso = [];
    $resumenCursos = [];
    foreach ($sumCursoApi as $c) {
        $cid = (int)$c['id'];
        $api = (int)$c['api_seg'];
        $sumTemas = $sumCursoPorTema[$cid] ?? 0;
        $h = floor($api / 3600); $min = floor(($api % 3600) / 60);
        $resumenCursos[] = [
            'curso_id' => $cid, 'titulo' => $c['titulo'],
            'segundos' => $api,
            'formateado' => $api > 0 ? ($h > 0 ? "{$h}h ".($min ? "{$min}min" : '') : "{$min}min") : '—',
        ];
        if ($api !== $sumTemas) {
            $discrepanciasCurso[] = [
                'curso_id' => $cid, 'titulo' => $c['titulo'],
                'subquery_seg' => $api, 'suma_temas_seg' => $sumTemas,
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'totales' => [
            'materiales_video' => count($mats),
            'videos_en_vdo'    => count($vdo),
            'temas'            => count($sumTemaApi),
            'cursos'           => count($sumCursoApi),
        ],
        'discrepancias_material_vs_vdocipher' => $discrepanciasMat,
        'discrepancias_sum_tema'              => $discrepanciasTema,
        'discrepancias_sum_curso'             => $discrepanciasCurso,
        'resumen_cursos'                      => $resumenCursos,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('verificar-duraciones: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
