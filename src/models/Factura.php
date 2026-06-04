<?php
/**
 * Modelo Factura - Acceso a datos de facturas
 * Módulo de Facturación
 */

class Factura
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function find(string $codigo): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM `Facturas_Clientes` WHERE `Codigo` = ?",
            [$codigo]
        );
    }

    public function findWithLines(string $codigo): ?array
    {
        $factura = $this->db->fetch(
            "SELECT * FROM `Facturas_Clientes` WHERE `Codigo` = ?",
            [$codigo]
        );
        if (!$factura) return null;

        $lineas = $this->db->fetchAll(
            "SELECT
                L.`Id_Factura`,
                L.`Linea`,
                L.`Id_Articulo`,
                L.`Descripcion`,
                L.`Cantidad`,
                L.`Precio`,
                L.`Descuento`,
                L.`Total`,
                L.`Id_Tipo_IVA`,
                L.`Importe_Bruto`,
                L.`Importe_Descuento`,
                L.`Base_Imponible`,
                L.`RE`,
                L.`Aplica_RE`,
                L.`Id_Ticket`,
                L.`Id_Canal`,
                L.`Id_Almacen`,
                L.`Calificacion`,
                L.`Clave_Regimen`,
                T.`IVA`             AS tipo_iva_pct,
                T.`RE`              AS tipo_re_pct,
                T.`Tipo_Territorio` AS tipo_territorio,
                T.`Codigo_Verifactu` AS codigo_verifactu
             FROM `Lineas_Facturas_Clientes` L
             LEFT JOIN `Tipos_IVA` T ON L.`Id_Tipo_IVA` = T.`Codigo`
             WHERE L.`Id_Factura` = ?
             ORDER BY L.`Linea`",
            [$codigo]
        );

        $factura['lineas'] = $lineas;

        // Para recapitulativas: cargar las facturas simplificadas que sustituye
        if (($factura['Tipo_Documento'] ?? '') === 'RECAPITULATIVA') {
            $factura['facturas_sustituidas'] = $this->db->fetchAll(
                "SELECT FS.`Id_Simplificada` AS codigo, F.`Fecha`
                 FROM `Facturas_Sustituidas` FS
                 JOIN `Facturas_Clientes` F ON F.`Codigo` = FS.`Id_Simplificada`
                 WHERE FS.`Id_Recapitulativa` = ?
                 ORDER BY F.`Fecha`, FS.`Id_Simplificada`",
                [$codigo]
            );
        }

        return $factura;
    }

    public function getLineas(string $codigo): array
    {
        return $this->db->fetchAll(
            "SELECT L.*, T.`IVA` AS tipo_iva_pct, T.`Codigo_Verifactu` AS codigo_verifactu,
                    T.`Tipo_Territorio` AS tipo_territorio, T.`RE` AS tipo_re_pct
             FROM `Lineas_Facturas_Clientes` L
             LEFT JOIN `Tipos_IVA` T ON L.`Id_Tipo_IVA` = T.`Codigo`
             WHERE L.`Id_Factura` = ?
             ORDER BY L.`Linea`",
            [$codigo]
        );
    }

    /**
     * Devuelve facturas SIMPLIFICADA pendientes de recapitular (Recapitulada = 'N').
     */
    public function findSimplificadasPendientes(
        ?string $idCanal = null,
        ?int $anio = null,
        ?int $mes = null,
        bool $soloEnVerifactu = false
    ): array {
        $where  = "F.`Tipo_Documento` = 'SIMPLIFICADA' AND IFNULL(F.`Recapitulada`, 'N') = 'N'";
        $params = [];

        if ($idCanal !== null && $idCanal !== '') {
            $where   .= ' AND F.`Id_Canal` = ?';
            $params[] = $idCanal;
        }
        if ($anio !== null) {
            $where   .= ' AND YEAR(F.`Fecha`) = ?';
            $params[] = $anio;
        }
        if ($mes !== null) {
            $where   .= ' AND MONTH(F.`Fecha`) = ?';
            $params[] = $mes;
        }

        if ($soloEnVerifactu) {
            $verifactuWhere  = "F.`Tipo_Documento` = 'SIMPLIFICADA'"
                             . " AND F.`Codigo` NOT IN (SELECT `Id_Simplificada` FROM `Facturas_Sustituidas`)";
            $verifactuParams = [];

            if ($idCanal !== null && $idCanal !== '') {
                $verifactuWhere   .= ' AND F.`Id_Canal` = ?';
                $verifactuParams[] = $idCanal;
            }
            if ($anio !== null) {
                $verifactuWhere   .= ' AND YEAR(F.`Fecha`) = ?';
                $verifactuParams[] = $anio;
            }
            if ($mes !== null) {
                $verifactuWhere   .= ' AND MONTH(F.`Fecha`) = ?';
                $verifactuParams[] = $mes;
            }

            return $this->db->fetchAll(
                "SELECT F.`Codigo`, F.`Numero`, F.`Fecha`, F.`Id_Canal`, F.`Id_Cliente`,
                        F.`Total`, F.`Cerrada`, C.`Nombre` AS Nombre,
                        VR.`Estado_Envio`, VR.`CSV_Hacienda`
                 FROM `Facturas_Clientes` F
                 LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
                 INNER JOIN `Verifactu_Registros` VR ON VR.`Id_Documento` = F.`Codigo`
                     AND VR.`Tipo_Origen` = 'FACTURA'
                     AND VR.`CSV_Hacienda` IS NOT NULL
                 WHERE {$verifactuWhere}
                 ORDER BY F.`Fecha`, F.`Codigo`",
                $verifactuParams
            );
        }

        return $this->db->fetchAll(
            "SELECT F.`Codigo`, F.`Numero`, F.`Fecha`, F.`Id_Canal`, F.`Id_Cliente`,
                    F.`Total`, F.`Cerrada`, C.`Nombre` AS Nombre
             FROM `Facturas_Clientes` F
             LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
             WHERE {$where}
             ORDER BY F.`Fecha`, F.`Codigo`",
            $params
        );
    }

    public function create(array $data): string
    {
        $this->db->beginTransaction();

        try {
            $lineas = $data['lineas'] ?? [];
            unset($data['lineas']);

            $data['Fecha_Alta']           = date('Y-m-d H:i:s');
            $data['Usuario_Alta']         = $_SESSION['usuario'] ?? 'sistema';
            $data['Ultima_Modificacion']  = date('Y-m-d H:i:s');

            $codigo = $this->db->insert('Facturas_Clientes', $data);

            if (!empty($lineas)) {
                foreach ($lineas as $linea) {
                    $linea['Id_Factura'] = $codigo;
                    $this->db->insert('Lineas_Facturas_Clientes', $linea);
                }
            }

            $this->db->commit();
            return $codigo;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(string $codigo, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            $data['Ultima_Modificacion']         = date('Y-m-d H:i:s');
            $data['Usuario_Ultima_Modificacion'] = $_SESSION['usuario'] ?? 'sistema';

            $this->db->update(
                'Facturas_Clientes',
                $data,
                '`Codigo` = ?',
                [$codigo]
            );

            $this->db->delete(
                'Lineas_Facturas_Clientes',
                '`Id_Factura` = ?',
                [$codigo]
            );

            if (!empty($data['lineas'])) {
                foreach ($data['lineas'] as $linea) {
                    $linea['Id_Factura'] = $codigo;
                    $this->db->insert('Lineas_Facturas_Clientes', $linea);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(string $codigo): bool
    {
        return $this->db->update(
            'Facturas_Clientes',
            [
                'Cerrada'            => 'S',
                'Ultima_Modificacion' => date('Y-m-d H:i:s'),
            ],
            '`Codigo` = ?',
            [$codigo]
        ) > 0;
    }

    /**
     * Obtener todas las facturas sin paginación (para exportación)
     */
    public function getAll(array $filtros = []): array
    {
        $whereParts = ["1=1"];
        $params     = [];

        if (!empty($filtros['tipo_documento'])) {
            if ($filtros['tipo_documento'] === 'BORRADOR') {
                $whereParts[] = "F.`Cerrada` = 'N'";
            } else {
                $whereParts[] = "F.`Tipo_Documento` = ?";
                $params[]     = $filtros['tipo_documento'];
            }
        }

        if (!empty($filtros['id_cliente'])) {
            $whereParts[] = "F.`Id_Cliente` = ?";
            $params[]     = $filtros['id_cliente'];
        }

        if (!empty($filtros['Nombre'])) {
            $whereParts[] = "C.`Nombre` = ?";
            $params[]     = $filtros['Nombre'];
        }

        if (!empty($filtros['codigo'])) {
            $whereParts[] = "F.`Codigo` LIKE ?";
            $params[]     = '%' . $filtros['codigo'] . '%';
        }

        if (!empty($filtros['id_canal'])) {
            $whereParts[] = "F.`Id_Canal` = ?";
            $params[]     = $filtros['id_canal'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereParts[] = "DATE(F.`Fecha`) >= ?";
            $params[]     = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $whereParts[] = "DATE(F.`Fecha`) <= ?";
            $params[]     = $filtros['fecha_hasta'];
        }

        if (isset($filtros['cobrado']) && $filtros['cobrado'] !== '') {
            $whereParts[] = "F.`Cobrada` = ?";
            $params[]     = $filtros['cobrado'];
        }

        $where = implode(' AND ', $whereParts);

        $sql = "SELECT
                    F.`Codigo`, F.`Id_Canal`,
                    F.`Numero`, F.`Fecha`,
                    F.`Id_Cliente`, F.`Id_Forma_Pago`,
                    F.`Observaciones`, F.`Descuento_Especial`,
                    F.`Descuento_PP`, F.`Descuento_Comercial`,
                    F.`Importe_Bruto`, F.`Importe_Dto_Especial`,
                    F.`Importe_Dto_PP`, F.`Total`,
                    F.`Direccion`, F.`Cobrada`,
                    F.`Fecha_Cobro`, F.`Abono`
                FROM `Facturas_Clientes` F
                LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
                WHERE {$where}
                ORDER BY F.`Fecha_Alta` DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Listar facturas con paginación
     */
    public function paginate(int $page = 1, int $perPage = 25, array $filtros = []): array
    {
        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;

        $whereParts = ["1=1"];
        $params     = [];

        if (!empty($filtros['tipo_documento'])) {
            if ($filtros['tipo_documento'] === 'BORRADOR') {
                $whereParts[] = "F.`Cerrada` = 'N'";
            } else {
                $whereParts[] = "F.`Tipo_Documento` = ?";
                $params[]     = $filtros['tipo_documento'];
            }
        }

        if (!empty($filtros['id_cliente'])) {
            $whereParts[] = "F.`Id_Cliente` = ?";
            $params[]     = $filtros['id_cliente'];
        }

        if (!empty($filtros['Nombre'])) {
            $whereParts[] = "C.`Nombre` = ?";
            $params[]     = $filtros['Nombre'];
        }

        if (!empty($filtros['codigo'])) {
            $whereParts[] = "F.`Codigo` LIKE ?";
            $params[]     = '%' . $filtros['codigo'] . '%';
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereParts[] = "DATE(F.`Fecha`) >= ?";
            $params[]     = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $whereParts[] = "DATE(F.`Fecha`) <= ?";
            $params[]     = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['id_canal'])) {
            $whereParts[] = "F.`Id_Canal` = ?";
            $params[]     = $filtros['id_canal'];
        }

        if (isset($filtros['cobrado']) && $filtros['cobrado'] !== '') {
            $whereParts[] = "F.`Cobrada` = ?";
            $params[]     = $filtros['cobrado'];
        }

        $where = implode(' AND ', $whereParts);

        $total = (int)$this->db->fetchCell(
            "SELECT COUNT(*)
             FROM `Facturas_Clientes` F
             WHERE {$where}",
            $params
        );

        $sql = "SELECT
                    F.`Codigo`         AS codigo,
                    F.`Numero`         AS numero,
                    F.`Fecha`          AS fecha,
                    F.`Id_Canal`       AS id_canal,
                    F.`Tipo_Documento` AS tipo_documento,
                    F.`Total`          AS total,
                    F.`Id_Cliente`     AS id_cliente,
                    C.`Nombre`  AS nombre_cliente,
                    F.`Cobrada`        AS cobrada,
                    CASE
                        WHEN F.`Abono`   = 'S' THEN 'ANULADA'
                        WHEN F.`Cobrada` = 'S' THEN 'PAGADA'
                        WHEN F.`Cerrada` = 'S' THEN 'EMITIDA'
                        ELSE 'BORRADOR'
                    END AS estado,
                    CASE COALESCE(
                        (SELECT VR2.`Estado_Envio`
                         FROM `Verifactu_Registros` VR2
                         WHERE VR2.`Id_Documento` = F.`Codigo`
                           AND VR2.`Tipo_Origen`  = 'FACTURA'
                         ORDER BY VR2.`Fecha_Generacion` DESC
                         LIMIT 1),
                        'NINGUNO')
                        WHEN 'ENVIADO'   THEN 'ENVIADA'
                        WHEN 'GENERADO'  THEN 'PENDIENTE'
                        WHEN 'PENDIENTE' THEN 'PENDIENTE'
                        WHEN 'ERROR'     THEN 'ERROR'
                        WHEN 'ANULADO'   THEN 'RECHAZADA'
                        ELSE 'NO_ENVIADA'
                    END AS estado_verifactu
                FROM `Facturas_Clientes` F
                LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
                WHERE {$where}
                ORDER BY F.`Fecha_Alta` DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function search(string $query, int $limit = 20): array
    {
        $limit      = max(1, (int)$limit);
        $searchTerm = "%{$query}%";

        $sql = "SELECT
                    F.`Codigo`, F.`Fecha`, F.`Total`, F.`Tipo_Documento`, F.`Cerrada`,
                    C.`NIF`, C.`Nombre` AS Nombre
                FROM `Facturas_Clientes` F
                LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
                WHERE F.`Codigo` LIKE ?
                   OR C.`NIF`    LIKE ?
                ORDER BY F.`Fecha` DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }

    public function findByCodigo(string $query, int $limit = 20): array
    {
        $limit      = max(1, (int)$limit);
        $searchTerm = "%{$query}%";

        return $this->db->fetchAll(
            "SELECT `Codigo`
             FROM `Facturas_Clientes`
             WHERE `Codigo` LIKE ?
             LIMIT {$limit}",
            [$searchTerm]
        );
    }

    public function origen(string $idCliente, int $ejercicio, int $limit = 200): array
    {
        $limit = max(1, (int)$limit);

        return $this->db->fetchAll(
            "SELECT `Codigo`, `Numero`, `Fecha`, `Total`, `Id_Canal`, `Id_Cliente`, `Tipo_Documento`
             FROM `Facturas_Clientes`
             WHERE `Id_Cliente` = ?
               AND LEFT(`Codigo`, 4) = ?
               AND IFNULL(`Abono`, 'N') = 'N'
             ORDER BY `Fecha` DESC, `Numero` DESC
             LIMIT {$limit}",
            [$idCliente, (string)$ejercicio]
        );
    }

    public function findByCliente(int|string $idCliente): array
    {
        return $this->db->fetchAll(
            "SELECT `Codigo`, `Fecha`, `Total`, `Tipo_Documento`, `Cerrada`, `Cobrada`
             FROM `Facturas_Clientes`
             WHERE `Id_Cliente` = ?
             ORDER BY `Fecha` DESC",
            [$idCliente]
        );
    }

    public function cerrar(string $codigo): bool
    {
        return $this->db->update(
            'Facturas_Clientes',
            [
                'Cerrada'            => 'S',
                'Fecha_Cierre'       => date('Y-m-d'),
                'Ultima_Modificacion' => date('Y-m-d H:i:s'),
            ],
            '`Codigo` = ?',
            [$codigo]
        ) > 0;
    }

    public function getEstadisticas(?int $ejercicio = null): array
    {
        $ejercicio = $ejercicio ?? (int)date('Y');

        $sql = "SELECT
                    COUNT(*) AS total_facturas,
                    SUM(`Total`) AS importe_total,
                    SUM(CASE WHEN `Tipo_Documento` = 'RECTIFICATIVA' THEN 1 ELSE 0 END) AS total_rectificativas,
                    SUM(CASE WHEN `Cerrada`  = 'S' THEN 1 ELSE 0 END) AS total_cerradas,
                    SUM(CASE WHEN `Cobrada`  = 'S' THEN 1 ELSE 0 END) AS total_cobradas
                FROM `Facturas_Clientes`
                WHERE LEFT(`Codigo`, 4) = ?";

        $result = $this->db->fetch($sql, [(string)$ejercicio]);

        return [
            'ejercicio'             => $ejercicio,
            'total_facturas'        => (int)($result['total_facturas']        ?? 0),
            'importe_total'         => (float)($result['importe_total']        ?? 0),
            'total_rectificativas'  => (int)($result['total_rectificativas']   ?? 0),
            'total_cerradas'        => (int)($result['total_cerradas']         ?? 0),
            'total_cobradas'        => (int)($result['total_cobradas']         ?? 0),
        ];
    }

    public function getSiguienteNumero(string $idCanal, int $ejercicio, string $tipoDocumento): int
    {
        $result = $this->db->fetch(
            "SELECT MAX(`Numero`) AS MaxNumero
             FROM `Facturas_Clientes`
             WHERE `Id_Canal` = ? AND LEFT(`Codigo`, 4) = ?",
            [$idCanal, (string)$ejercicio]
        );

        return $result ? ((int)$result['MaxNumero'] + 1) : 1;
    }
}
