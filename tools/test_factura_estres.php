<?php
/**
 * Prueba de estrés del flujo de facturación:
 *   A) Motor de cálculo (CalculoService) con escenarios límite, sin tocar BD.
 *   B) Creación real de facturas por el mismo camino que la API (store).
 *   C) Generación del XML Verifactu SIN enviar a la AEAT.
 *
 * Uso: php tools/test_factura_estres.php
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/core/Validator.php';
require_once __DIR__ . '/../src/core/NifInvalidoException.php';
require_once __DIR__ . '/../src/models/Factura.php';
require_once __DIR__ . '/../src/models/Cliente.php';
require_once __DIR__ . '/../src/services/CalculoService.php';
require_once __DIR__ . '/../src/services/NumeracionService.php';
require_once __DIR__ . '/../src/services/VerifactuService.php';

$fallos = 0;
$avisos = 0;

function ok(string $msg): void   { echo "  [OK]    {$msg}\n"; }
function fail(string $msg): void { global $fallos; $fallos++; echo "  [FALLO] {$msg}\n"; }
function warn(string $msg): void { global $avisos; $avisos++; echo "  [AVISO] {$msg}\n"; }

/** Comprueba la coherencia interna del resultado de CalculoService::calcular(). */
function verificarCoherencia(string $nombre, array $r): void
{
    $eps = 0.011; // tolerancia de redondeo (1 céntimo)

    $sumaCuotas = 0.0;
    $sumaBases  = 0.0;
    $sumaRE     = 0.0;
    foreach ($r['cuotas_iva'] as $c) {
        $sumaCuotas += $c['Cuota_IVA'];
        $sumaBases  += $c['Base_Imponible'];
        $sumaRE     += $c['Cuota_RE'];
    }

    $checks = [
        'base + IVA + RE = total' =>
            abs($r['base_imponible'] + $r['importe_iva'] + $r['importe_re'] - $r['total']) <= $eps,
        'suma cuotas grupos = importe_iva' =>
            abs($sumaCuotas - $r['importe_iva']) <= $eps,
        'suma bases grupos = base_imponible' =>
            abs($sumaBases - $r['base_imponible']) <= $eps,
        'suma RE grupos = importe_re' =>
            abs($sumaRE - $r['importe_re']) <= $eps,
        'descuentos en cascada coherentes' =>
            abs($r['net_subtotal']
                - $r['descuentos']['Importe_Dto_Especial']
                - $r['descuentos']['Importe_Dto_Comercial']
                - $r['descuentos']['Importe_Dto_PP']
                - $r['base_imponible']) <= $eps,
    ];

    foreach ($checks as $desc => $pasa) {
        if ($pasa) {
            ok("{$nombre}: {$desc}");
        } else {
            fail("{$nombre}: {$desc} — base={$r['base_imponible']} iva={$r['importe_iva']} re={$r['importe_re']} total={$r['total']} sumaCuotas=" . round($sumaCuotas, 4) . " sumaBases=" . round($sumaBases, 4));
        }
    }
    if (!empty($r['errores'])) {
        warn("{$nombre}: errores del servicio: " . implode(' | ', $r['errores']));
    }
}

$calc = new CalculoService();

echo "═══ PARTE A: motor de cálculo (en memoria, sin escribir en BD) ═══\n\n";

