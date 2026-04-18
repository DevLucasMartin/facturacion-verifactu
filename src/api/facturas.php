<?php
/**
 * API de Facturas
 * Módulo de Facturación
 */

require_once __DIR__ . '/../controllers/FacturaController.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path       = parse_url($requestUri, PHP_URL_PATH);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

if (str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

$path     = str_replace('.php', '', $path);
$path     = trim($path, '/');
$parts    = ($path === '') ? [] : explode('/', $path);
$resource = $parts[0] ?? '';

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
        'error_detail' => getenv('APP_ENV') === 'development' ? [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ] : null,
    ], JSON_UNESCAPED_UNICODE);
}
