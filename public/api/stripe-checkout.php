<?php
// ─────────────────────────────────────────────────────────────
// api/stripe-checkout.php  —  Crea una sesión Stripe Checkout
//
// FLUJO COMPLIANCE (RGPD + derecho desistimiento art. 103.m LGDCU):
//   1. Valida todos los datos fiscales del formulario de /checkout.
//   2. Auto-migra las columnas nuevas de `pagos` si aún no existen.
//   3. Guarda en `pagos` una fila pendiente con TODO el formulario,
//      la IP real del cliente, su user-agent y el texto EXACTO de la
//      casilla de desistimiento que firmó. Esta fila es la evidencia
//      legal de que la alumna renunció al desistimiento antes de pagar.
//   4. Crea un Customer en Stripe con name/email/phone/address y
//      tax_id_data (es_nif/es_cif), reaprovecha si ya existía.
//   5. Crea la sesión Checkout vinculada al customer, con metadata que
//      incluye desistimiento_aceptado + timestamp para auditoría.
//   6. Devuelve { ok, url } y el frontend redirige a Stripe.
//
// POST body JSON:
//   {
//     tipo: 'curso'|'pack', id: X,
//     nombre, apellidos, email, telefono,
//     direccion_calle, direccion_ciudad, direccion_provincia,
//     direccion_cp, direccion_pais (opt, default ES),
//     dni_nif, es_empresa (bool),
//     desistimiento_aceptado (bool, OBLIGATORIO true),
//     desistimiento_texto (string, texto literal mostrado en el checkbox)
//   }
//
// Devuelve: { ok, url }  (URL de la página de pago de Stripe)
// Errores 400 con { ok:false, mensaje, campo? } para que el cliente
// pinte el error al lado del campo en falta.
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
require_once __DIR__ . '/validar-fiscal.php';

$pdo  = obtenerPDO();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Auto-migración defensiva ──────────────────────────────────
// Si el deploy del SQL no se ha ejecutado, añadimos las columnas
// que necesitamos (mismo patrón que el resto del proyecto).
$migraciones = [
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(200) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS telefono VARCHAR(40) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_calle VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_ciudad VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_provincia VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_cp VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS direccion_pais VARCHAR(2) DEFAULT 'ES'",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS dni_nif VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS es_empresa TINYINT(1) DEFAULT 0",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS ip_cliente VARCHAR(45) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS desistimiento_texto TEXT DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS desistimiento_en DATETIME DEFAULT NULL",
    "ALTER TABLE pagos ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS telefono VARCHAR(40) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_calle VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_ciudad VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_provincia VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_cp VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS direccion_pais VARCHAR(2) DEFAULT 'ES'",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS dni_nif VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS es_empresa TINYINT(1) DEFAULT 0",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) DEFAULT NULL",
];
foreach ($migraciones as $m) { try { $pdo->exec($m); } catch (PDOException $e) {} }

// ── Validación de producto ────────────────────────────────────
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
    $cStmt = $pdo->prepare(
        'SELECT c.id
         FROM pack_cursos pc
         JOIN cursos c ON c.id = pc.curso_id
         WHERE pc.pack_id = :pid AND c.activo = 1'
    );
    $cStmt->execute([':pid' => $item['id']]);
    $idsPack = array_map('intval', array_column($cStmt->fetchAll(), 'id'));
    if (empty($idsPack)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'mensaje' => 'El pack no contiene cursos activos']);
        exit;
    }
    $cursosIds     = json_encode($idsPack);
    $stripePriceId = $item['stripe_price_id'];
    $descripcion   = $item['nombre'];
}

// ── Validación del formulario fiscal/personal ──────────────────
$nombre     = trim((string)($body['nombre']     ?? ''));
$apellidos  = trim((string)($body['apellidos']  ?? ''));
$email      = strtolower(trim((string)($body['email'] ?? '')));
$telefono   = trim((string)($body['telefono']   ?? ''));
$calle      = trim((string)($body['direccion_calle']     ?? ''));
$ciudad     = trim((string)($body['direccion_ciudad']    ?? ''));
$provincia  = trim((string)($body['direccion_provincia'] ?? ''));
$cp         = trim((string)($body['direccion_cp']        ?? ''));
$pais       = strtoupper(trim((string)($body['direccion_pais'] ?? 'ES'))) ?: 'ES';
$esEmpresa  = !empty($body['es_empresa']);
$dniRaw     = trim((string)($body['dni_nif'] ?? ''));
$desistAcep = !empty($body['desistimiento_aceptado']);
$desistTxt  = trim((string)($body['desistimiento_texto'] ?? ''));

$campoFalla = function (string $msg, string $campo) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => $msg, 'campo' => $campo]);
    exit;
};

