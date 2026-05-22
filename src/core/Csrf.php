<?php
class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const HEADER_KEY  = 'HTTP_X_CSRF_TOKEN';
    private const INPUT_NAME  = '_csrf_token';

    /** Genera (o devuelve el existente) token CSRF en sesión. */
    public static function generate(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /** Valida un token contra el almacenado en sesión. */
    public static function validate(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        return $stored !== '' && hash_equals($stored, $token);
    }

    /**
     * Para endpoints de API (AJAX).
     * En métodos que modifican estado (POST/PUT/PATCH/DELETE):
     *   1. Valida la cabecera X-CSRF-Token.
     *   2. Valida que el Content-Type sea application/json.
     * En GET/HEAD/OPTIONS no hace nada.
     */
    public static function requireApi(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;

        $token = $_SERVER[self::HEADER_KEY] ?? '';
        if (!self::validate($token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                ['success' => false, 'message' => 'Token CSRF inválido. Recarga la página e inténtalo de nuevo.'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        // DELETE sin cuerpo no envía Content-Type; solo validamos cuando hay cuerpo.
        if ($method !== 'DELETE') {
            $rawCt      = $_SERVER['CONTENT_TYPE'] ?? '';
            $contentType = strtolower(trim(explode(';', $rawCt)[0]));
            if ($contentType !== 'application/json') {
                http_response_code(415);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(
                    ['success' => false, 'message' => 'Content-Type debe ser application/json.'],
                    JSON_UNESCAPED_UNICODE
                );
                exit;
            }
        }
    }

    /**
     * Para formularios HTML tradicionales (POST).
     * Comprueba el campo oculto _csrf_token.
     */
    public static function requireForm(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        $token = $_POST[self::INPUT_NAME] ?? '';
        if (!self::validate($token)) {
            http_response_code(403);
            echo 'Token de seguridad inválido. Vuelve atrás y vuelve a intentarlo.';
            exit;
        }
    }
}
