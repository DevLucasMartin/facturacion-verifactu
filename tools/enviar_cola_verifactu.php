<?php
/**
 * Vaciado de la cola Verifactu — envío diferido a la AEAT.
 *
 * Pensado para ejecutarse periódicamente (Programador de tareas de Windows /
 * cron). Cuando se recupera Internet, envía a Hacienda las facturas que se
 * generaron sin conexión, EN ORDEN DE GENERACIÓN (el mismo del encadenamiento
 * de huellas) y DETENIÉNDOSE al primer fallo para no romper la cadena.
 *
 * Uso:
 *   php tools/enviar_cola_verifactu.php            # envía toda la cola
 *   php tools/enviar_cola_verifactu.php --limite=10  # como mucho 10 por pasada
 *
 * Códigos de salida:
 *   0  cola vacía o enviada por completo
 *   1  proceso detenido por fallo (queda cola pendiente) — revisar log
 *   2  sin conexión con la AEAT (no se intentó ningún envío)
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/services/VerifactuService.php';

// --limite=N (opcional)
$limite = null;
foreach ($argv as $arg) {
    if (preg_match('/^--limite=(\d+)$/', $arg, $m)) {
        $limite = max(1, (int)$m[1]);
    }
}

$marca = date('Y-m-d H:i:s');
echo "[{$marca}] Vaciando cola Verifactu" . ($limite ? " (límite {$limite})" : '') . "...\n";

$service   = new VerifactuService();
$resultado = $service->enviarCola($limite);

if (!empty($resultado['sin_conexion'])) {
    echo "  Sin conexión con la AEAT. {$resultado['total_cola']} factura(s) siguen en cola.\n";
    exit(2);
}

echo "  Cola: {$resultado['total_cola']} | Enviadas: {$resultado['enviados']} | Pendientes: {$resultado['pendientes_restantes']}\n";

foreach ($resultado['resultados'] as $r) {
    $estado = !empty($r['ok']) ? 'OK   ' : 'FALLO';
    $extra  = !empty($r['ok']) ? ('CSV ' . ($r['csv'] ?? '-')) : ($r['motivo'] ?? '');
    echo "    [{$estado}] {$r['tipo']} {$r['id_documento']}  {$extra}\n";
}

if ($resultado['detenido']) {
    echo "\n  ⚠ PROCESO DETENIDO: {$resultado['motivo_parada']}\n";
    echo "  Las facturas siguientes NO se han enviado para no romper el encadenamiento.\n";
    exit(1);
}

echo "  Cola procesada correctamente.\n";
exit(0);
