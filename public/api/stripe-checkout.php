<?php
// ─────────────────────────────────────────────────────────────
// api/stripe-checkout.php  —  Crea una sesión Stripe Checkout
// POST body JSON: { tipo: 'curso'|'pack', id: X }
// Devuelve: { ok, url }  (URL de la página de pago de Stripe)
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

// CORS limitado a cursosumme.es (http + https). Mismo origen no requiere CORS.
$ORIGENES_PERMITIDOS = ['http://cursosumme.es', 'https://cursosumme.es'];
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen, $ORIGENES_PERMITIDOS, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/db-config.php';

$pdo  = obtenerPDO();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$tipo = $body['tipo'] ?? '';
$id   = (int)($body['id'] ?? 0);

if (!in_array($tipo, ['curso', 'pack']) || !$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

// Obtener precio e IDs de cursos según tipo
if ($tipo === 'curso') {
    $stmt = $pdo->prepare('SELECT id, titulo, precio, stripe_price_id FROM cursos WHERE id=:id AND activo=1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if (!$item || !$item['stripe_price_id'] || !$item['precio']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Curso no disponible para la venta']);
        exit;
    }
    $stripePriceId = $item['stripe_price_id'];
    $cursosIds     = json_encode([$id]);
    $descripcion   = $item['titulo'];
} else {
    $stmt = $pdo->prepare('SELECT id, nombre, descripcion, precio, stripe_price_id FROM packs WHERE id=:id AND activo=1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
    if (!$item || !$item['stripe_price_id'] || !$item['precio']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'mensaje' => 'Pack no disponible para la venta']);
        exit;
    }
    // Obtener cursos del pack vía tabla N:N pack_cursos (un curso puede estar en varios packs)
    $cStmt = $pdo->prepare(
        'SELECT c.id
         FROM pack_cursos pc
         JOIN cursos c ON c.id = pc.curso_id
         WHERE pc.pack_id = :pid AND c.activo = 1'
    );
    $cStmt->execute([':pid' => $item['id']]);
    $idsPack       = array_map('intval', array_column($cStmt->fetchAll(), 'id'));
    if (empty($idsPack)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'El pack no contiene cursos activos']);
        exit;
    }
    $cursosIds     = json_encode($idsPack);
    $stripePriceId = $item['stripe_price_id'];
    $descripcion   = $item['nombre'];
}

// Crear sesión Stripe Checkout vía REST
$baseUrl = 'http://cursosumme.es';
$params  = [
    'mode'                         => 'payment',
    'line_items[0][price]'         => $stripePriceId,
    'line_items[0][quantity]'      => '1',
    'success_url'                  => $baseUrl . '/pago-ok?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'                   => $baseUrl . '/pago-ko',
    'metadata[tipo]'               => $tipo,
    'metadata[referencia_id]'      => (string)$id,
    'metadata[cursos_ids]'         => $cursosIds,
    'payment_intent_data[metadata][cursos_ids]' => $cursosIds,

    // Campo personalizado para pedir nombre y apellidos del alumno.
    // El webhook lee el valor de session.custom_fields[0].text.value.
    'custom_fields[0][key]'                 => 'nombre',
    'custom_fields[0][label][type]'         => 'custom',
    'custom_fields[0][label][custom]'       => 'Nombre y apellidos',
    'custom_fields[0][type]'                => 'text',
    'custom_fields[0][text][minimum_length]' => '2',
    'custom_fields[0][text][maximum_length]' => '80',
    'custom_fields[0][optional]'            => 'false',
];
// Adjuntar tax_rate (IVA España inclusive) si está configurado.
// Stripe muestra automáticamente Subtotal / IVA / Total en el recibo.
if (defined('STRIPE_TAX_RATE_IVA') && STRIPE_TAX_RATE_IVA) {
    $params['line_items[0][tax_rates][0]'] = STRIPE_TAX_RATE_IVA;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($res, true);
if ($code !== 200 || empty($data['url'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => $data['error']['message'] ?? 'Error al crear sesión de pago']);
    exit;
}

// Registrar pago pendiente en BD
$ins = $pdo->prepare(
    'INSERT INTO pagos (stripe_session_id, email, cursos_ids, importe)
     VALUES (:sid, :email, :cids, :importe)'
);
$ins->execute([
    ':sid'     => $data['id'],
    ':email'   => '',
    ':cids'    => $cursosIds,
    ':importe' => $item['precio'],
]);

echo json_encode(['ok' => true, 'url' => $data['url']]);