$escenarios = [
    'A1 todos los IVA peninsulares + dtos de línea variados' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1,      'Precio' => 100,       'Descuento' => 0,    'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 2.5,    'Precio' => 33.333,    'Descuento' => 15.5, 'Id_Tipo_IVA' => '02'],
            ['Id_Articulo' => 'AR4343', 'Cantidad' => 7,      'Precio' => 9.99,      'Descuento' => 50,   'Id_Tipo_IVA' => '03'],
            ['Id_Articulo' => 'ART772', 'Cantidad' => 3,      'Precio' => 20,        'Descuento' => 0,    'Id_Tipo_IVA' => '04', 'Calificacion' => 'E1'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1,      'Precio' => 50,        'Descuento' => 100,  'Id_Tipo_IVA' => '01'],
        ],
        'descuentos' => [],
        're' => 0,
    ],
    'A2 descuentos globales en cascada (5% + 2.5% + 1%)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 10, 'Precio' => 123.45, 'Descuento' => 10, 'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 4,  'Precio' => 55.55,  'Descuento' => 0,  'Id_Tipo_IVA' => '02'],
        ],
        'descuentos' => ['Descuento_Especial' => 5, 'Descuento_Comercial' => 2.5, 'Descuento_PP' => 1],
        're' => 0,
    ],
    'A3 recargo de equivalencia (cliente RE 5.2%)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 3, 'Precio' => 100, 'Descuento' => 0,  'Id_Tipo_IVA' => '01', 'Aplica_RE' => 1],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 2, 'Precio' => 40,  'Descuento' => 20, 'Id_Tipo_IVA' => '02', 'Aplica_RE' => 1],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 60,  'Descuento' => 0,  'Id_Tipo_IVA' => '01'],
        ],
        'descuentos' => ['Descuento_Especial' => 3],
        're' => 5.2,
    ],
    'A4 todas las líneas con descuento 100% (neto 0)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 5, 'Precio' => 80, 'Descuento' => 100, 'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 1, 'Precio' => 10, 'Descuento' => 100, 'Id_Tipo_IVA' => '02'],
        ],
        'descuentos' => ['Descuento_PP' => 10],
        're' => 0,
    ],
    'A5 importes minúsculos (redondeo)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 0.001, 'Precio' => 0.01,  'Descuento' => 0,    'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 3,     'Precio' => 0.333, 'Descuento' => 33.33, 'Id_Tipo_IVA' => '02'],
            ['Id_Articulo' => 'AR4343', 'Cantidad' => 1,     'Precio' => 0.005, 'Descuento' => 0,    'Id_Tipo_IVA' => '03'],
        ],
        'descuentos' => ['Descuento_Especial' => 1.5],
        're' => 0,
    ],
    'A6 importes enormes' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 99999, 'Precio' => 12345.6789, 'Descuento' => 7.77, 'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART004', 'Cantidad' => 88888, 'Precio' => 9999.9999,  'Descuento' => 0,    'Id_Tipo_IVA' => '02'],
        ],
        'descuentos' => ['Descuento_Comercial' => 12.5],
        're' => 0,
    ],
    'A7 calificaciones mixtas S1+E1+N1+S2' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => '01', 'Calificacion' => 'S1'],
            ['Id_Articulo' => 'ART772', 'Cantidad' => 1, 'Precio' => 200, 'Descuento' => 0, 'Id_Tipo_IVA' => '04', 'Calificacion' => 'E1'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 300, 'Descuento' => 0, 'Id_Tipo_IVA' => '01', 'Calificacion' => 'N1'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 400, 'Descuento' => 0, 'Id_Tipo_IVA' => '01', 'Calificacion' => 'S2'],
        ],
        'descuentos' => [],
        're' => 0,
    ],
    'A8 cantidad negativa (línea de abono)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 5,  'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => -2, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => '01'],
        ],
        'descuentos' => [],
        're' => 0,
    ],
    'A9 neto total negativo (abono completo)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => -3, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => '01'],
        ],
        'descuentos' => [],
        're' => 0,
    ],
    'A10 tipo de IVA inexistente (debe dar error)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => 'NOEXISTE'],
        ],
        'descuentos' => [],
        're' => 0,
        'espera_error' => true,
    ],
    'A11 territorios mezclados (debe dar error)' => [
        'lineas' => [
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => '01'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => 'IGIC7'],
            ['Id_Articulo' => 'ART001', 'Cantidad' => 1, 'Precio' => 100, 'Descuento' => 0, 'Id_Tipo_IVA' => 'IPSI4'],
        ],
        'descuentos' => [],
        're' => 0,
        'espera_error' => true,
    ],
    'A12 muchas líneas (60) con datos variados' => [
        'lineas' => (function () {
            $ls = [];
            $ivas = ['01', '02', '03', '04'];
            for ($i = 1; $i <= 60; $i++) {
                $ls[] = [
                    'Id_Articulo' => 'ART001',
                    'Cantidad'    => round(0.5 + ($i * 0.37), 4),
                    'Precio'      => round(1 + ($i * 7.531), 4),
                    'Descuento'   => ($i % 4) * 12.5,
                    'Id_Tipo_IVA' => $ivas[$i % 4],
                ];
            }
            return $ls;
        })(),
        'descuentos' => ['Descuento_Especial' => 2, 'Descuento_Comercial' => 3, 'Descuento_PP' => 4],
        're' => 0,
    ],
];