if ($nombre === '')                                                               $campoFalla('El nombre es obligatorio', 'nombre');
if ($apellidos === '')                                                            $campoFalla('Los apellidos son obligatorios', 'apellidos');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))                                   $campoFalla('Email no válido', 'email');
if ($telefono === '' || !preg_match('/^[\+\d\s\-\(\)]{6,30}$/', $telefono))      $campoFalla('Teléfono no válido', 'telefono');
if ($calle === '')                                                                $campoFalla('La dirección es obligatoria', 'direccion_calle');
if ($ciudad === '')                                                               $campoFalla('La ciudad es obligatoria', 'direccion_ciudad');
if ($provincia === '')                                                            $campoFalla('La provincia es obligatoria', 'direccion_provincia');
if ($cp === '' || !preg_match('/^\d{5}$/', $cp))                                  $campoFalla('Código postal no válido (5 dígitos)', 'direccion_cp');
if (!preg_match('/^[A-Z]{2}$/', $pais))                                           $campoFalla('País no válido', 'direccion_pais');

$fiscal = validar_fiscal($dniRaw, $esEmpresa);
if (!$fiscal['ok'])                                                               $campoFalla($fiscal['mensaje'], 'dni_nif');
$dniNif = $fiscal['valor'];

if (!$desistAcep)                                                                 $campoFalla('Debes aceptar la cláusula de desistimiento para continuar', 'desistimiento');
if (mb_strlen($desistTxt) < 60)                                                   $campoFalla('Texto de desistimiento inválido', 'desistimiento');

// ── IP real del cliente (respetando proxies / CDN) ────────────
// Prioridad: CF-Connecting-IP > X-Real-IP > primer salto de X-Forwarded-For > REMOTE_ADDR.
// Nunca confiamos en cabeceras arbitrarias si REMOTE_ADDR no es un proxy
// conocido — pero IONOS shared no expone esa info, así que cogemos la
// primera fuente disponible y validamos formato.
$ipCliente = '';
$fuentesIp = [
    $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
    $_SERVER['HTTP_X_REAL_IP']        ?? '',
    explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0] ?? '',
    $_SERVER['REMOTE_ADDR']           ?? '',
];
foreach ($fuentesIp as $candidata) {
    $candidata = trim($candidata);
    if ($candidata && filter_var($candidata, FILTER_VALIDATE_IP)) {
        $ipCliente = $candidata;
        break;
    }
}
$userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$nombreCompleto = trim($nombre . ' ' . $apellidos);

