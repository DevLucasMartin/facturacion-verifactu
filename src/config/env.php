<?php
/**
 * Carga de configuración por entorno.
 *
 * Toda la configuración variable por cliente vive en el archivo `.env` de la
 * raíz del proyecto. En Docker esas variables se inyectan en el contenedor
 * (compose -> env_file), por lo que getenv() las ve directamente. Como respaldo
 * (p. ej. ejecución sobre XAMPP sin Docker) este helper también lee el `.env`
 * de la raíz si existe.
 *
 * Uso:  env('DB_HOST', 'localhost')
 */

if (!function_exists('app_load_dotenv')) {

    /** Carga (una sola vez) el archivo .env de la raíz en el entorno del proceso. */
    function app_load_dotenv(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = __DIR__ . '/../../.env';
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // Quitar comillas envolventes opcionales
            $len = strlen($val);
            if ($len >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[$len - 1] === $val[0]) {
                $val = substr($val, 1, -1);
            }

            // No pisar variables que ya vengan del entorno (las de Docker mandan)
            if (getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key]    = $val;
                $_SERVER[$key] = $val;
            }
        }
    }

    /**
     * Devuelve una variable de entorno con conversión básica de tipos.
     * Cadenas "true"/"false"/"null" se convierten a sus tipos PHP.
     */
    function env(string $key, mixed $default = null): mixed
    {
        app_load_dotenv();

        $val = getenv($key);
        if ($val === false || $val === '') {
            return $default;
        }

        return match (strtolower($val)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $val,
        };
    }
}
