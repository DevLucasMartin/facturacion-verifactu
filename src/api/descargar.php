<?php
/**
 * Endpoint de descarga de archivos temporales
 */

require_once __DIR__ . '/../core/Auth.php';
Auth::requireApi();

$archivo = $_GET['archivo'] ?? '';

// Seguridad: solo nombre de archivo, sin rutas
$archivo = basename($archivo);

error_log('descargar.php - archivo: ' . $archivo . ' - ruta: ' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo . ' - existe: ' . (file_exists(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo) ? 'SI' : 'NO'));

if (empty($archivo) || !preg_match('/^(facturas|albaranes)_\d{8}_\d{6}\.xlsx$/', $archivo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Archivo no válido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $archivo;

if (!file_exists($ruta)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Archivo no encontrado o expirado'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-cache');

readfile($ruta);
unlink($ruta);
exit;
