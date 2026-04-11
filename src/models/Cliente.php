<?php
/**
 * Modelo Cliente - Acceso a datos de clientes
 * Módulo de Facturación
 */

class Cliente {
    private Database $fact_conexionBBDD;

    public function __construct() {
        $this->fact_conexionBBDD = Database::getInstance();
    }

    /**
     * Buscar cliente por código
     */
    public function find(int|string $fact_id_cliente): ?array {
        return $this->fact_conexionBBDD->fetch(
            "SELECT * FROM `Clientes` WHERE `Codigo` = ?",
            [$fact_id_cliente]
        );
    }

    /**
     * Crear un nuevo cliente. Devuelve el Codigo asignado.
     */
    public function create(array $data): string
    {
        $data['Fecha_Alta']                  = date('Y-m-d H:i:s');
        $data['Usuario_Alta']                = $_SESSION['usuario'] ?? 'sistema';
        $data['Ultima_Modificacion']         = date('Y-m-d H:i:s');
        $data['Usuario_Ultima_Modificacion'] = $_SESSION['usuario'] ?? 'sistema';

        return $this->fact_conexionBBDD->insert('Clientes', $data);
    }

    /**
     * Actualizar el NIF de un cliente.
     */
    public function updateNif(string $codigo, string $nif): void
    {
        $this->fact_conexionBBDD->update(
            'Clientes',
            [
                'NIF'                         => $nif,
                'Ultima_Modificacion'         => date('Y-m-d H:i:s'),
                'Usuario_Ultima_Modificacion' => $_SESSION['usuario'] ?? 'sistema',
            ],
            '`Codigo` = ?',
            [$codigo]
        );
    }

    /**
     * Buscar cliente por NIF
     */
    public function findByNIF(string $fact_nif_cliente): ?array {
        return $this->fact_conexionBBDD->fetch(
            "SELECT * FROM `Clientes` WHERE `NIF` = ?",
            [$fact_nif_cliente]
        );
    }

    /**
     * Obtener cliente con sus datos completos (direcciones, teléfonos)
     */
    public function findWithDetails(int|string $fact_id_cliente): ?array {
        $fact_cliente = $this->find($fact_id_cliente);

        if (!$fact_cliente) {
            return null;
        }

        // Cargar direcciones
        $fact_cliente['direcciones'] = $this->fact_conexionBBDD->fetchAll(
            "SELECT * FROM `Direcciones_Clientes`
             WHERE `Id_Cliente` = ?",
            [$fact_id_cliente]
        );

        // Cargar teléfonos
        $fact_cliente['telefonos'] = $this->fact_conexionBBDD->fetchAll(
            "SELECT * FROM `Telefonos_Clientes`
             WHERE `Id_Cliente` = ?",
            [$fact_id_cliente]
        );

        return $fact_cliente;
    }

    /**
     * Buscar clientes por texto (nombre, NIF, código)
     */
    public function search(string $fact_busq_cliente, int $fact_limit_busq = 20): array {
        $fact_limit_busq = max(1, (int)$fact_limit_busq);
        $fact_busq_param = "%{$fact_busq_cliente}%";

        return $this->fact_conexionBBDD->fetchAll(
            "SELECT `Codigo`, `Archivar_Como`, `Apellidos`, `NIF`,
                    `Id_Tipo_IVA`, `RE_Porcentaje`, `Aplica_RE`, `Id_Forma_Pago`,
                    `Id_Zona`, `Tarifa`, `Email_Facturacion`
             FROM `Clientes`
             WHERE `Archivar_Como` LIKE ?
                OR `NIF`          LIKE ?
                OR `Codigo`       LIKE ?
             ORDER BY `Codigo`
             LIMIT 25",
            [$fact_busq_param, $fact_busq_param, $fact_busq_param]
        );
    }

    /**
     * Listar clientes con paginación
     */
    public function paginate(int $fact_pag = 1, int $fact_perPage = 25): array {
        $fact_pag     = max(1, $fact_pag);
        $fact_perPage = max(1, $fact_perPage);
        $offset       = ($fact_pag - 1) * $fact_perPage;

        $fact_total_clientes = (int)$this->fact_conexionBBDD->fetchCell(
            "SELECT COUNT(*) FROM `Clientes`"
        );

        $fact_items = $this->fact_conexionBBDD->fetchAll(
            "SELECT `Codigo`, `NIF`, `Archivar_Como`,
                    `Id_Tipo_IVA`, `RE_Porcentaje`, `Aplica_RE`, `Id_Forma_Pago`
             FROM `Clientes`
             ORDER BY `Codigo`
             LIMIT {$fact_perPage} OFFSET {$offset}"
        );

        return [
            'items'       => $fact_items,
            'total'       => $fact_total_clientes,
            'page'        => $fact_pag,
            'per_page'    => $fact_perPage,
            'total_pages' => (int)ceil($fact_total_clientes / $fact_perPage),
        ];
    }

