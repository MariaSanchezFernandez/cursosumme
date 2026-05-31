<?php
/**
 * html-helper.php  —  Sanitización de HTML de descripciones
 *
 * Lista blanca estricta: solo tags semánticos seguros.
 * Elimina cualquier style/class/script/iframe antes de guardar en BD.
 * Se importa en cursos.php y temas.php.
 */

function limpiarHtml(?string $html): string {
    if (!$html || !trim($html)) return '';

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="rteroot">'
        . $html .
        '</div></body></html>',
        LIBXML_NOWARNING | LIBXML_NOERROR
    );
    libxml_clear_errors();

    $root = $doc->getElementById('rteroot');
    if (!$root) return '';

    $xpath = new DOMXPath($doc);

    // 1. Eliminar tags peligrosos con todo su contenido
    foreach (['script','style','iframe','object','embed','noscript','form','input','button','meta','link'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $el) {
            if ($el->parentNode) $el->parentNode->removeChild($el);
        }
    }

    // 2. Convertir b→strong, i→em para preservarlos en el allowlist
    foreach (['b' => 'strong', 'i' => 'em'] as $viejo => $nuevo) {
        foreach (iterator_to_array($doc->getElementsByTagName($viejo)) as $el) {
            $repl = $doc->createElement($nuevo);
            while ($el->firstChild) $repl->appendChild($el->firstChild);
            if ($el->parentNode) $el->parentNode->replaceChild($repl, $el);
        }
    }

    // 3. Tags permitidos → atributos seguros que conservan
    $permitidos = [
        'p' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [], 'br' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'blockquote' => [],
        'a'    => ['href'],
        'div'  => ['class'],
        'span' => ['class'],
    ];
    // Solo clases con prefijo "desc-" para evitar que clases globales rompan el layout
    $clasesPermitidas = '/^desc-[a-z0-9-]+$/';

    // 4. Recorrer todos los elementos en orden inverso (bottom-up)
    //    .//∗ = solo descendientes de $root, no el propio $root (evita que se desenvuelva a sí mismo)
    foreach (array_reverse(iterator_to_array($xpath->query('.//*', $root))) as $el) {
        if (!$el instanceof DOMElement || !$el->parentNode) continue;
        $tag = strtolower($el->tagName);

        if (isset($permitidos[$tag])) {
            // Quitar atributos no permitidos (style, onclick, data-*, etc.)
            $attrs = [];
            foreach ($el->attributes as $a) $attrs[] = $a->name;
            foreach ($attrs as $nombre) {
                if (!in_array($nombre, $permitidos[$tag])) $el->removeAttribute($nombre);
            }
            // Solo clases desc-* para evitar que nombres globales rompan el layout
            if ($el->hasAttribute('class')) {
                $clases = array_filter(
                    explode(' ', $el->getAttribute('class')),
                    fn($c) => preg_match($clasesPermitidas, trim($c))
                );
                if ($clases) {
                    $el->setAttribute('class', implode(' ', $clases));
                } else {
                    $el->removeAttribute('class');
                }
            }
            // Bloquear href javascript: y data:
            if ($tag === 'a' && $el->hasAttribute('href')) {
                if (preg_match('/^\s*(javascript|data):/i', $el->getAttribute('href'))) {
                    $el->removeAttribute('href');
                }
            }
        } else {
            // Tag no permitido (div, span, font, table, img…): desenvolver conservando hijos
            $parent = $el->parentNode;
            while ($el->firstChild) $parent->insertBefore($el->firstChild, $el);
            $parent->removeChild($el);
        }
    }

    // 5. Eliminar elementos de bloque vacíos
    foreach (iterator_to_array($xpath->query('.//p|.//h2|.//h3|.//h4|.//li|.//blockquote', $root)) as $el) {
        if (!$el instanceof DOMElement || !$el->parentNode) continue;
        $texto = preg_replace('/[\s\xc2\xa0]+/u', '', $el->textContent);
        if ($texto === '') $el->parentNode->removeChild($el);
    }

    // 6. Extraer innerHTML del wrapper
    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }

    return trim($result);
}

