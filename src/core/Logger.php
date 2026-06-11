<?php
/**
 * Logger central por servicio.
 *
 * Escribe un archivo de log independiente por cada servicio en
 * src/storage/logs/<servicio>.log. Cada línea registra la fecha y hora
 * exacta del fallo, el nivel y el mensaje del error.
 *
 * Uso:
 *   Logger::error('verifactu', 'No se pudo enviar a la AEAT', ['documento' => $codigo]);
 *   Logger::exception('pdf', $e, ['documento' => $codigo]);
 */

class Logger
{
    /** Directorio donde se guardan los logs (uno por servicio). */
    private static string $dir = __DIR__ . '/../storage/logs';

    /** Registra un fallo (nivel ERROR) de un servicio. */
    public static function error(string $servicio, string $mensaje, array $contexto = []): void
    {
        self::escribir($servicio, 'ERROR', $mensaje, $contexto);
    }

    /** Registra una advertencia (nivel WARNING) de un servicio. */
    public static function warning(string $servicio, string $mensaje, array $contexto = []): void
    {
        self::escribir($servicio, 'WARNING', $mensaje, $contexto);
    }

    /** Registra información (nivel INFO) de un servicio. */
    public static function info(string $servicio, string $mensaje, array $contexto = []): void
    {
        self::escribir($servicio, 'INFO', $mensaje, $contexto);
    }

    /** Registra una excepción capturada, añadiendo el archivo y la línea de origen. */
    public static function exception(string $servicio, \Throwable $e, array $contexto = []): void
    {
        $contexto['origen'] = basename($e->getFile()) . ':' . $e->getLine();
        self::escribir($servicio, 'ERROR', $e->getMessage(), $contexto);
    }

    /** Vuelca el mensaje en el archivo del servicio correspondiente. */
    private static function escribir(string $servicio, string $nivel, string $mensaje, array $contexto): void
    {
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0755, true);
        }

        $archivo = self::$dir . '/' . self::nombreArchivo($servicio) . '.log';

        $linea = '[' . date('Y-m-d H:i:s') . "] {$nivel}: {$mensaje}";

        if (!empty($contexto)) {
            $linea .= ' | ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        @file_put_contents($archivo, $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** Convierte el nombre de un servicio en un nombre de archivo seguro. */
    private static function nombreArchivo(string $servicio): string
    {
        $nombre = strtolower(trim($servicio));
        $nombre = preg_replace('/[^a-z0-9_\-]/', '_', $nombre);
        return $nombre !== '' ? $nombre : 'general';
    }
}
