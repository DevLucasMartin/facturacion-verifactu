<?php
/**
 * API de Verifactu
 * Módulo de Facturación
 *
 * Endpoints:
 * GET  /api/verifactu              - Listar registros
 * GET  /api/verifactu/stats        - Estadísticas
 * GET  /api/verifactu/estado/:codigo  - Estado de documento
 * GET  /api/verifactu/xml/:codigo     - Generar/descargar XML sin firma
 * GET  /api/verifactu/xml/firmado/:codigo - Generar/descargar XML firmado
 * GET  /api/verifactu/xml/aeat/:codigo    - Descargar XML de AEAT (si existe)
 * POST /api/verifactu/firmar      - Firmar documento
 * POST /api/verifactu/enviar      - Enviar a Hacienda
 * POST /api/verifactu/enviar-cola - Vaciar cola pendiente (envío diferido en orden)
 * POST /api/verifactu/reenviar/:tipo/:codigo - Reenviar tras rechazo (subsanación, sin re-firmar)
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();
require_once __DIR__ . '/../core/Csrf.php';
Csrf::requireApi();

require_once __DIR__ . '/../controllers/VerifactuController.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path       = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

$path            = str_replace('.php', '', $path);
$path            = trim($path, '/');
$segmentosRuta   = explode('/', $path);
$recurso         = $segmentosRuta[0] ?? '';

try {
    $controller = new VerifactuController();

    // Acción de la vista de detalle (ver.js): estado via query-string
    $qs_action = $_GET['action'] ?? '';
    if ($method === 'GET' && $qs_action === 'estado') {
        $qs_codigo = trim($_GET['codigo'] ?? '');
        if ($qs_codigo === '') {
            echo json_encode(['success' => false, 'message' => 'Código requerido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once __DIR__ . '/../core/Database.php';
        $registro = Database::getInstance()->fetch(
            "SELECT * FROM `Verifactu_Registros`
             WHERE `Tipo_Origen` = 'FACTURA' AND `Id_Documento` = ?
             ORDER BY `Fecha_Generacion` DESC LIMIT 1",
            [$qs_codigo]
        );
        if (!$registro) {
            echo json_encode(['success' => false, 'message' => 'Sin registro Verifactu'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success' => true, 'data' => [
            'estado'            => $registro['Estado_Envio']     ?? '-',
            'fecha_envio'       => $registro['Fecha_Generacion'] ?? null,
            'hash'              => $registro['Huella']           ?? null,
            'csv'               => $registro['CSV_Hacienda']     ?? null,
            'url_verificacion'  => $registro['URL_Verificacion'] ?? null,
            'descripcion_error' => $registro['Ultimo_Error']     ?? null,
            'reintentos'        => (int)($registro['Reintentos'] ?? 0),
        ]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($method) {
        case 'GET':
            switch ($recurso) {
                case 'verifactu':
                    if (isset($segmentosRuta[1])) {
                        if ($segmentosRuta[1] === 'stats' || $segmentosRuta[1] === 'estadisticas') {
                            $controller->estadisticas();
                        } elseif ($segmentosRuta[1] === 'certificado') {
                            $controller->certificado();
                        } elseif ($segmentosRuta[1] === 'xml') {
                            $tipoXml   = 'sin-firma';
                            $codigoIdx = 2;

                            if (isset($segmentosRuta[2])) {
                                if ($segmentosRuta[2] === 'firmado' || $segmentosRuta[2] === 'aeat') {
                                    $tipoXml   = $segmentosRuta[2];
                                    $codigoIdx = 3;
                                }
                            }
                            $codigoCompleto = ($segmentosRuta[$codigoIdx] ?? '') . '/' . ($segmentosRuta[$codigoIdx + 1] ?? '');
                            $controller->descargarXml($codigoCompleto, $tipoXml);
                        } elseif ($segmentosRuta[1] === 'estado' && isset($segmentosRuta[2])) {
                            $codigoCompleto = $segmentosRuta[2] . '/' . ($segmentosRuta[3] ?? '');
                            $controller->estado($codigoCompleto);
                        } else {
                            $controller->estado($segmentosRuta[1]);
                        }
                    } else {
                        $controller->index();
                    }
                    break;

                case 'estado':
                    $codigoCompleto = isset($segmentosRuta[2]) && $segmentosRuta[2] !== ''
                        ? ($segmentosRuta[1] ?? '') . '/' . $segmentosRuta[2]
                        : ($segmentosRuta[1] ?? '');
                    $controller->estado($codigoCompleto);
                    break;

                case 'xml':
                    $tipoXml   = 'sin-firma';
                    $codigoIdx = 2;

                    if (isset($segmentosRuta[1])) {
                        if ($segmentosRuta[1] === 'envio') {
                            $codigoCompleto = ($segmentosRuta[2] ?? '') . '/' . ($segmentosRuta[3] ?? '');
                            $controller->descargarXmlEnvio($codigoCompleto);
                            break;
                        }
                        if ($segmentosRuta[1] === 'firmado' || $segmentosRuta[1] === 'aeat') {
                            $tipoXml   = $segmentosRuta[1];
                            $codigoIdx = 2;
                        }
                    }

                    $codigoCompleto = ($segmentosRuta[$codigoIdx] ?? '') . '/' . ($segmentosRuta[$codigoIdx + 1] ?? '');
                    $controller->descargarXml($codigoCompleto, $tipoXml);
                    break;

                case 'reintentar':
                    $codigoCompleto = ($segmentosRuta[1] ?? '') . '/' . ($segmentosRuta[2] ?? '');
                    $controller->reintentar($codigoCompleto);
                    break;

                default:
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Recurso no encontrado'], JSON_UNESCAPED_UNICODE);
                    break;
            }
            break;

        case 'POST':
            switch ($recurso) {
                case 'verifactu':
                    if (isset($segmentosRuta[1])) {
                        if ($segmentosRuta[1] === 'firmar') {
                            $controller->firmar();
                        } elseif ($segmentosRuta[1] === 'enviar-cola' || $segmentosRuta[1] === 'cola') {
                            $controller->enviarCola();
                        } elseif ($segmentosRuta[1] === 'enviar') {
                            $controller->firmarYEnviar();
                        } elseif ($segmentosRuta[1] === 'reenviar' && isset($segmentosRuta[2])) {
                            // Subsanación de un rechazo: reenvía el registro existente
                            // SIN re-firmar (mantiene la huella y la cadena).
                            $codigoCompleto = $segmentosRuta[2] . '/' . ($segmentosRuta[3] ?? '');
                            $controller->enviar($codigoCompleto);
                        } elseif ($segmentosRuta[1] === 'anular' && isset($segmentosRuta[2])) {
                            $codigoCompleto = $segmentosRuta[2] . '/' . ($segmentosRuta[3] ?? '');
                            $controller->anular($codigoCompleto);
                        } elseif ($segmentosRuta[1] === 'reintentar' && isset($segmentosRuta[2])) {
                            $codigoCompleto = $segmentosRuta[2] . '/' . ($segmentosRuta[3] ?? '');
                            $controller->reintentar($codigoCompleto);
                        }
                    } else {
                        $controller->firmarYEnviar();
                    }
                    break;

                case 'anular':
                    $codigoCompleto = ($segmentosRuta[1] ?? '') . '/' . ($segmentosRuta[2] ?? '');
                    $controller->anular($codigoCompleto);
                    break;

                case 'reintentar':
                    $codigoCompleto = ($segmentosRuta[1] ?? '') . '/' . ($segmentosRuta[2] ?? '');
                    $controller->reintentar($codigoCompleto);
                    break;

                default:
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Recurso no encontrado'], JSON_UNESCAPED_UNICODE);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error'   => getenv('APP_ENV') !== 'production' ? $e->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
}
