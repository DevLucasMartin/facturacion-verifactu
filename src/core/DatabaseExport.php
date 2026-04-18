<?php
/**
 * Conexión de base de datos para exportaciones (sesión cerrada).
 * Extiende Database para operaciones de solo lectura de larga duración.
 */
class DatabaseExport extends Database
{
    private static ?DatabaseExport $exportInstance = null;

    /**
     * Devuelve una instancia independiente (no el singleton principal).
     */
    public static function getInstance(): static
    {
        if (self::$exportInstance === null) {
            self::$exportInstance = new self();
        }
        return self::$exportInstance;
    }

    /**
     * Libera la conexión.
     */
    public function close(): void
    {
        self::$exportInstance = null;
    }
}
