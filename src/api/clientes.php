<?php
/**
 * API de Clientes
 * Módulo de Facturación
 *
 * Endpoints:
 * GET    /api/clientes          - Listar clientes
 * GET    /api/clientes/:codigo  - Ver cliente
 * GET    /api/clientes/search   - Buscar clientes
 * POST   /api/clientes          - Crear cliente
 * PUT    /api/clientes/:codigo  - Modificar cliente
 * DELETE /api/clientes/:codigo  - Eliminar cliente (si no tiene facturas)
 * PATCH  /api/clientes/:id/nif  - Corregir NIF
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();
require_once __DIR__ . '/../core/Csrf.php';
Csrf::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Cliente.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fact_method      = $_SERVER['REQUEST_METHOD'];
$fact_requestUri  = $_SERVER['REQUEST_URI'];
$fact_path        = parse_url($fact_requestUri, PHP_URL_PATH);

$fact_scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (str_starts_with($fact_path, $fact_scriptDir)) {
    $fact_path = substr($fact_path, strlen($fact_scriptDir));
}

$fact_path   = str_replace('.php', '', $fact_path);
$fact_path   = trim($fact_path, '/');
$fact_parts  = ($fact_path === '') ? [] : explode('/', $fact_path);
$fact_recurso = $fact_parts[0] ?? '';

try {
    $clienteModel = new Cliente();

    if ($fact_method === 'GET') {
        if ($fact_recurso === 'clientes') {

            $fact_action = $_GET['action'] ?? '';

            // action=list → devuelve id_cliente + nombre_fiscal para selectores
            if ($fact_action === 'list') {
                $fact_limit = min((int)($_GET['limit'] ?? 500), 2000);
                $fact_items = Database::getInstance()->fetchAll(
                    "SELECT `Codigo` AS id_cliente,
                            IFNULL(NULLIF(TRIM(`Archivar_Como`), ''),
                                CONCAT(TRIM(`Nombre`), IF(`Apellidos` IS NOT NULL AND TRIM(`Apellidos`) != '',
                                CONCAT(' ', TRIM(`Apellidos`)), ''))) AS nombre_fiscal
                     FROM `Clientes`
                     WHERE `Activo` = 'S'
                     ORDER BY `Archivar_Como`, `Nombre`
                     LIMIT {$fact_limit}"
                );
                Response::success($fact_items);
            }

            if ($fact_action === 'search') {
                $fact_q     = trim((string)($_GET['q'] ?? ''));
                $fact_limit = (int)($_GET['limit'] ?? 20);
                if ($fact_q === '') {
                    Response::error('Se requiere parámetro de búsqueda (q)');
                }
                Response::success($clienteModel->search($fact_q, $fact_limit));
            }

            if (($fact_parts[1] ?? '') === 'search') {
                $fact_q     = trim((string)($_GET['q'] ?? ''));
                $fact_limit = (int)($_GET['limit'] ?? 20);
                if ($fact_q === '') {
                    Response::error('Se requiere parámetro de búsqueda (q)');
                }
                Response::success($clienteModel->search($fact_q, $fact_limit));
            }

            if (isset($fact_parts[1]) && $fact_parts[1] !== '') {
                $perfil = $clienteModel->findWithDetails($fact_parts[1]);
                if (!$perfil) {
                    Response::notFound('Cliente no encontrado');
                }
                Response::success($perfil);
            }

            $numPag   = (int)($_GET['page']     ?? 1);
            $perPage  = (int)($_GET['per_page'] ?? 25);
            $busqueda = trim($_GET['q'] ?? '');
            $gestion  = isset($_GET['gestion']);

            if ($gestion) {
                $result = $clienteModel->paginateGestion($numPag, $perPage, $busqueda);
            } else {
                $result = $clienteModel->paginate($numPag, $perPage);
            }

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
        } else {
            http_response_code(404);
            Response::error('Recurso no encontrado');
        }
    }

    if ($fact_method === 'POST' && $fact_recurso === 'clientes') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $codigo       = strtoupper(trim($body['Codigo']         ?? ''));
        $nombre       = trim($body['Nombre']                    ?? '');
        $apellidos    = trim($body['Apellidos']                 ?? '');
        $organizacion = trim($body['Organizacion']              ?? '');
        $archivar     = trim($body['Archivar_Como'] ?? $body['Archivar'] ?? '');
        $nif          = trim($body['NIF']                       ?? '');
        $direccion    = trim($body['Direccion']                 ?? '');
        $poblacion    = trim($body['Poblacion']                 ?? '');
        $provincia    = trim($body['Provincia']                 ?? '');
        $idPais       = trim($body['Id_Pais']                   ?? '');
        $tipoCliente  = trim($body['Id_Tipo_Cliente']           ?? '');
        $formaPago    = strtoupper(trim($body['Id_Forma_Pago']  ?? ''));
        $tarifa       = (int)($body['Tarifa']                   ?? 1);
        $aplicaRE     = ($body['Aplica_RE'] ?? 0) == 1;
        $dtoEspecial  = (float)($body['Descuento_Especial']    ?? 0);
        $dtoComercial = (float)($body['Descuento_Comercial']   ?? 0);
        $dtoPP        = (float)($body['Descuento_Pronto_Pago'] ?? 0);
        $activo       = in_array($body['Activo'] ?? 'S', ['S', 'N']) ? ($body['Activo'] ?? 'S') : 'S';

        if ($codigo === '') {
            http_response_code(422);
            Response::error('El código de cliente es obligatorio');
        }
        if (!preg_match('/^[A-Z0-9]{1,12}$/', $codigo)) {
            http_response_code(422);
            Response::error('El código solo puede contener letras y números (máx. 12 caracteres)');
        }
        if (empty($nombre) && empty($organizacion) && empty($archivar)) {
            http_response_code(422);
            Response::error('Se requiere al menos Nombre u Organización');
        }
        if ($clienteModel->find($codigo)) {
            http_response_code(409);
            Response::error('Código de cliente ya existente');
        }

        // Archivar_Como es NOT NULL: si no se envía, se auto-calcula del nombre
        if ($archivar === '') {
            $archivar = $organizacion !== '' ? $organizacion
                      : ($nombre . ($apellidos !== '' ? ', ' . $apellidos : ''));
        }

        $rePorc = (float)($body['RE_Porcentaje'] ?? ($aplicaRE ? 5.20 : 0.00));

        $data = [
            'Codigo'       => $codigo,
            'NIF'          => $nif,
            'Archivar_Como'=> $archivar,
            'Tarifa'       => $tarifa,
            'RE_Porcentaje'=> $rePorc,
            'Aplica_RE'    => $rePorc > 0 ? 'S' : 'N',
            'Activo'       => $activo,
        ];
        if ($apellidos !== '') $data['Apellidos']    = $apellidos;
        if ($formaPago !== '') $data['Id_Forma_Pago']= $formaPago;
        $data['Descuento_Especial']  = $dtoEspecial;
        $data['Descuento_Comercial'] = $dtoComercial;
        $data['Descuento_PP']        = $dtoPP;

        $nuevoCodigo = $clienteModel->create($data);
        $cliente     = $clienteModel->findWithDetails($nuevoCodigo);
        http_response_code(201);
        Response::success($cliente);
    }

    if ($fact_method === 'PUT' && $fact_recurso === 'clientes') {
        $idCliente = $fact_parts[1] ?? '';
        if ($idCliente === '') {
            http_response_code(422);
            Response::error('Se requiere el código del cliente');
        }
        if (!$clienteModel->find($idCliente)) {
            Response::notFound('Cliente no encontrado');
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $data = [];
        if (array_key_exists('Apellidos',         $body)) $data['Apellidos']         = trim($body['Apellidos']         ?? '');
        if (array_key_exists('Archivar_Como',      $body)) $data['Archivar_Como']     = trim($body['Archivar_Como']     ?? '');
        if (array_key_exists('NIF',                $body)) $data['NIF']               = strtoupper(trim($body['NIF']   ?? ''));
        if (array_key_exists('Id_Forma_Pago',      $body)) $data['Id_Forma_Pago']     = strtoupper(trim($body['Id_Forma_Pago'] ?? '')) ?: null;
        if (array_key_exists('Tarifa',             $body)) $data['Tarifa']            = (int)($body['Tarifa'] ?? 1);
        if (array_key_exists('RE_Porcentaje',      $body)) $data['RE_Porcentaje']     = (float)($body['RE_Porcentaje'] ?? 0);
        if (array_key_exists('RE_Porcentaje',      $body)) $data['Aplica_RE']         = ($data['RE_Porcentaje'] > 0) ? 'S' : 'N';
        if (array_key_exists('Email_Facturacion',  $body)) $data['Email_Facturacion'] = trim($body['Email_Facturacion'] ?? '') ?: null;

        if (empty($data)) {
            http_response_code(422);
            Response::error('No se han enviado campos a actualizar');
        }
        if (isset($data['Archivar_Como']) && $data['Archivar_Como'] === '') {
            http_response_code(422);
            Response::error('El campo "Archivar como" no puede estar vacío');
        }

        $clienteModel->update($idCliente, $data);
        Response::success($clienteModel->findWithDetails($idCliente), 'Cliente actualizado correctamente');
    }

    if ($fact_method === 'DELETE' && $fact_recurso === 'clientes') {
        $idCliente = $fact_parts[1] ?? '';
        if ($idCliente === '') {
            http_response_code(422);
            Response::error('Se requiere el código del cliente');
        }
        if (!$clienteModel->find($idCliente)) {
            Response::notFound('Cliente no encontrado');
        }
        $clienteModel->delete($idCliente);
        Response::success(null, 'Cliente desactivado correctamente');
    }

    if ($fact_method === 'PATCH' && $fact_recurso === 'clientes') {
        $idCliente = $fact_parts[1] ?? '';
        $accion    = $fact_parts[2] ?? '';

        if ($idCliente === '' || !in_array($accion, ['nif', 'activar'])) {
            Response::notFound('Endpoint no encontrado');
        }

        if ($accion === 'activar') {
            if (!$clienteModel->find($idCliente)) {
                Response::notFound('Cliente no encontrado');
            }
            $clienteModel->reactivate($idCliente);
            Response::success(null, 'Cliente activado correctamente');
        }

        require_once __DIR__ . '/../core/Validator.php';

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $newNif = strtoupper(trim($body['NIF'] ?? ''));

        if ($newNif === '') {
            http_response_code(422);
            Response::error('El NIF no puede estar vacío');
        }

        $errorNif = Validator::validarFormatoNif($newNif);
        if ($errorNif !== null) {
            http_response_code(422);
            Response::error($errorNif);
        }

        if (!$clienteModel->find($idCliente)) {
            Response::notFound('Cliente no encontrado');
        }

        $clienteModel->updateNif($idCliente, $newNif);
        Response::success(['NIF' => $newNif], 'NIF actualizado correctamente');
    }

} catch (Exception $error) {
    Response::serverError('Error al procesar solicitud', $error);
}
