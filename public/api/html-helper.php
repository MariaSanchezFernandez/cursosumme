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
