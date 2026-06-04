<?php
/**
 * Modelo VerifactuRegistro - Acceso a registros de Verifactu
 * Módulo de Facturación
 */

class VerifactuRegistro {
    private Database $db;

    // Estados
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_GENERADO  = 'GENERADO';
    public const ESTADO_ENVIADO   = 'ENVIADO';
    public const ESTADO_ERROR     = 'ERROR';
    public const ESTADO_ANULADO   = 'ANULADO';

    // Tipos de origen
    public const TIPO_FACTURA = 'FACTURA';
    public const TIPO_TICKET  = 'TICKET';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Verifactu_Registros` WHERE `Id` = ?",
            [$id]
        );
    }

    public function findByDocumento(string $tipoOrigen, string $idDocumento): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Verifactu_Registros`
             WHERE `Tipo_Origen` = ? AND `Id_Documento` = ?",
            [$tipoOrigen, $idDocumento]
        );
    }

    public function create(array $data): int|string {
        $data['Fecha_Generacion'] = date('Y-m-d H:i:s');
        $data['Estado_Envio']     = $data['Estado_Envio'] ?? self::ESTADO_PENDIENTE;
        $data['Reintentos']       = $data['Reintentos']   ?? 0;

        return $this->db->insert('Verifactu_Registros', $data);
    }

    public function update(int $id, array $data): bool {
        return $this->db->update(
            'Verifactu_Registros',
            $data,
            '`Id` = ?',
            [$id]
        ) > 0;
    }

    public function actualizarEnvio(int $id, array $data): bool {
        return $this->update($id, $data);
    }

    public function getPorEstado(string $estado): array {
        return $this->db->fetchAll(
            "SELECT VR.*, F.`Fecha` AS Factura_Fecha, F.`Total` AS Factura_Total,
                    C.`NIF` AS Cliente_NIF, C.`Nombre` AS Cliente_Nombre
             FROM `Verifactu_Registros` VR
             LEFT JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
             LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
             WHERE VR.`Estado_Envio` = ?
             ORDER BY VR.`Fecha_Generacion` DESC",
            [$estado]
        );
    }

    public function paginate(int $page = 1, int $perPage = 25, array $filtros = []): array {
        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;

        $condicionesWhere = ["1=1"];
        $params           = [];

        if (!empty($filtros['estado'])) {
            $condicionesWhere[] = "VR.`Estado_Envio` = ?";
            $params[]           = $filtros['estado'];
        }

        if (!empty($filtros['tipo_documento'])) {
            $condicionesWhere[] = "F.`Tipo_Documento` = ?";
            $params[]           = $filtros['tipo_documento'];
        } elseif (!empty($filtros['tipo_origen'])) {
            $condicionesWhere[] = "VR.`Tipo_Origen` = ?";
            $params[]           = $filtros['tipo_origen'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $condicionesWhere[] = "VR.`Fecha_Generacion` >= ?";
            $params[]           = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $condicionesWhere[] = "VR.`Fecha_Generacion` <= ?";
            $params[]           = $filtros['fecha_hasta'];
        }

        $where = implode(' AND ', $condicionesWhere);

        $total = (int)$this->db->fetchCell(
            "SELECT COUNT(*)
             FROM `Verifactu_Registros` VR
             LEFT JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
             WHERE {$where}",
            $params
        );

        $sql = "SELECT VR.*, F.`Fecha` AS Factura_Fecha, F.`Total` AS Factura_Total,
                       F.`Tipo_Documento` AS Tipo_Documento,
                       C.`NIF` AS Cliente_NIF, C.`Nombre` AS Cliente_Nombre
                FROM `Verifactu_Registros` VR
                LEFT JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
                LEFT JOIN `Clientes` C ON C.`Codigo` = F.`Id_Cliente`
                WHERE {$where}
                ORDER BY VR.`Fecha_Generacion` DESC
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

    public function getEstadisticas(): array {
        $result = $this->db->fetchAll(
            "SELECT `Estado_Envio`,
                    COUNT(*) AS Total,
                    SUM(CASE WHEN `Reintentos` > 0 THEN 1 ELSE 0 END) AS Con_Reintentos
             FROM `Verifactu_Registros`
             GROUP BY `Estado_Envio`"
        );

        $stats = [
            'total'      => 0,
            'pendientes' => 0,
            'generados'  => 0,
            'enviados'   => 0,
            'errores'    => 0,
            'anulados'   => 0,
        ];

        foreach ($result as $fila) {
            $estado  = $fila['Estado_Envio'];
            $conteo  = (int)$fila['Total'];
            $stats['total'] += $conteo;

            switch ($estado) {
                case self::ESTADO_PENDIENTE: $stats['pendientes'] = $conteo; break;
                case self::ESTADO_GENERADO:  $stats['generados']  = $conteo; break;
                case self::ESTADO_ENVIADO:   $stats['enviados']   = $conteo; break;
                case self::ESTADO_ERROR:     $stats['errores']    = $conteo; break;
                case self::ESTADO_ANULADO:   $stats['anulados']   = $conteo; break;
            }
        }

        return $stats;
    }

    public function incrementarReintentos(int $id): bool {
        $this->db->query(
            "UPDATE `Verifactu_Registros`
             SET `Reintentos` = `Reintentos` + 1
             WHERE `Id` = ?",
            [$id]
        );
        return true;
    }

    public function marcarError(int $id, string $mensaje, ?string $rawResponse = null): bool {
        $fields = [
            'Estado_Envio' => self::ESTADO_ERROR,
            'Ultimo_Error' => substr($mensaje, 0, 500),
        ];
        if ($rawResponse !== null) {
            $fields['Respuesta_Hacienda'] = substr($rawResponse, 0, 4000);
        }
        return $this->update($id, $fields);
    }

    public function marcarEnviado(int $id, string $respuestaHacienda, ?string $csv = null, ?string $urlVerificacion = null): bool {
        $fields = [
            'Estado_Envio'       => self::ESTADO_ENVIADO,
            'Fecha_Envio'        => date('Y-m-d H:i:s'),
            'Respuesta_Hacienda' => $respuestaHacienda,
            'CSV_Hacienda'       => $csv,
        ];
        if ($urlVerificacion !== null) {
            $fields['URL_Verificacion'] = $urlVerificacion;
        }
        return $this->update($id, $fields);
    }

    public function marcarGenerado(int $id): bool {
        return $this->update($id, [
            'Estado_Envio' => self::ESTADO_GENERADO,
        ]);
    }

    public function delete(int $id): bool {
        return $this->db->delete(
            'Verifactu_Registros',
            '`Id` = ?',
            [$id]
        ) > 0;
    }

    public function existe(string $tipoOrigen, string $idDocumento): bool {
        return $this->findByDocumento($tipoOrigen, $idDocumento) !== null;
    }

    public function getUltimo(string $tipoOrigen, string $idCanal): ?array {
        return $this->db->fetch(
            "SELECT VR.*
             FROM `Verifactu_Registros` VR
             JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
             WHERE VR.`Tipo_Origen` = ? AND F.`Id_Canal` = ?
             ORDER BY VR.`Fecha_Generacion` DESC
             LIMIT 1",
            [$tipoOrigen, $idCanal]
        );
    }

    /**
     * Devuelve el último registro global para encadenamiento de facturas
     * a nivel de emisor, tal como exige la especificación AEAT Verifactu.
     */
    public function getUltimoGlobal(string $tipoOrigen): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Verifactu_Registros`
             WHERE `Tipo_Origen` = ?
             ORDER BY `Fecha_Generacion` DESC
             LIMIT 1",
            [$tipoOrigen]
        );
    }

    /**
     * Devuelve el registro cuya Huella_Actual coincide, junto con la fecha del documento.
     * Necesario para construir EncadenamientoFacturaAnterior en el XML de AEAT.
     */
    public function findByHuellaActual(string $huella): ?array {
        return $this->db->fetch(
            "SELECT VR.*, F.`Fecha` AS Doc_Fecha
             FROM `Verifactu_Registros` VR
             LEFT JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
             WHERE VR.`Huella_Actual` = ?",
            [$huella]
        );
    }

    /**
     * Fallback: devuelve el registro inmediatamente anterior al dado (por Id).
     */
    public function findPreviousByRegistroId(int $currentId): ?array {
        return $this->db->fetch(
            "SELECT VR.*, F.`Fecha` AS Doc_Fecha
             FROM `Verifactu_Registros` VR
             LEFT JOIN `Facturas_Clientes` F ON F.`Codigo` = VR.`Id_Documento`
             WHERE VR.`Id` < ?
             ORDER BY VR.`Id` DESC
             LIMIT 1",
            [$currentId]
        );
    }
}
