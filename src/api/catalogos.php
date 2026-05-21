<?php
/**
 * API de Catálogos (tablas maestras de solo lectura)
 * Módulo de Facturación
 *
 * GET /api/catalogos.php?tabla=paises
 * GET /api/catalogos.php?tabla=tipos_cliente
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Catalogo.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        Response::error('Método no permitido');
    }

    $tabla = trim($_GET['tabla'] ?? '');

    if ($tabla === '') {
        http_response_code(400);
        Response::error('Se requiere el parámetro "tabla"');
    }

    $catalogo = new Catalogo();

    if (!$catalogo->esValido($tabla)) {
        http_response_code(404);
        Response::error("Catálogo '{$tabla}' no encontrado");
    }

    Response::success($catalogo->todos($tabla));

} catch (Exception $error) {
    Response::serverError('Error al procesar solicitud', $error);
}
