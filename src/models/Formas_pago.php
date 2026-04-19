<?php
/**
 * Modelo Formas_pago - Acceso a canales/series de facturación
 * Módulo de Facturación
 */

class Formas_pago {
    private Database $fact_conex_BBDD_forma_pago;

    public function __construct() {
        $this->fact_conex_BBDD_forma_pago = Database::getInstance();
    }

    /**
     * Buscar canal por código
     */
    public function find(string $fact_id_forma_pago): ?array {
        return $this->fact_conex_BBDD_forma_pago->fetch(
            "SELECT * FROM `Canales` WHERE `Codigo` = ?",
            [$fact_id_forma_pago]
        );
    }

    /**
     * Obtener todas las formas de pago activas
     */
    public function all(): array {
        return $this->fact_conex_BBDD_forma_pago->fetchAll(
            "SELECT `Codigo` AS `Id_Forma_Pago`, `Descripcion`
             FROM `Formas_Pago`
             WHERE `Activo` = 'S'
             ORDER BY `Codigo`"
        );
    }

    /**
     * Obtener canales que permiten facturación (no tickets)
     */
    public function facturacion(): array {
        return $this->fact_conex_BBDD_forma_pago->fetchAll(
            "SELECT `Codigo`, `Descripcion`, `Id_Cliente_Facturacion`, `Facturacion_Defecto`
             FROM `Canales`
             WHERE `Ticket` = 'N' OR `Ticket` IS NULL
             ORDER BY `Descripcion`"
        );
    }

    /**
     * Obtener canal por defecto
     */
    public function getDefault(): ?array {
        $fact_forma_pago = $this->fact_conex_BBDD_forma_pago->fetch(
            "SELECT `Codigo`, `Descripcion`
             FROM `Canales`
             WHERE `Facturacion_Defecto` = 'S'
             LIMIT 1"
        );

        if (!$fact_forma_pago) {
            return $this->fact_conex_BBDD_forma_pago->fetch(
                "SELECT `Codigo`, `Descripcion` FROM `Canales` ORDER BY `Descripcion` LIMIT 1"
            );
        }

        return $fact_forma_pago;
    }
}
