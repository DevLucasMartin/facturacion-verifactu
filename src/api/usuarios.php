<?php
/**
 * API de Usuarios
 *
 * GET    /api/usuarios          - Listar todos
 * GET    /api/usuarios/:usuario - Ver uno
 * POST   /api/usuarios          - Crear
 * PUT    /api/usuarios/:usuario - Modificar contraseña
 * DELETE /api/usuarios/:usuario - Eliminar
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path       = parse_url($requestUri, PHP_URL_PATH);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

$path    = trim(str_replace('.php', '', $path), '/');
$parts   = ($path === '') ? [] : explode('/', $path);
$recurso = $parts[0] ?? '';

try {
    $db = Database::getInstance();

    // GET /usuarios  o  GET /usuarios/:usuario
    if ($method === 'GET' && $recurso === 'usuarios') {
        $usuario = $parts[1] ?? '';

        if ($usuario !== '') {
            $row = $db->fetch('SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` = ?', [$usuario]);
            if (!$row) Response::notFound('Usuario no encontrado');
            Response::success($row);
        }

        $q     = trim($_GET['q'] ?? '');
        $items = $q !== ''
            ? $db->fetchAll('SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` LIKE ? ORDER BY `Usuario`', ["%{$q}%"])
            : $db->fetchAll('SELECT `Usuario` FROM `Usuarios` ORDER BY `Usuario`');

        Response::success($items);
    }

    // POST /usuarios  — crear
    if ($method === 'POST' && $recurso === 'usuarios') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $usuario = trim($body['Usuario']    ?? '');
        $pass    = trim($body['Contrasena'] ?? '');

        if ($usuario === '') { http_response_code(422); Response::error('El usuario es obligatorio'); }
        if ($pass    === '') { http_response_code(422); Response::error('La contraseña es obligatoria'); }
        if (!preg_match('/^[A-Za-z0-9_]{1,50}$/', $usuario)) {
            http_response_code(422);
            Response::error('El usuario solo puede contener letras, números y guion bajo (máx. 50)');
        }

        $existe = $db->fetch('SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` = ?', [$usuario]);
        if ($existe) { http_response_code(409); Response::error('El usuario ya existe'); }

        $db->query(
            'INSERT INTO `Usuarios` (`Usuario`, `Contrasena`) VALUES (?, SHA2(?, 256))',
            [$usuario, $pass]
        );
        http_response_code(201);
        Response::success(['Usuario' => $usuario], 'Usuario creado correctamente');
    }

    // PUT /usuarios/:usuario  — cambiar contraseña
    if ($method === 'PUT' && $recurso === 'usuarios') {
        $usuario = $parts[1] ?? '';
        if ($usuario === '') { http_response_code(422); Response::error('Se requiere el usuario'); }

        $row = $db->fetch('SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` = ?', [$usuario]);
        if (!$row) Response::notFound('Usuario no encontrado');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $pass = trim($body['Contrasena'] ?? '');
        if ($pass === '') { http_response_code(422); Response::error('La nueva contraseña es obligatoria'); }

        $misma = $db->fetch(
            'SELECT 1 FROM `Usuarios` WHERE `Usuario` = ? AND `Contrasena` = SHA2(?, 256)',
            [$usuario, $pass]
        );
        if ($misma) {
            http_response_code(422);
            Response::error('La nueva contraseña es igual a la actual. Elige una contraseña diferente.');
        }

        $db->query(
            'UPDATE `Usuarios` SET `Contrasena` = SHA2(?, 256) WHERE `Usuario` = ?',
            [$pass, $usuario]
        );
        Response::success(['Usuario' => $usuario], 'Contraseña actualizada correctamente');
    }

    // DELETE /usuarios/:usuario  — eliminar
    if ($method === 'DELETE' && $recurso === 'usuarios') {
        $usuario = $parts[1] ?? '';
        if ($usuario === '') { http_response_code(422); Response::error('Se requiere el usuario'); }

        $row = $db->fetch('SELECT `Usuario` FROM `Usuarios` WHERE `Usuario` = ?', [$usuario]);
        if (!$row) Response::notFound('Usuario no encontrado');

        $db->query('DELETE FROM `Usuarios` WHERE `Usuario` = ?', [$usuario]);
        Response::success(null, 'Usuario eliminado correctamente');
    }

} catch (Exception $e) {
    Response::serverError('Error al procesar la solicitud', $e);
}
