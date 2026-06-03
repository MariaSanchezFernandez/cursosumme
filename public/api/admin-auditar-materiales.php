<?php
// ─────────────────────────────────────────────────────────────
// api/admin-auditar-materiales.php
// ─────────────────────────────────────────────────────────────
// Endpoint TEMPORAL de auditoría — SOLO INFORMA, NO BORRA NADA.
//
// Recorre la tabla `materiales` y comprueba para cada fila si el
// archivo al que apunta `ruta` existe físicamente en disco. Reporta
// las filas huérfanas (fila en BD pero archivo ausente) con
// suficiente contexto (curso, tema, nombre) para identificarlas y
// borrarlas a mano desde el admin sin riesgo.
//
// Excluye:
//   - Filas sin `ruta` (vídeos de VdoCipher, que viven en CDN)
//   - Filas con `vdocipher_video_id` no nulo (también vídeos CDN)
//
// Uso:
//   GET /api/admin-auditar-materiales.php
//   → JSON con summary + lista de huérfanos
//
// Requiere autenticación de admin (X-Token de sesión).
// Lectura pura — ningún DELETE/UPDATE.
//
// Una vez resuelto el problema de las huérfanas, borrar este
// archivo del repo y redesplegar para que no quede expuesto.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Token, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/db-connect.php';
$pdo  = obtenerPDO();
requireAdmin($pdo);

// Filas con ruta a archivo local (excluye VdoCipher)
$stmt = $pdo->query(
    "SELECT m.id, m.tema_id, m.tipo, m.nombre, m.ruta, m.tamano_kb, m.subido_en,
            t.titulo AS tema_titulo,
            c.id     AS curso_id,
            c.titulo AS curso_titulo
       FROM materiales m
       LEFT JOIN temas  t ON t.id = m.tema_id
       LEFT JOIN cursos c ON c.id = t.curso_id
      WHERE m.ruta IS NOT NULL
        AND m.ruta != ''
        AND (m.vdocipher_video_id IS NULL OR m.vdocipher_video_id = '')
      ORDER BY c.titulo, t.titulo, m.subido_en"
);
$filas = $stmt->fetchAll();

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$huerfanos = [];
$ok        = 0;

foreach ($filas as $f) {
    $rutaFisica = $docRoot . $f['ruta'];
    if (file_exists($rutaFisica)) {
        $ok++;
    } else {
        $huerfanos[] = [
            'id'           => (int)$f['id'],
            'curso_id'     => $f['curso_id'] ? (int)$f['curso_id'] : null,
            'curso_titulo' => $f['curso_titulo'],
            'tema_id'      => (int)$f['tema_id'],
            'tema_titulo'  => $f['tema_titulo'],
            'tipo'         => $f['tipo'],
            'nombre'       => $f['nombre'],
            'ruta'         => $f['ruta'],
            'tamano_kb'    => $f['tamano_kb'] !== null ? (int)$f['tamano_kb'] : null,
            'subido_en'    => $f['subido_en'],
        ];
    }
}

echo json_encode([
    'ok'              => true,
    'resumen'         => [
        'total_filas_con_ruta' => count($filas),
        'archivos_ok'          => $ok,
        'huerfanas'            => count($huerfanos),
    ],
    'huerfanos'       => $huerfanos,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
