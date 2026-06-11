<?php
/**
 * Helper de respuestas HTTP JSON
 * Módulo de Facturación
 */

require_once __DIR__ . '/Logger.php';

class Response {

    /**
     * Envía una respuesta de éxito.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): void {
        self::send($status, [
            'ok'      => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * Envía una respuesta de error del cliente (4xx).
     */
    public static function error(string $message, int $status = 400, mixed $errors = null): void {
        $body = [
            'ok'      => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        self::send($status, $body);
    }

    /**
     * 404 Not Found.
     */
    public static function notFound(string $message = 'Recurso no encontrado'): void {
        self::error($message, 404);
    }

    /**
     * 405 Method Not Allowed.
     */
    public static function methodNotAllowed(string $message = 'Método no permitido'): void {
        self::error($message, 405);
    }

    /**
     * 201 Created.
     */
    public static function created(mixed $data = null, string $message = 'Creado correctamente'): void {
        self::send(201, [
            'ok'      => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * 422 Unprocessable Entity - errores de validación.
     */
    public static function validationError(array $errors): void {
        self::send(422, [
            'ok'      => false,
            'message' => 'Error de validación',
            'errors'  => $errors,
        ]);
    }

    /**
     * Envía una respuesta JSON con cuerpo y status arbitrarios.
     */
    public static function json(array $body, int $status = 200): void {
        self::send($status, $body);
    }

    /**
     * 500 Internal Server Error.
     */
    public static function serverError(string $message = 'Error interno del servidor', ?\Throwable $e = null): void {
        $body = [
            'ok'      => false,
            'message' => $message,
        ];
        if ($e !== null) {
            Logger::exception('http', $e, ['mensaje' => $message]);
            $body['error_detail'] = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
        }
        self::send(500, $body);
    }

    /**
     * Respuesta paginada estándar.
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): void {
        self::send(200, [
            'ok'   => true,
            'data' => [
                'items'       => $items,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    /**
     * Envía la respuesta y termina la ejecución.
     */
    private static function send(int $status, array $body): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            // Fallback: re-encode sanitizando strings con encoding inválido
            array_walk_recursive($body, function (&$v) {
                if (is_string($v)) $v = mb_convert_encoding($v, 'UTF-8', 'auto');
            });
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        echo $encoded;
        exit;
    }
}