/**
 * Extrae un resumen plano (sin HTML) del primer párrafo útil de una
 * descripción HTML. Pensado para tarjetas de listado donde cada curso
 * necesita un teaser propio en lugar de las características genéricas
 * repetidas.
 *
 * - Busca el primer <p> con contenido real (descarta vacíos / sólo
 *   espacios) y, si no encuentra ninguno, cae a strip_tags del bloque.
 * - Normaliza espacios y entidades. Corta en la última palabra antes
 *   de `$max` caracteres y añade ellipsis si recorta.
 */
function extraerResumen(?string $html, int $max = 180): string {
    if (!$html) return '';
    $texto = '';
    if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
        foreach ($matches[1] as $p) {
            $plano = trim(preg_replace('/\s+/u', ' ', strip_tags($p)));
            if ($plano !== '' && mb_strlen($plano) > 20) { $texto = $plano; break; }
        }
    }
    if ($texto === '') {
        $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
    }
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (mb_strlen($texto) > $max) {
        $recorte = mb_substr($texto, 0, $max);
        $espacio = mb_strrpos($recorte, ' ');
        if ($espacio !== false && $espacio > $max - 40) {
            $recorte = mb_substr($recorte, 0, $espacio);
        }
        $texto = rtrim($recorte, ".,;:—-") . '…';
    }
    return $texto;
}

/**
 * Extrae una lista de puntos clave (máx N) de una descripción HTML.
 * Pensado para las tarjetas de listado: cada curso necesita su propia
 * lista de bullets, no las mismas características genéricas repetidas.
 *
 * Selección por orden de afinidad con la intención del editor:
 *   1. .desc-chips li     — lista corta de etiquetas/features
 *   2. .desc-card li      — lista "qué aprenderás" o similar
 *   3. .desc-si li        — solo la parte positiva del bloque "para quién"
 *   4. cualquier <li> que NO esté dentro de .desc-no ni .desc-para-quien
 *
 * Cada item se devuelve como texto plano, ≤90 chars, recortado en palabra.
 */
function extraerPuntos(?string $html, int $max = 4): array {
    if (!$html || !trim($html)) return [];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="root">'
        . $html .
        '</div></body></html>',
        LIBXML_NOWARNING | LIBXML_NOERROR
    );
    libxml_clear_errors();

    $root = $doc->getElementById('root');
    if (!$root) return [];
    $xpath = new DOMXPath($doc);

    $consultas = [
        ".//div[contains(concat(' ',normalize-space(@class),' '),' desc-chips ')]//li",
        ".//div[contains(concat(' ',normalize-space(@class),' '),' desc-card ')]//li",
        ".//div[contains(concat(' ',normalize-space(@class),' '),' desc-si ')]//li",
        ".//li[not(ancestor::div[contains(concat(' ',normalize-space(@class),' '),' desc-no ')])"
            . " and not(ancestor::div[contains(concat(' ',normalize-space(@class),' '),' desc-para-quien ')])]",
    ];

    foreach ($consultas as $q) {
        $nodes = $xpath->query($q, $root);
        if (!$nodes || $nodes->length === 0) continue;
        $puntos = [];
        $vistos = [];
        foreach ($nodes as $li) {
            $t = trim(preg_replace('/\s+/u', ' ', $li->textContent ?? ''));
            if ($t === '') continue;
            // Estricto: ≤4 palabras por item para que la lista quede
            // visual y compacta. Los items más largos se descartan; el
            // editor debe meter chips cortos en .desc-chips si quiere
            // que el curso aparezca aquí.
            $palabras = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
            if (!$palabras || count($palabras) > 4) continue;
            $key = mb_strtolower($t);
            if (isset($vistos[$key])) continue;
            $vistos[$key] = true;
            $puntos[] = $t;
            if (count($puntos) >= $max) break;
        }
        if (!empty($puntos)) return $puntos;
    }
    return [];
}