    /**
     * Obtener clientes con RE (Recargo de Equivalencia)
     */
    public function findWithRE(): array {
        return $this->fact_conexionBBDD->fetchAll(
            "SELECT `Codigo`, `NIF`, `RE_Porcentaje`
             FROM `Clientes`
             WHERE `RE_Porcentaje` > 0"
        );
    }

    /**
     * Obtener forma de pago del cliente
     */
    public function getFormaPago(int|string $idCliente): ?array {
        $fact_cliente = $this->find($idCliente);

        if (!$fact_cliente || empty($fact_cliente['Id_Forma_Pago'])) {
            return null;
        }

        return Database::getInstance()->fetch(
            "SELECT * FROM `Formas_Pago` WHERE `Codigo` = ?",
            [$fact_cliente['Id_Forma_Pago']]
        );
    }

    /**
     * Obtener tipo de IVA del cliente
     */
    public function getTipoIVA(int|string $idCliente): ?array {
        $fact_cliente = $this->find($idCliente);

        if (!$fact_cliente || empty($fact_cliente['Id_Tipo_IVA'])) {
            return null;
        }

        return Database::getInstance()->fetch(
            "SELECT * FROM `Tipos_IVA` WHERE `Codigo` = ?",
            [$fact_cliente['Id_Tipo_IVA']]
        );
    }

    /**
     * Verificar si un cliente tiene Recargo de Equivalencia
     */
    public function tieneRE(int|string $id): bool {
        $fact_cliente = $this->find($id);
        return $fact_cliente && ($fact_cliente['RE_Porcentaje'] ?? 0) > 0;
    }

    /**
     * Obtener porcentaje de RE del cliente
     */
    public function getREPorcentaje(int|string $id): float {
        $fact_cliente = $this->find($id);
        return $fact_cliente ? (float)($fact_cliente['RE_Porcentaje'] ?? 0) : 0;
    }

    /**
     * Obtener email de facturación del cliente
     */
    public function getEmailFacturacion(int|string $id): ?string {
        $fact_cliente = $this->find($id);

        if (!$fact_cliente) {
            return null;
        }

        if (!empty($fact_cliente['Email_Facturacion'])) {
            return $fact_cliente['Email_Facturacion'];
        }

        $fact_direccion_cliente = Database::getInstance()->fetch(
            "SELECT `Correo_Electronico`
             FROM `Direcciones_Clientes`
             WHERE `Id_Cliente` = ? AND `Correo_Electronico` IS NOT NULL
             ORDER BY `Predeterminada` DESC
             LIMIT 1",
            [$id]
        );

        return $fact_direccion_cliente['Correo_Electronico'] ?? null;
    }

    /**
     * Obtener descuentos del cliente
     */
    public function getDescuentos(int|string $fact_id_cliente): array {
        $fact_cliente = $this->find($fact_id_cliente);

        if (!$fact_cliente) {
            return [
                'Descuento_Especial'   => 0,
                'Descuento_PP'         => 0,
                'Descuento_Comercial'  => 0,
            ];
        }

        return [
            'Descuento_Especial'  => (float)($fact_cliente['Descuento_Especial']  ?? 0),
            'Descuento_PP'        => (float)($fact_cliente['Descuento_PP']        ?? 0),
            'Descuento_Comercial' => (float)($fact_cliente['Descuento_Comercial'] ?? 0),
        ];
    }

    /**
     * Obtener tarifa del cliente
     */
    public function getTarifa(int|string $fact_id_cliente): int {
        $fact_cliente = $this->find($fact_id_cliente);
        return $fact_cliente ? (int)($fact_cliente['Tarifa'] ?? 1) : 1;
    }

    /**
     * Obtener zona del cliente
     */
    public function getZona(int|string $fact_id_cliente): ?string {
        $fact_cliente = $this->find($fact_id_cliente);
        return $fact_cliente['Id_Zona'] ?? null;
    }
}