foreach ($escenarios as $nombre => $esc) {
    echo "── {$nombre}\n";
    try {
        $r = $calc->calcular($esc['lineas'], $esc['descuentos'], (float)$esc['re']);
        if (!empty($esc['espera_error'])) {
            if (!empty($r['errores'])) {
                ok("{$nombre}: rechazado como se esperaba: " . $r['errores'][0]);
            } else {
                fail("{$nombre}: debería devolver error y no lo hace");
            }
        } else {
            verificarCoherencia($nombre, $r);
            echo "        subtotal={$r['subtotal']}  base={$r['base_imponible']}  iva={$r['importe_iva']}  re={$r['importe_re']}  total={$r['total']}\n";
        }
    } catch (Throwable $e) {
        fail("{$nombre}: EXCEPCIÓN " . get_class($e) . ': ' . $e->getMessage());
    }
    echo "\n";
}

echo "═══ PARTE B: creación real de facturas (mismo camino que la API) ═══\n\n";

/** Réplica de FacturaController::normalizarLineasParaInsert(). */
function normalizarLineas(array $lineas): array
{
    $out = [];
    foreach ($lineas as $l) {
        $row = [
            'Linea'             => (int)($l['Linea'] ?? 0),
            'Id_Articulo'       => (string)($l['Id_Articulo'] ?? ''),
            'Descripcion'       => (string)($l['Descripcion'] ?? ''),
            'Cantidad'          => (float)($l['Cantidad'] ?? 0),
            'Precio'            => (float)($l['Precio'] ?? 0),
            'Descuento'         => (float)($l['Descuento'] ?? 0),
            'Id_Tipo_IVA'       => (string)($l['Id_Tipo_IVA'] ?? ''),
            'Importe_Bruto'     => (float)($l['Importe_Bruto'] ?? 0),
            'Importe_Descuento' => (float)($l['Importe_Descuento'] ?? 0),
            'Base_Imponible'    => (float)($l['Base_Imponible'] ?? 0),
            'Total'             => (float)($l['Total'] ?? 0),
            'RE'                => (float)($l['RE'] ?? 0),
            'Aplica_RE'         => ($l['Aplica_RE'] ?? 0) ? 'S' : 'N',
            'Calificacion'      => (string)($l['Calificacion'] ?? 'S1'),
            'Clave_Regimen'     => (string)($l['Clave_Regimen'] ?? '01'),
        ];
        if ($row['Linea'] <= 0 || $row['Id_Articulo'] === '' || $row['Cantidad'] == 0.0) continue;
        $out[] = $row;
    }
    return $out;
}

