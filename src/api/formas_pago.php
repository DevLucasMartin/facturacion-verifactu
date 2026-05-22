<?php
/**
 * API de Formas de Pago
 * Módulo de Facturación
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();
require_once __DIR__ . '/../core/Csrf.php';
Csrf::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Formas_pago.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $model = new Formas_pago();

    if ($method === 'GET') {
        Response::success($model->all());
    } else {
        http_response_code(405);
        Response::error('Método no permitido');
    }
} catch (Exception $error) {
    Response::serverError('Error al procesar solicitud', $error);
}
