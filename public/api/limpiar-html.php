<?php
// ─────────────────────────────────────────────────────────────
// api/limpiar-html.php  —  Limpieza única de HTML en descripciones
// Protegido con SETUP_KEY. Uso: GET /api/limpiar-html.php?key=...
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';

if (($_GET['key'] ?? '') !== SETUP_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Clave incorrecta']);
    exit;
}

// ── Función de limpieza HTML ──────────────────────────────────
function limpiarHtml(?string $html): string {
    if (!$html || !trim($html)) return '';

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="r">' . $html . '</div></body></html>',
        LIBXML_NOWARNING | LIBXML_NOERROR
    );
    libxml_clear_errors();

    $root = $doc->getElementById('r');
    if (!$root) return $html;

    $xpath = new DOMXPath($doc);

    // 1. Eliminar atributos presentacionales
    foreach (iterator_to_array($xpath->query('//*[@style or @class or @color or @face or @size or @align]')) as $el) {
        foreach (['style','class','color','face','size','align'] as $attr) {
            if ($el->hasAttribute($attr)) $el->removeAttribute($attr);
        }
    }

    // 2. Desenvuelve etiquetas sin semántica: font, span
    foreach (['font', 'span'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $el) {
            $parent = $el->parentNode;
            if (!$parent) continue;
            while ($el->firstChild) {
                $parent->insertBefore($el->firstChild, $el);
            }
            $parent->removeChild($el);
        }
    }

    // 3. Convierte b→strong, i→em, div→p
    foreach (['b' => 'strong', 'i' => 'em', 'div' => 'p'] as $old => $new) {
        foreach (iterator_to_array($doc->getElementsByTagName($old)) as $el) {
            $newEl = $doc->createElement($new);
            while ($el->firstChild) {
                $newEl->appendChild($el->firstChild);
            }
            $el->parentNode->replaceChild($newEl, $el);
        }
    }

    // 4. Elimina párrafos vacíos (solo espacios o &nbsp;)
    foreach (iterator_to_array($xpath->query('//p')) as $el) {
        $texto = preg_replace('/[\s\xc2\xa0\x{00a0}]+/u', '', $el->textContent);
        $tieneImg = $xpath->query('.//img', $el)->length > 0;
        if ($texto === '' && !$tieneImg && $el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }

    // 5. Elimina BR inicial y final del fragmento
    while ($root->firstChild instanceof DOMElement && strtolower($root->firstChild->tagName) === 'br') {
        $root->removeChild($root->firstChild);
    }
    while ($root->lastChild instanceof DOMElement && strtolower($root->lastChild->tagName) === 'br') {
        $root->removeChild($root->lastChild);
    }

    // 6. Extraer innerHTML del wrapper
    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }

    return trim($result);
}

// ── Conectar y procesar ───────────────────────────────────────
$pdo = obtenerPDO();

$cambios   = [];
$sinCambio = 0;
$errores   = 0;

// Cursos
$cursos = $pdo->query('SELECT id, titulo, descripcion FROM cursos WHERE descripcion IS NOT NULL AND descripcion != ""')->fetchAll();
foreach ($cursos as $c) {
    $limpio = limpiarHtml($c['descripcion']);
    if ($limpio === $c['descripcion']) { $sinCambio++; continue; }
    try {
        $pdo->prepare('UPDATE cursos SET descripcion = :d WHERE id = :id')
            ->execute([':d' => $limpio, ':id' => $c['id']]);
        $cambios[] = ['tipo' => 'curso', 'id' => $c['id'], 'titulo' => $c['titulo']];
    } catch (Exception $e) {
        $errores++;
    }
}

// Temas
$temas = $pdo->query('SELECT id, titulo, descripcion FROM temas WHERE descripcion IS NOT NULL AND descripcion != ""')->fetchAll();
foreach ($temas as $t) {
    $limpio = limpiarHtml($t['descripcion']);
    if ($limpio === $t['descripcion']) { $sinCambio++; continue; }
    try {
        $pdo->prepare('UPDATE temas SET descripcion = :d WHERE id = :id')
            ->execute([':d' => $limpio, ':id' => $t['id']]);
        $cambios[] = ['tipo' => 'tema', 'id' => $t['id'], 'titulo' => $t['titulo']];
    } catch (Exception $e) {
        $errores++;
    }
}

echo json_encode([
    'ok'        => true,
    'actualizados' => count($cambios),
    'sin_cambio'   => $sinCambio,
    'errores'      => $errores,
    'detalle'      => $cambios,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
