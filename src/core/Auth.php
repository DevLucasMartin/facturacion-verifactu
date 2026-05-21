<?php
/**
 * Autenticación centralizada
 *
 * Auth::requireLogin() → para vistas: redirige al login si no hay sesión.
 * Auth::requireApi()   → para APIs:   devuelve 401 JSON si no hay sesión.
 */

class Auth
{
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Protege una vista HTML. Redirige al login si no está autenticado. */
    public static function requireLogin(): void
    {
        self::startSession();
        if (empty($_SESSION['usuario'])) {
            header('Location: /SistemaGestionFacturas/src/views/login.php');
            exit;
        }
    }

    /** Protege un endpoint de API. Devuelve 401 JSON si no está autenticado. */
    public static function requireApi(): void
    {
        self::startSession();
        if (empty($_SESSION['usuario'])) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'No autenticado. Inicia sesión para continuar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /** Devuelve el usuario de la sesión actual o null. */
    public static function usuario(): ?string
    {
        self::startSession();
        return $_SESSION['usuario'] ?? null;
    }

    /** Comprueba si el usuario actual es admin. */
    public static function esAdmin(): bool
    {
        return self::usuario() === 'admin';
    }
}
