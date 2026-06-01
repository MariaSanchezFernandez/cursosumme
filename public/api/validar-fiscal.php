<?php
// ─────────────────────────────────────────────────────────────
// api/validar-fiscal.php  —  Validación de DNI / NIE / CIF
//
// Funciones:
//   normalizar_fiscal($valor)              → string en mayúsculas sin espacios/guiones
//   validar_dni_nie($valor)                → bool
//   validar_cif($valor)                    → bool
//   validar_fiscal($valor, $esEmpresa)     → ['ok'=>bool, 'tipo'=>'dni'|'nie'|'cif', 'valor'=>'...', 'mensaje'=>'...']
//   stripe_tax_id_type($esEmpresa)         → 'es_nif' (DNI/NIE) | 'es_cif' (empresa)
//
// Algoritmos:
//   DNI: 8 dígitos + letra = chars[mod 23] de "TRWAGMYFPDXBNJZSQVHLCKE"
//   NIE: prefijo X/Y/Z (= 0/1/2) + 7 dígitos + letra (mismo algoritmo)
//   CIF: letra + 7 dígitos + control (letra o dígito; cálculo Luhn-like mod 10)
// ─────────────────────────────────────────────────────────────

function normalizar_fiscal(string $valor): string {
    return strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));
}

function validar_dni_nie(string $valor): bool {
    $v = normalizar_fiscal($valor);
    // NIE: convertir X→0, Y→1, Z→2 para el cálculo
    if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $v)) {
        $primera = ['X' => '0', 'Y' => '1', 'Z' => '2'][$v[0]];
        $numero  = $primera . substr($v, 1, 7);
        $letra   = $v[8];
    } elseif (preg_match('/^\d{8}[A-Z]$/', $v)) {
        $numero = substr($v, 0, 8);
        $letra  = $v[8];
    } else {
        return false;
    }
    $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    return $letras[(int)$numero % 23] === $letra;
}

function validar_cif(string $valor): bool {
    $v = normalizar_fiscal($valor);
    if (!preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $v)) return false;
    $digitos = substr($v, 1, 7);
    $control = $v[8];

    // Suma de dígitos en posiciones pares (1,3,5 de los 7 dígitos)
    $par   = (int)$digitos[1] + (int)$digitos[3] + (int)$digitos[5];

    // Suma de dígitos en posiciones impares multiplicados por 2, sumando sus cifras
    $impar = 0;
    foreach ([0, 2, 4, 6] as $i) {
        $d = (int)$digitos[$i] * 2;
        $impar += ($d >= 10) ? (1 + ($d - 10)) : $d;
    }

    $sumaTotal = $par + $impar;
    $unidad    = $sumaTotal % 10;
    $digitoCtrl = $unidad === 0 ? 0 : 10 - $unidad;

    // Algunas letras iniciales obligan a letra de control (no dígito).
    // 'P','Q','R','S','N','W' → control siempre letra. 'A','B','E','H' → siempre dígito.
    // El resto admiten ambos. La letra equivalente al dígito 0-9 es 'JABCDEFGHI'.
    $primera = $v[0];
    $letraEq = 'JABCDEFGHI'[$digitoCtrl];

    if (strpos('PQRSNW', $primera) !== false) {
        return $control === $letraEq;
    }
    if (strpos('ABEH', $primera) !== false) {
        return $control === (string)$digitoCtrl;
    }
    return $control === (string)$digitoCtrl || $control === $letraEq;
}

function validar_fiscal(string $valor, bool $esEmpresa): array {
    $v = normalizar_fiscal($valor);
    if ($v === '') {
        return ['ok' => false, 'mensaje' => 'DNI/NIF requerido', 'valor' => '', 'tipo' => ''];
    }
    if ($esEmpresa) {
        if (validar_cif($v)) return ['ok' => true, 'valor' => $v, 'tipo' => 'cif', 'mensaje' => ''];
        return ['ok' => false, 'mensaje' => 'CIF inválido', 'valor' => $v, 'tipo' => 'cif'];
    }
    if (validar_dni_nie($v)) {
        $tipo = preg_match('/^[XYZ]/', $v) ? 'nie' : 'dni';
        return ['ok' => true, 'valor' => $v, 'tipo' => $tipo, 'mensaje' => ''];
    }
    return ['ok' => false, 'mensaje' => 'DNI/NIE inválido', 'valor' => $v, 'tipo' => 'dni'];
}

// Stripe SOLO admite `es_cif` (empresa) y `eu_vat` (VAT europeo) en la
// API tax_id_data. El DNI/NIE de un particular NO es un tax_id en el
// catálogo Stripe — se guarda en customer.metadata.dni_nif y se usa
// para emitir factura completa cuando sea necesario.
//
// Devuelve null cuando NO procede mandar tax_id_data (particular con DNI/NIE).
function stripe_tax_id_type(bool $esEmpresa): ?string {
    return $esEmpresa ? 'es_cif' : null;
}
