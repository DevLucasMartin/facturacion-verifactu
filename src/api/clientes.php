<?php
/**
 * API de Clientes
 * Módulo de Facturación
 *
 * Endpoints:
 * GET   /api/clientes          - Listar clientes
 * GET   /api/clientes/:codigo  - Ver cliente
 * GET   /api/clientes/search   - Buscar clientes
 * POST  /api/clientes          - Crear cliente
 * PATCH /api/clientes/:id/nif  - Corregir NIF
 */

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

            $numPag  = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 25);
            $result  = $clienteModel->paginate($numPag, $perPage);
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
        $activo       = in_array($body['Activo'] ?? 'S', ['S', 'N']) ? $body['Activo'] : 'S';

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

        $data = ['Codigo' => $codigo];
        if ($nombre       !== '') $data['Nombre']              = $nombre;
        if ($apellidos    !== '') $data['Apellidos']           = $apellidos;
        if ($organizacion !== '') $data['Organizacion']        = $organizacion;
        if ($archivar     !== '') $data['Archivar_Como']       = $archivar;
        if ($nif          !== '') $data['NIF']                 = $nif;
        if ($direccion    !== '') $data['Direccion']           = $direccion;
        if ($poblacion    !== '') $data['Poblacion']           = $poblacion;
        if ($provincia    !== '') $data['Provincia']           = $provincia;
        if ($idPais       !== '') $data['Id_Pais']             = $idPais;
        if ($tipoCliente  !== '') $data['Id_Tipo_Cliente']     = $tipoCliente;
        if ($formaPago    !== '') $data['Id_Forma_Pago']       = $formaPago;
        $data['Tarifa']                = $tarifa;
        $data['RE_Porcentaje']         = $aplicaRE ? 5.20 : 0.00;
        $data['Descuento_Especial']    = $dtoEspecial;
        $data['Descuento_Comercial']   = $dtoComercial;
        $data['Descuento_Pronto_Pago'] = $dtoPP;
        $data['Activo']                = $activo;

        $nuevoCodigo = $clienteModel->create($data);
        $cliente     = $clienteModel->findWithDetails($nuevoCodigo);
        http_response_code(201);
        Response::success($cliente);
    }

    if ($fact_method === 'PATCH' && $fact_recurso === 'clientes') {
        $idCliente = $fact_parts[1] ?? '';
        $accion    = $fact_parts[2] ?? '';

        if ($idCliente === '' || $accion !== 'nif') {
            Response::notFound('Endpoint no encontrado');
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
