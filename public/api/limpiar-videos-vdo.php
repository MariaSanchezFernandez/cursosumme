<?php
// ─────────────────────────────────────────────────────────────
// api/limpiar-videos-vdo.php
// Detecta y (opcionalmente) fusiona vídeos duplicados de VdoCipher.
//
// Un "duplicado" = varios materiales con el MISMO nombre pero distinto
// vdocipher_video_id (el mismo vídeo subido varias veces antes de existir
// la reutilización). Fusionar = re-apuntar todos los materiales al videoId
// que se conserva y borrar las copias sobrantes del CDN de VdoCipher.
//
// POST { "key": "<BACKUP_SECRET>", "dry_run": true|false }
//   - dry_run ausente o true  → SOLO informe, no toca nada (por seguridad).
//   - dry_run: false          → fusiona los grupos SEGUROS y borra sobrantes.
//
// Seguridad anti-borrado-erróneo: solo fusiona un grupo si TODAS sus copias
// tienen la misma duración (±2 s). Si difieren, lo marca 'revisar_manual'
// y NO lo toca.
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
// Por seguridad, SOLO actúa si dry_run === false explícito. Cualquier otra
// cosa (ausente, true, null) es informe de solo lectura.
$dryRun = !(array_key_exists('dry_run', $body) && $body['dry_run'] === false);

$apiKey = defined('VDOCIPHER_API_KEY') ? VDOCIPHER_API_KEY : '';
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'VDOCIPHER_API_KEY no configurada']);
    exit;
}

function vdoApi(string $method, string $path, string $apiKey): array {
    $ch = curl_init('https://dev.vdocipher.com/api' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Authorization: Apisecret ' . $apiKey, 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true), 'raw' => $res];
}

try {
    $pdo = obtenerPDO();

    // 1. Materiales de vídeo con su tema
    $mats = $pdo->query(
        "SELECT m.id, m.tema_id, m.nombre, m.duracion_seg, m.vdocipher_video_id, m.vdo_status,
                t.titulo AS tema
         FROM materiales m
         LEFT JOIN temas t ON t.id = m.tema_id
         WHERE m.tipo = 'video' AND m.vdocipher_video_id IS NOT NULL
         ORDER BY m.nombre, m.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    // 2. Todos los vídeos de la cuenta VdoCipher (paginado)
    $vdoById = [];
    $page = 1;
    do {
        $r = vdoApi('GET', "/videos?page={$page}&limit=40", $apiKey);
        if ($r['code'] !== 200) {
            throw new RuntimeException("VdoCipher list HTTP {$r['code']}: " . substr((string)$r['raw'], 0, 300));
        }
        $rows = $r['data']['rows'] ?? [];
        foreach ($rows as $v) $vdoById[$v['id']] = $v;
        $page++;
    } while (count($rows) === 40);

    // 3. Agrupar materiales por nombre
    $porNombre = [];
    foreach ($mats as $m) {
        $porNombre[$m['nombre']][] = $m;
    }

    $grupos          = [];   // informe de cada grupo duplicado
    $fusionados      = [];   // grupos efectivamente fusionados
    $copiasBorradas  = [];   // videoIds borrados del CDN

    foreach ($porNombre as $nombre => $lista) {
        $ids = array_values(array_unique(array_map(fn($m) => $m['vdocipher_video_id'], $lista)));
        if (count($ids) <= 1) continue; // no hay duplicado

        // Duraciones por videoId (para el chequeo de seguridad)
        $durPorId = [];
        foreach ($lista as $m) {
            $durPorId[$m['vdocipher_video_id']][] = (int)$m['duracion_seg'];
        }
        $todasDur = array_map('intval', array_column($lista, 'duracion_seg'));
        $durMin = min($todasDur); $durMax = max($todasDur);
        $mismaDur = ($durMax - $durMin) <= 2;

        // Candidato a conservar: existe en VdoCipher, status ready, y con más usos
        $usosPorId = [];
        foreach ($lista as $m) $usosPorId[$m['vdocipher_video_id']] = ($usosPorId[$m['vdocipher_video_id']] ?? 0) + 1;
        $candidatos = array_filter($ids, fn($id) =>
            isset($vdoById[$id]) && ($vdoById[$id]['status'] ?? '') === 'ready');
        usort($candidatos, fn($a, $b) => ($usosPorId[$b] ?? 0) <=> ($usosPorId[$a] ?? 0));
        $canonical = $candidatos[0] ?? null;

        $motivoSkip = null;
        if (!$mismaDur)        $motivoSkip = 'duraciones distintas (posibles vídeos diferentes) — revisar manual';
        elseif (!$canonical)   $motivoSkip = 'ninguna copia existe/ready en VdoCipher — revisar manual';

        $sobrantes = $canonical ? array_values(array_diff($ids, [$canonical])) : [];

        $grupo = [
            'nombre'      => $nombre,
            'copias'      => count($ids),
            'conservar'   => $canonical,
            'borrar'      => $sobrantes,
            'skip'        => $motivoSkip,
            'detalle'     => array_map(function ($id) use ($vdoById, $usosPorId, $durPorId) {
                return [
                    'videoId'   => $id,
                    'usos'      => $usosPorId[$id] ?? 0,
                    'dur_bd'    => $durPorId[$id][0] ?? null,
                    'vdo_existe'=> isset($vdoById[$id]),
                    'vdo_status'=> $vdoById[$id]['status'] ?? null,
                ];
            }, $ids),
        ];

        // 4. Fusionar si procede
        if (!$dryRun && !$motivoSkip && $canonical && $sobrantes) {
            // 4a. Re-apuntar materiales de las copias sobrantes al canonical
            $in  = implode(',', array_fill(0, count($sobrantes), '?'));
            $upd = $pdo->prepare(
                "UPDATE materiales SET vdocipher_video_id = ?
                 WHERE tipo = 'video' AND vdocipher_video_id IN ($in)"
            );
            $upd->execute(array_merge([$canonical], $sobrantes));
            $repuntados = $upd->rowCount();

            // 4b. Borrar las copias sobrantes del CDN de VdoCipher
            $borradosGrupo = [];
            foreach (array_chunk($sobrantes, 10) as $lote) {
                $del = vdoApi('DELETE', '/videos?videos=' . rawurlencode(implode(',', $lote)), $apiKey);
                if ($del['code'] >= 200 && $del['code'] < 300) {
                    foreach ($lote as $id) { $borradosGrupo[] = $id; $copiasBorradas[] = $id; }
                }
            }
            $grupo['repuntados']     = $repuntados;
            $grupo['borrados_cdn']   = $borradosGrupo;
            $fusionados[] = $nombre;
        }

        $grupos[] = $grupo;
    }

    // 5. Huérfanos en VdoCipher (no referenciados) — solo informe
    $referenciados = array_flip(array_map(fn($m) => $m['vdocipher_video_id'], $mats));
    $huerfanos = [];
    foreach ($vdoById as $id => $v) {
        if (!isset($referenciados[$id])) {
            $huerfanos[] = ['videoId' => $id, 'title' => $v['title'] ?? '', 'status' => $v['status'] ?? ''];
        }
    }

    echo json_encode([
        'ok'                  => true,
        'dry_run'             => $dryRun,
        'total_materiales'    => count($mats),
        'total_vdo'           => count($vdoById),
        'grupos_duplicados'   => count($grupos),
        'grupos'              => $grupos,
        'fusionados'          => $fusionados,
        'copias_borradas_cdn' => $copiasBorradas,
        'huerfanos'           => $huerfanos,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('limpiar-videos-vdo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}
