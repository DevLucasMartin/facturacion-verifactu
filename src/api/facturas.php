<?php
/**
 * API de Facturas
 * Módulo de Facturación
 */

// Capturar cualquier error PHP y devolverlo como JSON
ini_set('display_errors', 0);
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => false,
        'success' => false,
        'message' => $e->getMessage(),
        'error_detail' => [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'success' => false,
            'message' => 'Fatal error: ' . $err['message'],
            'error_detail' => $err,
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/../controllers/FacturaController.php';

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path       = parse_url($requestUri, PHP_URL_PATH);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

if (str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

$path     = str_replace('.php', '', $path);
$path     = trim($path, '/');
$parts    = ($path === '') ? [] : array_map('rawurldecode', explode('/', $path));
$resource = $parts[0] ?? '';

// No enviar Content-Type JSON si es una descarga de archivo
$esExportar = ($method === 'GET' && ($parts[1] ?? '') === 'exportar');
if (!$esExportar) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

try {
    $controller = new FacturaController();

    // Acciones especiales por query-string antes del enrutado REST
    $qs_action = $_GET['action'] ?? '';
    if ($method === 'GET' && $qs_action === 'codigos') {
        // Devuelve los canales que tienen facturas (para el filtro)
        require_once __DIR__ . '/../core/Database.php';
        $rows = Database::getInstance()->fetchAll(
            "SELECT DISTINCT `Id_Canal` FROM `Facturas_Clientes` ORDER BY `Id_Canal`"
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'   => true,
            'data' => array_column($rows, 'Id_Canal'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Acciones de la vista de detalle (ver.js): get, lineas, totales, cliente, pdf, xml
    if ($method === 'GET' && in_array($qs_action, ['get', 'lineas', 'totales', 'cliente', 'pdf', 'ver_pdf', 'xml'])) {
        $qs_codigo = trim($_GET['codigo'] ?? '');
        if ($qs_codigo === '') {
            echo json_encode(['success' => false, 'message' => 'Código requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($qs_action === 'pdf') {
            $controller->descargarPdf($qs_codigo);
            exit;
        }

        if ($qs_action === 'ver_pdf') {
            $controller->verPdf($qs_codigo);
            exit;
        }

        if ($qs_action === 'xml') {
            require_once __DIR__ . '/../controllers/VerifactuController.php';
            $vc = new VerifactuController();
            $vc->descargarXml('FACTURA/' . $qs_codigo, trim($_GET['tipo'] ?? 'sin-firma'));
            exit;
        }

        $db = Database::getInstance();

        switch ($qs_action) {
            case 'get':
                $f = $db->fetch("SELECT * FROM `Facturas_Clientes` WHERE `Codigo` = ?", [$qs_codigo]);
                if (!$f) {
                    echo json_encode(['success' => false, 'message' => 'Factura no encontrada'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $estado = ($f['Cerrada'] ?? 'N') === 'S' ? 'EMITIDA' : 'BORRADOR';
                $rectificativa = $db->fetch(
                    "SELECT `Codigo` FROM `Facturas_Clientes` WHERE `Factura_Rectificada_Id` = ? AND `Tipo_Documento` = 'RECTIFICATIVA' LIMIT 1",
                    [$qs_codigo]
                );
                echo json_encode(['success' => true, 'data' => [
                    'codigo'           => $f['Codigo']          ?? '',
                    'fecha'            => $f['Fecha']           ?? null,
                    'tipo_documento'   => $f['Tipo_Documento']  ?? '',
                    'estado'           => $estado,
                    'cobrada'          => ($f['Cobrada']        ?? 'N') === 'S',
                    'recapitulada'     => ($f['Recapitulada']   ?? 'N') === 'S',
                    'canal'            => $f['Id_Canal']        ?? '',
                    'id_cliente'       => $f['Id_Cliente']      ?? '',
                    'id_forma_pago'    => $f['Id_Forma_Pago']   ?? '',
                    'observaciones'    => $f['Observaciones']   ?? '',
                    'total'            => (float)($f['Total']            ?? 0),
                    'importe_cobrado'  => (float)($f['Importe_Cobrado']  ?? 0),
                    'dto_especial'          => (float)($f['Descuento_Especial']  ?? 0),
                    'dto_comercial'         => (float)($f['Descuento_Comercial'] ?? 0),
                    'dto_pp'                => (float)($f['Descuento_PP']        ?? 0),
                    'factura_rectificada_id'  => $f['Factura_Rectificada_Id'] ?? null,
                    'rectificada_por'         => $rectificativa['Codigo'] ?? null,
                ]], JSON_UNESCAPED_UNICODE);
                exit;

            case 'lineas':
                $lineas = $db->fetchAll(
                    "SELECT L.`Descripcion`, L.`Id_Articulo`, L.`Cantidad`, L.`Precio`,
                            L.`Descuento`, L.`Total`, L.`Id_Tipo_IVA`,
                            T.`IVA` AS tipo_iva_pct, T.`RE` AS tipo_re_pct
                     FROM `Lineas_Facturas_Clientes` L
                     LEFT JOIN `Tipos_IVA` T ON L.`Id_Tipo_IVA` = T.`Codigo`
                     WHERE L.`Id_Factura` = ?
                     ORDER BY L.`Linea`",
                    [$qs_codigo]
                );
                $result = array_map(fn($l) => [
                    'descripcion'     => $l['Descripcion']  ?? '',
                    'referencia'      => $l['Id_Articulo']  ?? '',
                    'cantidad'        => (float)($l['Cantidad']     ?? 0),
                    'precio_unitario' => (float)($l['Precio']       ?? 0),
                    'descuento'       => (float)($l['Descuento']    ?? 0),
                    'porcentaje_iva'  => (float)($l['tipo_iva_pct'] ?? 0),
                    'porcentaje_re'   => (float)($l['tipo_re_pct']  ?? 0),
                    'tipo_iva'        => $l['Id_Tipo_IVA']  ?? '',
                    'total'           => (float)($l['Total']        ?? 0),
                ], $lineas);
                echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
                exit;

            case 'totales':
                $f = $db->fetch("SELECT * FROM `Facturas_Clientes` WHERE `Codigo` = ?", [$qs_codigo]);
                if (!$f) {
                    echo json_encode(['success' => false, 'message' => 'Factura no encontrada'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $cuotas = $db->fetchAll(
                    "SELECT L.`Id_Tipo_IVA`, T.`IVA` AS iva_pct, T.`RE` AS re_pct,
                            SUM(L.`Base_Imponible` * T.`IVA` / 100) AS cuota_iva,
                            SUM(L.`RE`) AS cuota_re
                     FROM `Lineas_Facturas_Clientes` L
                     LEFT JOIN `Tipos_IVA` T ON L.`Id_Tipo_IVA` = T.`Codigo`
                     WHERE L.`Id_Factura` = ?
                     GROUP BY L.`Id_Tipo_IVA`, T.`IVA`, T.`RE`
                     ORDER BY T.`IVA`",
                    [$qs_codigo]
                );
                $cuotas_norm = array_map(fn($c) => [
                    'porcentaje_iva' => (float)($c['iva_pct']   ?? 0),
                    'cuota_iva'      => (float)($c['cuota_iva'] ?? 0),
                    'porcentaje_re'  => (float)($c['re_pct']    ?? 0),
                    'cuota_re'       => (float)($c['cuota_re']  ?? 0),
                ], $cuotas);
                echo json_encode(['success' => true, 'data' => [
                    'subtotal'       => (float)($f['Importe_Bruto']          ?? 0),
                    'dto_especial'   => (float)($f['Importe_Dto_Especial']   ?? 0),
                    'dto_comercial'  => (float)($f['Importe_Dto_Comercial']  ?? 0),
                    'dto_pp'         => (float)($f['Importe_Dto_PP']         ?? 0),
                    'base_imponible' => (float)($f['Base_Imponible']         ?? 0),
                    'cuotas_iva'     => $cuotas_norm,
                    'total'          => (float)($f['Total']                  ?? 0),
                ]], JSON_UNESCAPED_UNICODE);
                exit;

            case 'cliente':
                $row = $db->fetch("SELECT `Id_Cliente` FROM `Facturas_Clientes` WHERE `Codigo` = ?", [$qs_codigo]);
                if (!$row || empty($row['Id_Cliente'])) {
                    echo json_encode(['success' => false, 'message' => 'Cliente no encontrado'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $c = $db->fetch("SELECT * FROM `Clientes` WHERE `Codigo` = ?", [$row['Id_Cliente']]);
                if (!$c) {
                    echo json_encode(['success' => false, 'message' => 'Cliente no encontrado'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $nombreFiscal = $c['Archivar_Como'] ?? '';
                if (empty(trim($nombreFiscal))) {
                    $nombreFiscal = trim(($c['Nombre'] ?? '') . ' ' . ($c['Apellidos'] ?? ''));
                }
                echo json_encode(['success' => true, 'data' => [
                    'nombre_fiscal' => $nombreFiscal,
                    'nombre'        => $c['Nombre']            ?? '',
                    'nif'           => $c['NIF']               ?? '',
                    'direccion'     => $c['Direccion']         ?? '',
                    'poblacion'     => $c['Poblacion']         ?? '',
                    'cp'            => $c['CP']                ?? '',
                    'email'         => $c['Email_Facturacion'] ?? '',
                ]], JSON_UNESCAPED_UNICODE);
                exit;
        }
    }

    // Registrar pago parcial o total
    if ($method === 'POST' && $qs_action === 'pagar') {
        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $qs_codigo  = trim($body['codigo'] ?? '');
        $importe    = (float)($body['importe'] ?? 0);
        $fecha      = trim($body['fecha'] ?? date('Y-m-d'));

        if ($qs_codigo === '') {
            echo json_encode(['success' => false, 'message' => 'Código requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($importe <= 0) {
            echo json_encode(['success' => false, 'message' => 'El importe debe ser mayor que cero'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::getInstance();
        $f  = $db->fetch("SELECT `Total`, `Importe_Cobrado`, `Cobrada` FROM `Facturas_Clientes` WHERE `Codigo` = ?", [$qs_codigo]);
        if (!$f) {
            echo json_encode(['success' => false, 'message' => 'Factura no encontrada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $total           = (float)$f['Total'];
        $cobradoActual   = (float)($f['Importe_Cobrado'] ?? 0);
        $pendiente       = round($total - $cobradoActual, 4);

        if ($importe > $pendiente + 0.001) {
            echo json_encode(['success' => false, 'message' => 'El importe supera el pendiente de cobro'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nuevoCobrado = round($cobradoActual + $importe, 4);
        $totalCobrada = $nuevoCobrado >= ($total - 0.001);

        $campos = ['Importe_Cobrado' => $nuevoCobrado];
        if ($totalCobrada) {
            $campos['Cobrada']     = 'S';
            $campos['Fecha_Cobro'] = $fecha;
        }
        $db->update('Facturas_Clientes', $campos, '`Codigo` = ?', [$qs_codigo]);

        echo json_encode(['success' => true, 'data' => [
            'importe_cobrado' => $nuevoCobrado,
            'pendiente'       => max(0, round($total - $nuevoCobrado, 4)),
            'cobrada'         => $totalCobrada,
        ]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($method) {
        case 'GET':
            switch ($resource) {
                case 'facturas':
                    if (isset($parts[1]) && $parts[1] !== '') {
                        if ($parts[1] === 'exportar') {
                            $controller->exportar();
                        } elseif ($parts[1] === 'search') {
                            $controller->search();
                        } elseif ($parts[1] === 'next') {
                            $controller->siguienteNumero();
                        } elseif ($parts[1] === 'origen') {
                            $controller->origen();
                        } elseif ($parts[1] === 'simplificadas-pendientes') {
                            $controller->simplificadasPendientes();
                        } elseif ($parts[1] === 'stats' || $parts[1] === 'estadisticas') {
                            $controller->estadisticas();
                        } elseif (isset($parts[2]) && $parts[2] === 'pdf') {
                            $controller->generarPdf($parts[1]);
                        } elseif (isset($parts[2]) && $parts[2] === 'vista') {
                            $controller->verPdf($parts[1]);
                        } elseif (isset($parts[2]) && $parts[2] === 'descargar') {
                            $controller->descargarPdf($parts[1]);
                        } else {
                            $controller->show($parts[1]);
                        }
                    } else {
                        $controller->index();
                    }
                    break;

                default:
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Recurso no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'POST':
            switch ($resource) {
                case 'facturas':
                    if (isset($parts[1]) && $parts[1] !== '') {
                        if ($parts[1] === 'from-albaranes') {
                            $controller->convertirDesdeAlbaranes();
                        } elseif ($parts[1] === 'crear-recapitulativa') {
                            $controller->crearRecapitulativa();
                        } elseif (isset($parts[2]) && $parts[2] === 'firmar') {
                            $controller->firmar($parts[1]);
                        } elseif (isset($parts[2]) && $parts[2] === 'enviar') {
                            $controller->enviar($parts[1]);
                        } elseif (isset($parts[2]) && $parts[2] === 'email') {
                            $controller->enviarEmail($parts[1]);
                        } else {
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado'], JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $controller->store();
                    }
                    break;

                default:
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Recurso no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'PUT':
            if ($resource === 'facturas' && isset($parts[1]) && $parts[1] !== '') {
                $controller->update($parts[1]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'      => false,
        'message'      => 'Error al procesar solicitud',
        'error_detail' => [
            'message' => $e->getMessage(),
            'file'    => str_replace(__DIR__ . '/../../', '', $e->getFile()),
            'line'    => $e->getLine(),
        ],
    ], JSON_UNESCAPED_UNICODE);
}
