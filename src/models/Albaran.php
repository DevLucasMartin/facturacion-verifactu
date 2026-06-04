<?php
/**
 * Modelo Albaran - Acceso a datos de albaranes de cliente
 * Módulo de Facturación
 */

class Albaran
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function find(string $codigo): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM `Albaranes_Clientes` WHERE `Codigo` = ?",
            [$codigo]
        );
    }

    public function findWithLines(string $codigo): ?array
    {
        $albaran = $this->find($codigo);
        if (!$albaran) return null;

        $albaran['lineas'] = $this->getLineas($codigo);
        return $albaran;
    }

    public function getLineas(string $codigo): array
    {
        return $this->db->fetchAll(
            "SELECT L.*, T.`IVA` AS tipo_iva_pct, T.`RE` AS tipo_re_pct,
                    T.`Tipo_Territorio` AS tipo_territorio, T.`Codigo_Verifactu` AS codigo_verifactu
             FROM `Lineas_Albaranes_Clientes` L
             LEFT JOIN `Tipos_IVA` T ON L.`Id_Tipo_IVA` = T.`Codigo`
             WHERE L.`Id_Albaran` = ?
             ORDER BY L.`Linea`",
            [$codigo]
        );
    }

    public function create(array $data): string
    {
        $this->db->beginTransaction();

        try {
            $lineas = $data['lineas'] ?? [];
            unset($data['lineas']);

            $data['Fecha_Alta']                  = date('Y-m-d H:i:s');
            $data['Usuario_Alta']                = $_SESSION['usuario'] ?? 'sistema';
            $data['Ultima_Modificacion']         = date('Y-m-d H:i:s');
            $data['Usuario_Ultima_Modificacion'] = $_SESSION['usuario'] ?? 'sistema';

            $codigo = $this->db->insert('Albaranes_Clientes', $data);

            if (!empty($lineas)) {
                foreach ($lineas as $linea) {
                    $linea['Id_Albaran'] = $codigo;
                    $this->db->insert('Lineas_Albaranes_Clientes', $linea);
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
            $lineas = $data['lineas'] ?? [];
            unset($data['lineas']);

            $data['Ultima_Modificacion']         = date('Y-m-d H:i:s');
            $data['Usuario_Ultima_Modificacion'] = $_SESSION['usuario'] ?? 'sistema';

            $this->db->update(
                'Albaranes_Clientes',
                $data,
                '`Codigo` = ?',
                [$codigo]
            );

            $this->db->delete(
                'Lineas_Albaranes_Clientes',
                '`Id_Albaran` = ?',
                [$codigo]
            );

            if (!empty($lineas)) {
                foreach ($lineas as $linea) {
                    $linea['Id_Albaran'] = $codigo;
                    $this->db->insert('Lineas_Albaranes_Clientes', $linea);
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
        $this->db->delete('Lineas_Albaranes_Clientes', '`Id_Albaran` = ?', [$codigo]);

        return $this->db->delete(
            'Albaranes_Clientes',
            '`Codigo` = ?',
            [$codigo]
        ) > 0;
    }

    /**
     * Obtener todos los albaranes sin paginación (para exportación)
     */
    public function getAll(array $filtros = []): array
    {
        $whereParts = ["1=1"];
        $params     = [];

        if (!empty($filtros['id_cliente'])) {
            $whereParts[] = "A.`Id_Cliente` = ?";
            $params[]     = $filtros['id_cliente'];
        }

        if (!empty($filtros['Nombre'])) {
            $whereParts[] = "C.`Nombre` = ?";
            $params[]     = $filtros['Nombre'];
        }

        if (!empty($filtros['codigo'])) {
            $whereParts[] = "A.`Codigo` LIKE ?";
            $params[]     = '%' . $filtros['codigo'] . '%';
        }

        if (!empty($filtros['id_canal'])) {
            $whereParts[] = "A.`Id_Canal` = ?";
            $params[]     = $filtros['id_canal'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereParts[] = "DATE(A.`Fecha`) >= ?";
            $params[]     = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $whereParts[] = "DATE(A.`Fecha`) <= ?";
            $params[]     = $filtros['fecha_hasta'];
        }

        $where = implode(' AND ', $whereParts);

        $sql = "SELECT
                    A.`Codigo`, A.`Id_Canal`,
                    A.`Numero`, A.`Fecha`,
                    A.`Id_Cliente`, A.`Id_Forma_Pago`,
                    A.`Observaciones`, A.`Fecha_Alta`,
                    A.`Ultima_Modificacion`, A.`Usuario_Alta`,
                    A.`Usuario_Ultima_Modificacion`,
                    A.`Importe_Bruto`, A.`Base_Imponible`,
                    A.`Cerrado`, A.`Facturado`,
                    A.`Direccion`, A.`Cobrado`,
                    A.`Fecha_Cobro`
                FROM `Albaranes_Clientes` A
                LEFT JOIN `Clientes` C ON C.`Codigo` = A.`Id_Cliente`
                WHERE {$where}
                ORDER BY A.`Fecha` DESC, A.`Codigo` DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Listar albaranes con paginación
     */
    public function paginate(int $page = 1, int $perPage = 25, array $filtros = []): array
    {
        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;

        $whereParts = ['1=1'];
        $params     = [];

        if (!empty($filtros['id_cliente'])) {
            $whereParts[] = 'A.`Id_Cliente` = ?';
            $params[]     = $filtros['id_cliente'];
        }

        if (!empty($filtros['codigo'])) {
            $whereParts[] = "A.`Codigo` LIKE ?";
            $params[]     = '%' . $filtros['codigo'] . '%';
        }

        if (!empty($filtros['Nombre'])) {
            $whereParts[] = 'C.`Nombre` = ?';
            $params[]     = $filtros['Nombre'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $whereParts[] = 'DATE(A.`Fecha`) >= ?';
            $params[]     = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $whereParts[] = 'DATE(A.`Fecha`) <= ?';
            $params[]     = $filtros['fecha_hasta'];
        }

        if (!empty($filtros['id_canal'])) {
            $whereParts[] = 'A.`Id_Canal` = ?';
            $params[]     = $filtros['id_canal'];
        }

        if (!empty($filtros['facturado'])) {
            $whereParts[] = 'A.`Facturado` = ?';
            $params[]     = $filtros['facturado'];
        }

        if (!empty($filtros['cerrado'])) {
            $whereParts[] = 'A.`Cerrado` = ?';
            $params[]     = $filtros['cerrado'];
        }

        $where = implode(' AND ', $whereParts);

        $total = (int)$this->db->fetchCell(
            "SELECT COUNT(*)
             FROM `Albaranes_Clientes` A
             WHERE {$where}",
            $params
        );

        $sql = "SELECT
                    A.`Codigo`, A.`Numero`, A.`Fecha`,
                    A.`Id_Cliente`, A.`Id_Canal`,
                    A.`Total`, A.`Cerrado`, A.`Facturado`,
                    C.`NIF`, C.`Nombre` AS `Nombre`
                FROM `Albaranes_Clientes` A
                LEFT JOIN `Clientes` C ON C.`Codigo` = A.`Id_Cliente`
                WHERE {$where}
                ORDER BY A.`Fecha_Alta` DESC
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
                    A.`Codigo`, A.`Fecha`, A.`Total`, A.`Cerrado`, A.`Facturado`,
                    C.`NIF`, C.`Nombre` AS `Nombre`
                FROM `Albaranes_Clientes` A
                LEFT JOIN `Clientes` C ON C.`Codigo` = A.`Id_Cliente`
                WHERE A.`Codigo` LIKE ?
                   OR C.`NIF`    LIKE ?
                ORDER BY A.`Fecha` DESC
                LIMIT {$limit}";

        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }

    public function findByCodigo(string $query, int $limit = 20): array
    {
        $limit      = max(1, (int)$limit);
        $searchTerm = "%{$query}%";

        return $this->db->fetchAll(
            "SELECT `Codigo`
             FROM `Albaranes_Clientes`
             WHERE `Codigo` LIKE ?
             LIMIT {$limit}",
            [$searchTerm]
        );
    }

    public function cerrar(string $codigo): bool
    {
        return $this->db->update(
            'Albaranes_Clientes',
            [
                'Cerrado'             => 'S',
                'Fecha_Cierre'        => date('Y-m-d'),
                'Ultima_Modificacion' => date('Y-m-d H:i:s'),
            ],
            '`Codigo` = ?',
            [$codigo]
        ) > 0;
    }

    public function marcarFacturado(string $codigo): bool
    {
        return $this->db->update(
            'Albaranes_Clientes',
            [
                'Facturado'           => 'S',
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
                    COUNT(*) AS total_albaranes,
                    SUM(`Total`) AS importe_total,
                    SUM(CASE WHEN `Cerrado`   = 'S' THEN 1 ELSE 0 END) AS total_cerrados,
                    SUM(CASE WHEN `Facturado` = 'S' THEN 1 ELSE 0 END) AS total_facturados
                FROM `Albaranes_Clientes`
                WHERE LEFT(`Codigo`, 4) = ?";

        $result = $this->db->fetch($sql, [(string)$ejercicio]);

        return [
            'ejercicio'        => $ejercicio,
            'total_albaranes'  => (int)($result['total_albaranes']  ?? 0),
            'importe_total'    => (float)($result['importe_total']   ?? 0),
            'total_cerrados'   => (int)($result['total_cerrados']    ?? 0),
            'total_facturados' => (int)($result['total_facturados']  ?? 0),
        ];
    }

    public function getPlantillas(): array
    {
        return $this->db->fetchAll(
            "SELECT A.`Codigo`, A.`Nombre_Plantilla`, A.`Id_Canal`, A.`Id_Cliente`,
                    A.`Fecha`, A.`Id_Forma_Pago`, C.`Nombre` AS NombreCliente
             FROM `Albaranes_Clientes` A
             LEFT JOIN `Clientes` C ON C.`Codigo` = A.`Id_Cliente`
             WHERE A.`Es_Plantilla` = 'S'
             ORDER BY A.`Nombre_Plantilla`",
            []
        );
    }

    public function marcarComoPlantilla(string $codigo, string $nombre): bool
    {
        return $this->db->update(
            'Albaranes_Clientes',
            ['Es_Plantilla' => 'S', 'Nombre_Plantilla' => $nombre],
            '`Codigo` = ?',
            [$codigo]
        );
    }

    public function eliminarPlantilla(string $codigo): bool
    {
        return $this->db->update(
            'Albaranes_Clientes',
            ['Es_Plantilla' => 'N', 'Nombre_Plantilla' => null],
            '`Codigo` = ?',
            [$codigo]
        );
    }

    public function renombrarPlantilla(string $codigo, string $nombre): bool
    {
        return $this->db->update(
            'Albaranes_Clientes',
            ['Nombre_Plantilla' => $nombre],
            '`Codigo` = ?',
            [$codigo]
        );
    }

    public function getSiguienteNumero(string $idCanal, int $ejercicio): int
    {
        $result = $this->db->fetch(
            "SELECT MAX(`Numero`) AS MaxNumero
             FROM `Albaranes_Clientes`
             WHERE `Id_Canal` = ? AND LEFT(`Codigo`, 4) = ?",
            [$idCanal, (string)$ejercicio]
        );

        return $result ? ((int)$result['MaxNumero'] + 1) : 1;
    }
}