/** Réplica del flujo store(): valida, calcula, numera y crea. Devuelve [codigo, resultadoCalculo]. */
function crearFacturaDePrueba(array $data, CalculoService $calc): array
{
    $errors = Validator::validarFactura($data);
    if (!empty($errors)) {
        throw new RuntimeException('Validación: ' . implode(' | ', $errors));
    }

    $clienteModel = new Cliente();
    $cliente = $clienteModel->find($data['Id_Cliente']);
    if (!$cliente) throw new RuntimeException('Cliente no encontrado: ' . $data['Id_Cliente']);

    $nifCliente = trim((string)($cliente['NIF'] ?? ''));
    $errorNif = Validator::validarFormatoNif($nifCliente);
    if ($errorNif !== null) throw new RuntimeException("NIF cliente inválido: {$errorNif}");

    $numeracion = (new NumeracionService())->asignarNumero(
        $data['Id_Canal'],
        (int)date('Y', strtotime($data['Fecha'])),
        $data['Tipo_Documento']
    );

    $descuentos = [
        'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
        'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
        'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
    ];
    $rePorcentaje = (float)($cliente['RE_Porcentaje'] ?? 0);

    $r = $calc->calcular($data['lineas'], $descuentos, $rePorcentaje, $data['Tipo_Documento']);
    if (!empty($r['errores'])) throw new RuntimeException('Cálculo: ' . implode(' | ', $r['errores']));

    $facturaData = [
        'Codigo'               => $numeracion['codigo'],
        'Numero'               => $numeracion['numero'],
        'Id_Canal'             => $data['Id_Canal'],
        'Fecha'                => $data['Fecha'],
        'Id_Cliente'           => $data['Id_Cliente'],
        'Id_Forma_Pago'        => $data['Id_Forma_Pago'],
        'Tipo_Documento'       => $data['Tipo_Documento'],
        'Abono'                => 'N',
        'Observaciones'        => $data['Observaciones'] ?? '',
        'Descuento_Especial'   => $descuentos['Descuento_Especial'],
        'Descuento_PP'         => $descuentos['Descuento_PP'],
        'Descuento_Comercial'  => $descuentos['Descuento_Comercial'],
        'Importe_Dto_Especial' => $r['descuentos']['Importe_Dto_Especial'],
        'Importe_Dto_PP'       => $r['descuentos']['Importe_Dto_PP'],
        'Importe_Bruto'        => $r['subtotal'],
        'Base_Imponible'       => $r['base_imponible'],
        'Cuota_IVA'            => $r['importe_iva'],
        'Total'                => $r['total'],
        'Cerrada'              => 'N',
        'Cobrada'              => 'N',
    ];

    $lineasGuardar = $calc->generarLineasParaGuardar($data['lineas'], $descuentos, $rePorcentaje);
    $facturaData['lineas'] = normalizarLineas($lineasGuardar);

    $codigo = (new Factura())->create($facturaData);
    return [$codigo, $r];
}

/** Genera el XML Verifactu sin enviar, usando un registro sintético (no se toca la cadena de hashes). */
function generarXmlSinEnviar(string $codigo): string
{
    $service = new VerifactuService();
    $doc = (new Factura())->findWithLines($codigo);
    if (!$doc) throw new RuntimeException("Factura no encontrada: {$codigo}");

    $registroFake = [
        'Id'              => 0,
        'Cadena_Firma'    => '',
        'Huella_Anterior' => '',
        'Huella_Actual'   => '',
    ];

    $ref = new ReflectionMethod(VerifactuService::class, 'generarXmlConWrapper');
    return (string)$ref->invoke($service, 'FACTURA', $codigo, $doc, $registroFake, null, false, null);
}

/** Valida que el XML está bien formado y que los totales cuadran con la factura. */
function verificarXml(string $nombre, string $xml, float $totalEsperado): void
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        fail("{$nombre}: el XML generado no está bien formado");
        return;
    }
    ok("{$nombre}: XML bien formado (" . strlen($xml) . " bytes)");

    if (preg_match('/<sum1:ImporteTotal>([\-0-9.]+)<\/sum1:ImporteTotal>/', $xml, $m)) {
        $importeXml = (float)$m[1];
        if (abs($importeXml - $totalEsperado) <= 0.011) {
            ok("{$nombre}: ImporteTotal del XML ({$importeXml}) cuadra con el total de la factura ({$totalEsperado})");
        } else {
            // El total Verifactu excluye calificaciones que no suman (p.ej. N1); avisar, no fallar
            warn("{$nombre}: ImporteTotal XML ({$importeXml}) difiere del total factura ({$totalEsperado}) — revisar si hay líneas no sujetas/exentas que lo justifiquen");
        }
    } else {
        fail("{$nombre}: no se encontró <ImporteTotal> en el XML");
    }
    if (preg_match('/<sum1:Huella>([0-9A-F]{64})<\/sum1:Huella>/', $xml)) {
        ok("{$nombre}: huella SHA-256 presente");
    } else {
        fail("{$nombre}: falta la huella en el XML");
    }
}

