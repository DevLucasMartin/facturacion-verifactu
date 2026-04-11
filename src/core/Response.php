<?php
/**
 * Helper de respuestas HTTP JSON
 * Módulo de Facturación
 */

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
     * 500 Internal Server Error.
     */
    public static function serverError(string $message = 'Error interno del servidor'): void {
        self::send(500, [
            'ok'      => false,
            'message' => $message,
        ]);
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
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
