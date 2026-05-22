<?php
/**
 * API de Tipos de IVA
 * Módulo de Facturación
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();
require_once __DIR__ . '/../core/Csrf.php';
Csrf::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/TipoIVA.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $tipoIVAModel = new TipoIVA();

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'porTerritorio' && isset($_GET['territorio'])) {
            $result = $tipoIVAModel->porTerritorio($_GET['territorio']);
        } else {
            $result = $tipoIVAModel->allAdmin();
        }

        Response::success($result);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body['Codigo']) || empty($body['Descripcion'])) {
            http_response_code(400);
            Response::error('Código y descripción son obligatorios');
            exit;
        }
        if ($tipoIVAModel->exists($body['Codigo'])) {
            http_response_code(409);
            Response::error('Ya existe un tipo de IVA con ese código');
            exit;
        }
        $data = [
            'Codigo'                 => strtoupper(trim($body['Codigo'])),
            'Descripcion'            => trim($body['Descripcion']),
            'IVA'                    => isset($body['IVA'])  ? (float)$body['IVA']  : 0.00,
            'RE'                     => isset($body['RE'])   ? (float)$body['RE']   : 0.00,
            'Cuenta_IVA_Soportado'   => !empty($body['Cuenta_IVA_Soportado'])   ? trim($body['Cuenta_IVA_Soportado'])   : null,
            'Cuenta_IVA_Repercutido' => !empty($body['Cuenta_IVA_Repercutido']) ? trim($body['Cuenta_IVA_Repercutido']) : null,
            'Cuenta_RE_Soportado'    => !empty($body['Cuenta_RE_Soportado'])    ? trim($body['Cuenta_RE_Soportado'])    : null,
            'Cuenta_RE_Repercutido'  => !empty($body['Cuenta_RE_Repercutido'])  ? trim($body['Cuenta_RE_Repercutido'])  : null,
            'Tipo_Territorio'        => !empty($body['Tipo_Territorio'])        ? $body['Tipo_Territorio']               : null,
            'Activo'                 => ($body['Activo'] ?? 'S') === 'S' ? 'S' : 'N',
            'Orden'                  => isset($body['Orden']) ? (int)$body['Orden'] : 0,
            'Codigo_Verifactu'       => !empty($body['Codigo_Verifactu'])       ? trim($body['Codigo_Verifactu'])       : null,
            'Actualizado'            => isset($body['Actualizado']) ? (int)(bool)$body['Actualizado'] : 1,
        ];
        $tipoIVAModel->create($data);
        Response::success(['Codigo' => $data['Codigo']], 'Tipo de IVA creado correctamente', 201);

    } elseif ($method === 'PUT') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $codigo = $body['Codigo'] ?? '';
        if (empty($codigo)) {
            http_response_code(400);
            Response::error('Código del tipo de IVA requerido');
            exit;
        }
        if (!$tipoIVAModel->exists($codigo)) {
            http_response_code(404);
            Response::error('Tipo de IVA no encontrado');
            exit;
        }
        $data = [];
        if (isset($body['Descripcion']))   $data['Descripcion']            = trim($body['Descripcion']);
        if (isset($body['IVA']))           $data['IVA']                    = (float)$body['IVA'];
        if (isset($body['RE']))            $data['RE']                     = (float)$body['RE'];
        if (array_key_exists('Cuenta_IVA_Soportado', $body))
            $data['Cuenta_IVA_Soportado']   = !empty($body['Cuenta_IVA_Soportado'])   ? trim($body['Cuenta_IVA_Soportado'])   : null;
        if (array_key_exists('Cuenta_IVA_Repercutido', $body))
            $data['Cuenta_IVA_Repercutido'] = !empty($body['Cuenta_IVA_Repercutido']) ? trim($body['Cuenta_IVA_Repercutido']) : null;
        if (array_key_exists('Cuenta_RE_Soportado', $body))
            $data['Cuenta_RE_Soportado']    = !empty($body['Cuenta_RE_Soportado'])    ? trim($body['Cuenta_RE_Soportado'])    : null;
        if (array_key_exists('Cuenta_RE_Repercutido', $body))
            $data['Cuenta_RE_Repercutido']  = !empty($body['Cuenta_RE_Repercutido'])  ? trim($body['Cuenta_RE_Repercutido'])  : null;
        if (array_key_exists('Tipo_Territorio', $body))
            $data['Tipo_Territorio']        = !empty($body['Tipo_Territorio'])        ? $body['Tipo_Territorio']               : null;
        if (isset($body['Activo']))        $data['Activo']                 = $body['Activo'] === 'S' ? 'S' : 'N';
        if (isset($body['Orden']))         $data['Orden']                  = (int)$body['Orden'];
        if (array_key_exists('Codigo_Verifactu', $body))
            $data['Codigo_Verifactu']       = !empty($body['Codigo_Verifactu'])       ? trim($body['Codigo_Verifactu'])       : null;
        if (isset($body['Actualizado']))   $data['Actualizado']            = (int)(bool)$body['Actualizado'];
        if (empty($data)) {
            http_response_code(400);
            Response::error('Sin campos que actualizar');
            exit;
        }
        $tipoIVAModel->update($codigo, $data);
        Response::success(null, 'Tipo de IVA actualizado correctamente');

    } else {
        http_response_code(405);
        Response::error('Método no permitido');
    }
} catch (Exception $e) {
    Response::serverError('Error al procesar solicitud', $e);
}