$hoy = date('Y-m-d');

// ── B1: factura monstruo, 16 líneas, todos los IVA y descuentos
$facturaMonstruo = [
    'Id_Canal'            => 'ACCI',
    'Fecha'               => $hoy,
    'Id_Cliente'          => '001',
    'Id_Forma_Pago'       => 'TRF',
    'Tipo_Documento'      => 'FACTURA',
    'Observaciones'       => 'PRUEBA AUTOMÁTICA - factura de estrés (puede borrarse)',
    'Descuento_Especial'  => 5,
    'Descuento_Comercial' => 2.5,
    'Descuento_PP'        => 1,
    'lineas' => [
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Consultoría',        'Cantidad' => 10,     'Precio' => 75,        'Descuento' => 0,     'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART002', 'Descripcion' => 'Licencia',           'Cantidad' => 1,      'Precio' => 1234.56,   'Descuento' => 15.5,  'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART003', 'Descripcion' => 'Soporte',            'Cantidad' => 12,     'Precio' => 49.99,     'Descuento' => 10,    'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART004', 'Descripcion' => 'Material oficina',   'Cantidad' => 2.5,    'Precio' => 33.333,    'Descuento' => 0,     'Id_Tipo_IVA' => '02'],
        ['Id_Articulo' => 'ART012', 'Descripcion' => 'Papel A4',           'Cantidad' => 6,      'Precio' => 18.75,     'Descuento' => 25,    'Id_Tipo_IVA' => '02'],
        ['Id_Articulo' => 'AR4343', 'Descripcion' => 'Superreducido',      'Cantidad' => 7,      'Precio' => 9.99,      'Descuento' => 50,    'Id_Tipo_IVA' => '03'],
        ['Id_Articulo' => 'ART77',  'Descripcion' => 'Superreducido 2',    'Cantidad' => 0.5,    'Precio' => 200,       'Descuento' => 0,     'Id_Tipo_IVA' => '03'],
        ['Id_Articulo' => 'ART772', 'Descripcion' => 'Exento E1',          'Cantidad' => 3,      'Precio' => 20,        'Descuento' => 0,     'Id_Tipo_IVA' => '04', 'Calificacion' => 'E1'],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Gratis (dto 100%)',  'Cantidad' => 1,      'Precio' => 50,        'Descuento' => 100,   'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Cantidad decimal',   'Cantidad' => 3.333,  'Precio' => 14.142,    'Descuento' => 33.33, 'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Precio céntimo',     'Cantidad' => 100,    'Precio' => 0.01,      'Descuento' => 0,     'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART009', 'Descripcion' => 'Portátiles',         'Cantidad' => 25,     'Precio' => 899.90,    'Descuento' => 8.25,  'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Precio 4 decimales', 'Cantidad' => 9,      'Precio' => 11.1111,   'Descuento' => 0,     'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART004', 'Descripcion' => 'Reducido mini',      'Cantidad' => 0.001,  'Precio' => 999,       'Descuento' => 0,     'Id_Tipo_IVA' => '02'],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Dto 99.99%',         'Cantidad' => 2,      'Precio' => 500,       'Descuento' => 99.99, 'Id_Tipo_IVA' => '01'],
        ['Id_Articulo' => 'ART012', 'Descripcion' => 'Última línea',       'Cantidad' => 1000,   'Precio' => 1.234,     'Descuento' => 5,     'Id_Tipo_IVA' => '02'],
    ],
];

