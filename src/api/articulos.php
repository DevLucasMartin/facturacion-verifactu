<?php
/**
 * API de Artículos
 * Módulo de Facturación
 *
 * Endpoints:
 * GET    /api/articulos                    - Listar artículos (gestión, paginado)
 * GET    /api/articulos?action=search&q=X  - Buscar artículos (activos)
 * GET    /api/articulos/:codigo            - Ver artículo
 * POST   /api/articulos                    - Crear artículo
 * PUT    /api/articulos/:codigo            - Actualizar artículo
 * DELETE /api/articulos/:codigo            - Desactivar artículo
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Articulo.php';

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

$resource = preg_replace('/\.php$/i', '', $resource);

if (($resource === 'articulos') && isset($_GET['action']) && !isset($parts[1])) {
    $parts[1] = (string)$_GET['action'];
}

try {
    $articuloModel = new Articulo();

    if ($method === 'GET') {
        if ($resource === 'articulos') {
            if (isset($parts[1])) {
                if ($parts[1] === 'search') {
                    $query  = $_GET['q'] ?? '';
                    $tarifa = (int)($_GET['tarifa'] ?? 1);
                    $limit  = (int)($_GET['limit'] ?? 20);

                    if (strlen($query) < 1) {
                        Response::error('Se requiere parámetro de búsqueda (q)');
                    }

                    $result = $articuloModel->search($query, $tarifa, $limit);
                    Response::success($result);
                } else {
                    $codigo   = rawurldecode($parts[1]);
                    $articulo = $articuloModel->find($codigo);
                    if (!$articulo) {
                        Response::notFound('Artículo no encontrado');
                    }
                    Response::success($articulo);
                }
            } else {
                $page      = (int)($_GET['page']     ?? 1);
                $perPage   = (int)($_GET['per_page'] ?? 25);
                $busqueda  = trim($_GET['q'] ?? '');
                $gestion   = isset($_GET['gestion']);

                if ($gestion) {
                    $result = $articuloModel->paginateGestion($page, $perPage, $busqueda);
                } else {
                    $result = $articuloModel->paginate($page, $perPage);
                }

                Response::paginated(
                    $result['items'],
                    $result['total'],
                    $result['page'],
                    $result['per_page']
                );
            }
        } else {
            http_response_code(404);
            Response::error('Recurso no encontrado');
        }

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['Codigo'])) {
            http_response_code(400);
            Response::error('El código del artículo es obligatorio');
        }
        if (empty($body['Descripcion'])) {
            http_response_code(400);
            Response::error('La descripción es obligatoria');
        }
        if (empty($body['Id_Tipo_IVA'])) {
            http_response_code(400);
            Response::error('El tipo de IVA es obligatorio');
        }

        $codigo = strtoupper(trim($body['Codigo']));
        if ($articuloModel->find($codigo)) {
            http_response_code(409);
            Response::error("Ya existe un artículo con el código {$codigo}");
        }

        $articuloModel->create($body);
        http_response_code(201);
        Response::success(['Codigo' => $codigo], "Artículo {$codigo} creado correctamente");

    } elseif ($method === 'PUT') {
        if (!isset($parts[1])) {
            http_response_code(400);
            Response::error('Se requiere el código del artículo');
        }

        $codigo   = rawurldecode($parts[1]);
        $articulo = $articuloModel->find($codigo);
        if (!$articulo) {
            Response::notFound('Artículo no encontrado');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body['Descripcion'])) {
            http_response_code(400);
            Response::error('La descripción es obligatoria');
        }
        if (empty($body['Id_Tipo_IVA'])) {
            http_response_code(400);
            Response::error('El tipo de IVA es obligatorio');
        }

        $articuloModel->update($codigo, $body);
        Response::success(['Codigo' => $codigo], 'Artículo actualizado correctamente');

    } elseif ($method === 'DELETE') {
        if (!isset($parts[1])) {
            http_response_code(400);
            Response::error('Se requiere el código del artículo');
        }

        $codigo   = rawurldecode($parts[1]);
        $articulo = $articuloModel->find($codigo);
        if (!$articulo) {
            Response::notFound('Artículo no encontrado');
        }

        $articuloModel->delete($codigo);
        Response::success(null, 'Artículo desactivado correctamente');

    } elseif ($method === 'PATCH') {
        if (!isset($parts[1]) || ($parts[2] ?? '') !== 'activar') {
            Response::notFound('Endpoint no encontrado');
        }

        $codigo   = rawurldecode($parts[1]);
        $articulo = $articuloModel->find($codigo);
        if (!$articulo) {
            Response::notFound('Artículo no encontrado');
        }

        $articuloModel->reactivate($codigo);
        Response::success(null, 'Artículo activado correctamente');

    } else {
        http_response_code(405);
        Response::error('Método no permitido');
    }
} catch (Exception $e) {
    Response::serverError('Error al procesar solicitud', $e);
}
