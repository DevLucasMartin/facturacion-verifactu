<?php
/**
 * Modelo TipoIVA - Acceso a tipos de IVA
 * Módulo de Facturación
 */

class TipoIVA {
    private Database $db;
    private static ?array $cache = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Buscar tipo de IVA por código
     */
    public function find(string $codigo): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Tipos_IVA` WHERE `Codigo` = ?",
            [$codigo]
        );
    }

    /**
     * Obtener todos los tipos de IVA sin filtrar (para administración)
     */
    public function allAdmin(): array {
        return $this->db->fetchAll(
            "SELECT `Codigo`, `Descripcion`, `IVA`, `RE`,
                    `Cuenta_IVA_Soportado`, `Cuenta_IVA_Repercutido`,
                    `Cuenta_RE_Soportado`, `Cuenta_RE_Repercutido`,
                    `Tipo_Territorio`, `Activo`, `Orden`,
                    `Codigo_Verifactu`, `Actualizado`
             FROM `Tipos_IVA`
             ORDER BY `Orden`, `Codigo`"
        );
    }

    /**
     * Obtener todos los tipos de IVA visibles en el desplegable (con caché).
     * Solo devuelve registros activos (Activo='S') y marcados como actualizados (Actualizado=1).
     */
    public function all(): array {
        if (self::$cache === null) {
            self::$cache = $this->db->fetchAll(
                "SELECT `Codigo`, `Descripcion`, `IVA`, `RE`,
                        `Cuenta_IVA_Soportado`, `Cuenta_IVA_Repercutido`,
                        `Cuenta_RE_Soportado`, `Cuenta_RE_Repercutido`,
                        `Tipo_Territorio`, `Activo`, `Orden`,
                        `Codigo_Verifactu`, `Actualizado`
                 FROM `Tipos_IVA`
                 WHERE (`Activo` = 'S' OR `Activo` IS NULL)
                   AND `Actualizado` = 1
                 ORDER BY `Orden`, `Codigo`"
            );
        }
        return self::$cache;
    }

    /**
     * Obtener tipos de IVA activos y actualizados
     */
    public function activos(): array {
        return array_filter($this->all(), function($tipo) {
            return ($tipo['Activo'] ?? 'S') === 'S';
        });
    }

    /**
     * Obtener tipos de IVA por territorio (activos y actualizados)
     */
    public function porTerritorio(string $territorio): array {
        return $this->db->fetchAll(
            "SELECT `Codigo`, `Descripcion`, `IVA`, `RE`
             FROM `Tipos_IVA`
             WHERE `Tipo_Territorio` = ?
               AND (`Activo` = 'S' OR `Activo` IS NULL)
               AND `Actualizado` = 1
             ORDER BY `IVA`",
            [$territorio]
        );
    }

    /**
     * Calcular IVA de un importe
     */
    public function calcularIVA(float $base, string $codigo): float {
        $tipo = $this->find($codigo);
        if (!$tipo) {
            return 0;
        }
        return round($base * ((float)$tipo['IVA'] / 100), 2);
    }

    /**
     * Calcular RE de un importe
     */
    public function calcularRE(float $base, string $codigo): float {
        $tipo = $this->find($codigo);
        if (!$tipo) {
            return 0;
        }
        return round($base * ((float)$tipo['RE'] / 100), 2);
    }

    /**
     * Comprobar si existe un tipo de IVA por código
     */
    public function exists(string $codigo): bool {
        $row = $this->db->fetch(
            "SELECT 1 FROM `Tipos_IVA` WHERE `Codigo` = ?",
            [$codigo]
        );
        return $row !== null;
    }

    /**
     * Crear un nuevo tipo de IVA
     */
    public function create(array $data): void {
        $this->db->query(
            "INSERT INTO `Tipos_IVA`
                (`Codigo`,`Descripcion`,`IVA`,`RE`,
                 `Cuenta_IVA_Soportado`,`Cuenta_IVA_Repercutido`,
                 `Cuenta_RE_Soportado`,`Cuenta_RE_Repercutido`,
                 `Tipo_Territorio`,`Activo`,`Orden`,`Codigo_Verifactu`,`Actualizado`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['Codigo'],
                $data['Descripcion'],
                $data['IVA'],
                $data['RE'],
                $data['Cuenta_IVA_Soportado']   ?? null,
                $data['Cuenta_IVA_Repercutido'] ?? null,
                $data['Cuenta_RE_Soportado']    ?? null,
                $data['Cuenta_RE_Repercutido']  ?? null,
                $data['Tipo_Territorio']        ?? null,
                $data['Activo']                 ?? 'S',
                $data['Orden']                  ?? 0,
                $data['Codigo_Verifactu']       ?? null,
                $data['Actualizado']            ?? 1,
            ]
        );
        self::$cache = null;
    }

    /**
     * Actualizar un tipo de IVA existente
     */
    public function update(string $codigo, array $data): int {
        if (empty($data)) {
            return 0;
        }
        $result = $this->db->update('Tipos_IVA', $data, '`Codigo` = ?', [$codigo]);
        self::$cache = null;
        return $result;
    }

    /**
     * Limpiar caché
     */
    public static function clearCache(): void {
        self::$cache = null;
    }
}
