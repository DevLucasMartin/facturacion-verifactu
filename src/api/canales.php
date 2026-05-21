<?php
/**
 * API de Canales / Series de facturación
 *
 * GET    /api/canales.php          → lista todos los canales activos
 * GET    /api/canales.php/:codigo  → un canal concreto
 * POST   /api/canales.php          → crear canal
 * PUT    /api/canales.php          → actualizar canal
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance();

    // ── GET ──────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $path      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }
        $path  = trim(str_replace('.php', '', $path), '/');
        $parts = $path !== '' ? explode('/', $path) : [];
        $codigo = $parts[1] ?? '';

        if ($codigo !== '') {
            $row = $db->fetch(
                "SELECT `Codigo`, `Descripcion`, `Color`, `Ticket`,
                        `Facturacion_Defecto`, `Id_Cliente_Facturacion`,
                        `Direccion_Facturacion`, `Porcentaje_Antieconomico`,
                        `Prioridad`, `Tipo_Prioridad`, `Departamento`,
                        `Cobrar_Franquicia`, `Computable`, `Activo`
                 FROM `Canales` WHERE `Codigo` = ?",
                [$codigo]
            );
            if (!$row) {
                Response::notFound('Canal no encontrado');
            }
            Response::success($row);
        }

        $soloActivos = ($_GET['activo'] ?? 'S') !== 'N';
        $where = $soloActivos ? "WHERE `Activo` = 'S'" : '';

        $rows = $db->fetchAll(
            "SELECT `Codigo`, `Descripcion`, `Color`, `Ticket`,
                    `Facturacion_Defecto`, `Id_Cliente_Facturacion`,
                    `Direccion_Facturacion`, `Porcentaje_Antieconomico`,
                    `Prioridad`, `Tipo_Prioridad`, `Departamento`,
                    `Cobrar_Franquicia`, `Computable`, `Activo`
             FROM `Canales`
             {$where}
             ORDER BY `Codigo`"
        );

        Response::success($rows);
    }

    // ── POST (crear) ─────────────────────────────────────────────────────────
    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            Response::badRequest('Cuerpo JSON inválido');
        }

        $codigo      = strtoupper(trim($body['Codigo'] ?? ''));
        $descripcion = trim($body['Descripcion'] ?? '');
        if ($codigo === '' || $descripcion === '') {
            Response::badRequest('Código y descripción son obligatorios');
        }

        $db->query(
            "INSERT INTO `Canales`
                (`Codigo`, `Descripcion`, `Color`, `Ticket`, `Facturacion_Defecto`,
                 `Id_Cliente_Facturacion`, `Direccion_Facturacion`, `Porcentaje_Antieconomico`,
                 `Prioridad`, `Tipo_Prioridad`, `Departamento`, `Cobrar_Franquicia`, `Computable`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $codigo,
                $descripcion,
                $body['Color'] ?? null,
                $body['Ticket'] ?? 'N',
                $body['Facturacion_Defecto'] ?? 'N',
                $body['Id_Cliente_Facturacion'] ?? null,
                $body['Direccion_Facturacion'] ?? null,
                $body['Porcentaje_Antieconomico'] ?? null,
                $body['Prioridad'] ?? null,
                $body['Tipo_Prioridad'] ?? null,
                $body['Departamento'] ?? null,
                $body['Cobrar_Franquicia'] ?? 0,
                $body['Computable'] ?? 0,
            ]
        );

        Response::success(null, 'Canal creado correctamente');
    }

    // ── PUT (actualizar) ─────────────────────────────────────────────────────
    if ($method === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            Response::badRequest('Cuerpo JSON inválido');
        }

        $codigo = strtoupper(trim($body['Codigo'] ?? ''));
        if ($codigo === '') {
            Response::badRequest('Código es obligatorio');
        }

        $db->query(
            "UPDATE `Canales` SET
                `Descripcion`             = ?,
                `Color`                   = ?,
                `Ticket`                  = ?,
                `Facturacion_Defecto`     = ?,
                `Id_Cliente_Facturacion`  = ?,
                `Direccion_Facturacion`   = ?,
                `Porcentaje_Antieconomico`= ?,
                `Prioridad`               = ?,
                `Tipo_Prioridad`          = ?,
                `Departamento`            = ?,
                `Cobrar_Franquicia`       = ?,
                `Computable`              = ?
             WHERE `Codigo` = ?",
            [
                trim($body['Descripcion'] ?? ''),
                $body['Color'] ?? null,
                $body['Ticket'] ?? 'N',
                $body['Facturacion_Defecto'] ?? 'N',
                $body['Id_Cliente_Facturacion'] ?? null,
                $body['Direccion_Facturacion'] ?? null,
                $body['Porcentaje_Antieconomico'] ?? null,
                $body['Prioridad'] ?? null,
                $body['Tipo_Prioridad'] ?? null,
                $body['Departamento'] ?? null,
                $body['Cobrar_Franquicia'] ?? 0,
                $body['Computable'] ?? 0,
                $codigo,
            ]
        );

        Response::success(null, 'Canal actualizado correctamente');
    }

    Response::methodNotAllowed();

} catch (Throwable $e) {
    Response::serverError('Error en la API de canales', $e);
}
