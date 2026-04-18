<?php
/**
 * API de Canales / Series de facturación
 *
 * GET /api/canales.php          → lista todos los canales activos
 * GET /api/canales.php/:codigo  → un canal concreto
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::methodNotAllowed();
}

try {
    $db = Database::getInstance();

    // Extraer segmento de ruta tras el nombre del script
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $path      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }
    $path  = trim(str_replace('.php', '', $path), '/');
    $parts = $path !== '' ? explode('/', $path) : [];
    // $parts[0] = 'canales', $parts[1] = opcional código

    $codigo = $parts[1] ?? '';

    if ($codigo !== '') {
        $row = $db->fetch(
            "SELECT `Codigo` AS id_canal, `Descripcion` AS nombre,
                    `Activo`, `Ticket`, `Facturacion_Defecto`
             FROM `Canales` WHERE `Codigo` = ?",
            [$codigo]
        );
        if (!$row) {
            Response::notFound('Canal no encontrado');
        }
        Response::success($row);
    }

    // Listado
    $soloActivos = ($_GET['activo'] ?? 'S') !== 'N';
    $where = $soloActivos ? "WHERE `Activo` = 'S'" : '';

    $rows = $db->fetchAll(
        "SELECT `Codigo` AS id_canal, `Descripcion` AS nombre,
                `Activo`, `Ticket`, `Facturacion_Defecto`
         FROM `Canales`
         {$where}
         ORDER BY `Codigo`"
    );

    Response::success($rows);

} catch (Throwable $e) {
    Response::serverError('Error al obtener canales', $e);
}