// ── Crear o reutilizar Customer en Stripe ─────────────────────
// Si el email ya tiene customer, reusamos para no duplicar (Stripe
// permite varios con el mismo email pero ensucia el panel y dificulta
// la trazabilidad fiscal).
$customerId = null;
$buscaC = curl_init('https://api.stripe.com/v1/customers/search?' . http_build_query([
    'query' => 'email:"' . str_replace('"', '\\"', $email) . '"',
    'limit' => 1,
]));
curl_setopt_array($buscaC, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$resBuscaRaw = curl_exec($buscaC);
$resBuscaCod = curl_getinfo($buscaC, CURLINFO_HTTP_CODE);
curl_close($buscaC);
if ($resBuscaCod === 200) {
    $resBusca = json_decode($resBuscaRaw, true);
    if (!empty($resBusca['data'][0]['id'])) {
        $customerId = $resBusca['data'][0]['id'];
    }
}

// Datos del Customer (se aplican a creación o actualización)
$paramsCustomer = [
    'name'              => $nombreCompleto,
    'email'             => $email,
    'phone'             => $telefono,
    'address[line1]'    => $calle,
    'address[city]'     => $ciudad,
    'address[state]'    => $provincia,
    'address[postal_code]' => $cp,
    'address[country]'  => $pais,
    'metadata[dni_nif]'    => $dniNif,
    'metadata[es_empresa]' => $esEmpresa ? '1' : '0',
];

if ($customerId) {
    // Actualizar con los datos nuevos (puede haber cambiado dirección/teléfono)
    $upd = curl_init('https://api.stripe.com/v1/customers/' . urlencode($customerId));
    curl_setopt_array($upd, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_POSTFIELDS     => http_build_query($paramsCustomer),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    curl_exec($upd);
    curl_close($upd);
} else {
    // Crear customer. tax_id_data SOLO va si es empresa (CIF) — Stripe
    // no acepta el DNI español como tax_id ('es_nif' no existe en su
    // catálogo). Para particulares el DNI vive en metadata.
    $tipoTaxId = stripe_tax_id_type($esEmpresa);
    if ($tipoTaxId !== null) {
        $paramsCustomer['tax_id_data[0][type]']  = $tipoTaxId;
        $paramsCustomer['tax_id_data[0][value]'] = $dniNif;
    }
    $crea = curl_init('https://api.stripe.com/v1/customers');
    curl_setopt_array($crea, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_POSTFIELDS     => http_build_query($paramsCustomer),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resCrea  = curl_exec($crea);
    $codCrea  = curl_getinfo($crea, CURLINFO_HTTP_CODE);
    curl_close($crea);
    if ($codCrea !== 200) {
        $errC = json_decode($resCrea, true);
        http_response_code(502);
        echo json_encode(['ok' => false, 'mensaje' => $errC['error']['message'] ?? 'No se pudo crear el cliente en Stripe']);
        exit;
    }
    $dataCust = json_decode($resCrea, true);
    $customerId = $dataCust['id'] ?? null;
}

if (!$customerId) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo obtener customer_id de Stripe']);
    exit;
}

// ── Crear sesión Stripe Checkout ──────────────────────────────
$baseUrl = 'https://cursosumme.es';
$ahoraIso = gmdate('c'); // ISO 8601 UTC

$params  = [
    'mode'                         => 'payment',
    'customer'                     => $customerId,

    // El customer ya trae nombre, email, teléfono, dirección y tax_id.
    // Stripe NO volverá a pedir el email (lo pre-rellena y lo bloquea)
    // ni la dirección de facturación. El "nombre del titular de la
    // tarjeta" sí lo sigue pidiendo siempre — no es del Customer sino
    // de la propia tarjeta, no se puede ocultar.
    //
    // customer_update[name|address] = auto permite a Stripe actualizar
    // el customer si la usuaria cambiase algo en Stripe (ej. el nombre
    // del titular distinto al del comprador). Sin esto Stripe ABORTA
    // la sesión cuando el customer ya tiene address/name (regla suya
    // para evitar machacar datos por descuido).
    'customer_update[name]'        => 'auto',
    'customer_update[address]'     => 'auto',
    'billing_address_collection'   => 'auto', // ya viene en el customer

    'line_items[0][price]'         => $stripePriceId,
    'line_items[0][quantity]'      => '1',
    'success_url'                  => $baseUrl . '/pago-ok?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'                   => $baseUrl . '/pago-ko',

    // Generar factura automáticamente: Stripe crea y finaliza una factura
    // (PDF con numeración correlativa + IVA desglosado + datos fiscales del
    // Customer: NIF/CIF, dirección) tras el pago. Si "Email finalized
    // invoices" está activo en el panel de Stripe, se la envía al cliente.
    'invoice_creation[enabled]'                   => 'true',
    'invoice_creation[invoice_data][description]' => $descripcion,

    // Metadata legal/auditoría — copia EXACTA en la sesión Stripe del
    // valor que guardamos en BD, para que un revisor pueda contrastar.
    'metadata[tipo]'                          => $tipo,
    'metadata[referencia_id]'                 => (string)$id,
    'metadata[cursos_ids]'                    => $cursosIds,
    'metadata[desistimiento_aceptado]'        => 'true',
    'metadata[desistimiento_en]'              => $ahoraIso,
    'metadata[dni_nif]'                       => $dniNif,
    'metadata[es_empresa]'                    => $esEmpresa ? '1' : '0',
    'payment_intent_data[metadata][cursos_ids]'             => $cursosIds,
    'payment_intent_data[metadata][desistimiento_aceptado]' => 'true',
];
// Adjuntar tax_rate (IVA España inclusive) si está configurado.
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

// ── Registrar pago pendiente con TODA la info fiscal/desistimiento ─
$ins = $pdo->prepare(
    'INSERT INTO pagos
        (stripe_session_id, email, cursos_ids, importe,
         nombre_completo, telefono,
         direccion_calle, direccion_ciudad, direccion_provincia, direccion_cp, direccion_pais,
         dni_nif, es_empresa, ip_cliente, user_agent,
         desistimiento_texto, desistimiento_en, stripe_customer_id)
     VALUES
        (:sid, :email, :cids, :importe,
         :nom, :tel,
         :calle, :ciudad, :prov, :cp, :pais,
         :dni, :esemp, :ip, :ua,
         :dtxt, NOW(), :cust)'
);
$ins->execute([
    ':sid'    => $data['id'],
    ':email'  => $email,
    ':cids'   => $cursosIds,
    ':importe'=> $item['precio'],
    ':nom'    => $nombreCompleto,
    ':tel'    => $telefono,
    ':calle'  => $calle,
    ':ciudad' => $ciudad,
    ':prov'   => $provincia,
    ':cp'     => $cp,
    ':pais'   => $pais,
    ':dni'    => $dniNif,
    ':esemp'  => $esEmpresa ? 1 : 0,
    ':ip'     => $ipCliente,
    ':ua'     => $userAgent,
    ':dtxt'   => $desistTxt,
    ':cust'   => $customerId,
]);

echo json_encode(['ok' => true, 'url' => $data['url']]);
