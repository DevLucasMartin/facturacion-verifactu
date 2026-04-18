<?php
/**
 * API de Tarifas
 * Módulo de Facturación
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Tarifa.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $model = new Tarifa();

    if ($method === 'GET') {
        Response::success($model->all());
    } else {
        http_response_code(405);
        Response::error('Método no permitido');
    }
} catch (Exception $error) {
    Response::serverError('Error al procesar solicitud', $error);
}
