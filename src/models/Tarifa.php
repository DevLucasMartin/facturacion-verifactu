<?php
/**
 * Modelo Tarifa - Acceso a tarifas
 * Módulo de Facturación
 */

class Tarifa {
    private Database $fact_conex_BBDD_tarifa;

    public function __construct() {
        $this->fact_conex_BBDD_tarifa = Database::getInstance();
    }

    /**
     * Buscar clientes que usan una tarifa concreta
     */
    public function find(string $fact_tarifa): ?array {
        return $this->fact_conex_BBDD_tarifa->fetch(
            "SELECT * FROM `Clientes` WHERE `Tarifa` = ?",
            [$fact_tarifa]
        );
    }

    /**
     * Obtener todas las tarifas distintas usadas en clientes
     */
    public function all(): array {
        return $this->fact_conex_BBDD_tarifa->fetchAll(
            "SELECT DISTINCT `Tarifa`
             FROM `Clientes`
             WHERE `Tarifa` IS NOT NULL
             ORDER BY `Tarifa`"
        );
    }

    /**
     * Obtener tarifa por defecto (la primera registrada)
     */
    public function getDefault(): ?array {
        $fact_tarifa = $this->fact_conex_BBDD_tarifa->fetch(
            "SELECT `Tarifa`
             FROM `Clientes`
             ORDER BY `Tarifa`
             LIMIT 1"
        );

        if (!$fact_tarifa) {
            return $this->fact_conex_BBDD_tarifa->fetch(
                "SELECT `Tarifa` FROM `Clientes` LIMIT 1"
            );
        }

        return $fact_tarifa;
    }
}
