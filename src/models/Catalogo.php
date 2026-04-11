<?php
/**
 * Modelo Catalogo - Tablas maestras de solo lectura
 * Módulo de Facturación
 *
 * Centraliza el acceso a tablas de catálogo simples para evitar
 * crear un modelo y API por cada tabla maestra.
 */

class Catalogo {
    private Database $db;

    /**
     * Tablas permitidas y su configuración de consulta.
     * Formato: 'clave' => [tabla, columna_id, columna_label, orden]
     */
    private const WHITELIST = [
        'paises'        => ['Paises',        'Codigo', 'Nombre', 'Nombre'],
        'tipos_cliente' => ['Tipos_Clientes', 'Codigo', 'Codigo', 'Codigo'],
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Devuelve si una clave de catálogo es válida.
     */
    public function esValido(string $clave): bool {
        return isset(self::WHITELIST[$clave]);
    }

    /**
     * Obtiene todos los registros de un catálogo.
     * Devuelve siempre un array con claves 'id' y 'label'.
     */
    public function todos(string $clave): array {
        [$tabla, $colId, $colLabel, $orden] = self::WHITELIST[$clave];

        return $this->db->fetchAll(
            "SELECT `{$colId}` AS id, `{$colLabel}` AS label
             FROM `{$tabla}`
             ORDER BY `{$orden}`"
        );
    }
}