try {
    [$codigoB1, $rB1] = crearFacturaDePrueba($facturaMonstruo, $calc);
    ok("B1: factura creada: {$codigoB1} (16 líneas, total {$rB1['total']} €)");
    verificarCoherencia('B1', $rB1);

    // Releer de BD y comparar
    $guardada = (new Factura())->findWithLines($codigoB1);
    $nLineas = count($guardada['lineas'] ?? []);
    if ($nLineas === 16) {
        ok("B1: las 16 líneas se guardaron en BD");
    } else {
        fail("B1: se esperaban 16 líneas guardadas y hay {$nLineas}");
    }
    if (abs((float)$guardada['Total'] - $rB1['total']) <= 0.011) {
        ok("B1: total en BD coincide ({$guardada['Total']})");
    } else {
        fail("B1: total en BD ({$guardada['Total']}) ≠ calculado ({$rB1['total']})");
    }

    echo "\n── B1: generación XML Verifactu (sin enviar)\n";
    $xml = generarXmlSinEnviar($codigoB1);
    verificarXml('B1', $xml, (float)$rB1['total']);
} catch (Throwable $e) {
    fail('B1: EXCEPCIÓN ' . get_class($e) . ': ' . $e->getMessage());
}

echo "\n";

// ── B2: factura con recargo de equivalencia (cliente 009, RE 5.2%)
$facturaRE = [
    'Id_Canal'            => 'ACCI',
    'Fecha'               => $hoy,
    'Id_Cliente'          => '009',
    'Id_Forma_Pago'       => 'TRF',
    'Tipo_Documento'      => 'FACTURA',
    'Observaciones'       => 'PRUEBA AUTOMÁTICA - factura RE (puede borrarse)',
    'Descuento_Especial'  => 0,
    'Descuento_Comercial' => 0,
    'Descuento_PP'        => 2,
    'lineas' => [
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Con RE 21%',  'Cantidad' => 4, 'Precio' => 150,   'Descuento' => 0,  'Id_Tipo_IVA' => '01', 'Aplica_RE' => 1],
        ['Id_Articulo' => 'ART004', 'Descripcion' => 'Con RE 10%',  'Cantidad' => 8, 'Precio' => 12.50, 'Descuento' => 10, 'Id_Tipo_IVA' => '02', 'Aplica_RE' => 1],
        ['Id_Articulo' => 'AR4343', 'Descripcion' => 'Con RE 4%',   'Cantidad' => 2, 'Precio' => 30,    'Descuento' => 0,  'Id_Tipo_IVA' => '03', 'Aplica_RE' => 1],
        ['Id_Articulo' => 'ART001', 'Descripcion' => 'Sin RE',      'Cantidad' => 1, 'Precio' => 100,   'Descuento' => 0,  'Id_Tipo_IVA' => '01'],
    ],
];

try {
    [$codigoB2, $rB2] = crearFacturaDePrueba($facturaRE, $calc);
    ok("B2: factura RE creada: {$codigoB2} (total {$rB2['total']} €, RE {$rB2['importe_re']} €)");
    verificarCoherencia('B2', $rB2);
    if ($rB2['importe_re'] > 0) {
        ok("B2: el RE se ha aplicado ({$rB2['importe_re']} €)");
    } else {
        fail('B2: el RE no se ha aplicado pese a Aplica_RE=1');
    }

    echo "\n── B2: generación XML Verifactu (sin enviar)\n";
    $xml2 = generarXmlSinEnviar($codigoB2);
    verificarXml('B2', $xml2, (float)$rB2['total']);
    if (str_contains($xml2, '<sum1:ClaveRegimen>18</sum1:ClaveRegimen>')) {
        ok('B2: el XML lleva ClaveRegimen 18 (recargo de equivalencia)');
    } else {
        warn('B2: el XML no contiene ClaveRegimen 18 — revisar tratamiento del RE');
    }
} catch (Throwable $e) {
    fail('B2: EXCEPCIÓN ' . get_class($e) . ': ' . $e->getMessage());
}

echo "\n═══ RESUMEN ═══\n";
echo $fallos === 0
    ? "Todo correcto. {$avisos} aviso(s).\n"
    : "{$fallos} FALLO(S), {$avisos} aviso(s).\n";
exit($fallos === 0 ? 0 : 1);
