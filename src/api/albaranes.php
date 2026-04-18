<?php
/**
 * API de Albaranes
 * Módulo de Facturación
 */

require_once __DIR__ . '/../controllers/AlbaranController.php';

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
    $controller = new AlbaranController();

    switch ($method) {
        case 'GET':
            switch ($resource) {
                case 'albaranes':
                    if (isset($parts[1]) && $parts[1] !== '') {
                        if ($parts[1] === 'plantillas') {
                            $controller->plantillas();
                        } elseif ($parts[1] === 'exportar') {
                            $controller->exportar();
                        } elseif ($parts[1] === 'search') {
                            $controller->search();
                        } elseif ($parts[1] === 'stats' || $parts[1] === 'estadisticas') {
                            $controller->estadisticas();
                        } elseif ($parts[1] === 'siguiente' || $parts[1] === 'next') {
                            $controller->siguienteNumero();
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
            if ($resource === 'albaranes' && (!isset($parts[1]) || $parts[1] === '')) {
                $controller->store();
            } elseif (isset($parts[2]) && $parts[2] === 'email') {
                $controller->enviarEmail($parts[1]);
            } elseif (isset($parts[2]) && $parts[2] === 'plantilla') {
                $controller->guardarComoPlantilla($parts[1]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'PUT':
            if ($resource === 'albaranes' && isset($parts[1]) && $parts[1] !== '') {
                if (isset($parts[2]) && $parts[2] === 'plantilla') {
                    $controller->renombrarPlantilla($parts[1]);
                } else {
                    $controller->update($parts[1]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'DELETE':
            if ($resource === 'albaranes' && isset($parts[1]) && $parts[1] !== '') {
                if (isset($parts[2]) && $parts[2] === 'plantilla') {
                    $controller->eliminarPlantilla($parts[1]);
                } else {
                    $controller->destroy($parts[1]);
                }
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
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]
    ], JSON_UNESCAPED_UNICODE);
}
