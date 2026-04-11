<?php
/**
 * Modelo Articulo - Acceso a datos de artículos
 * Módulo de Facturación
 */

class Articulo {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Buscar artículo por código o modelo
     */
    public function find(int|string $param): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Articulos` WHERE `Codigo` = ? OR `Modelo` = ?",
            [$param, $param]
        );
    }

    /**
     * Buscar artículo por código de barras
     */
    public function findByBarCode(string $codigoBarras): ?array {
        return $this->db->fetch(
            "SELECT * FROM `Articulos` WHERE `Codigo_Barras` = ?",
            [$codigoBarras]
        );
    }

    /**
     * Buscar artículos por texto (código, descripción, código de barras)
     */
    public function search(string $query, int $tarifa = 1, int $limit = 20): array {
        $limit      = max(1, (int)$limit);
        $searchTerm = "%{$query}%";

        $precioField = match($tarifa) {
            1 => '`Precio_Venta_1`',
            2 => '`Precio_Venta_2`',
            3 => '`Precio_Venta_3`',
            4 => '`Precio_Venta_4`',
            5 => '`Precio_Venta_5`',
            6 => '`Precio_Venta_6`',
            7 => '`Precio_Venta_7`',
            8 => '`Precio_Venta_8`',
            default => '`Precio_Venta_1`',
        };

        return $this->db->fetchAll(
            "SELECT
                    `Codigo`, `Descripcion`, `Codigo_Barras`, `Id_Tipo_IVA`,
                    `Id_Familia`, `Id_Marca`, `Stock_Minimo`,
                    {$precioField} AS `Precio`,
                    `Descuento`
             FROM `Articulos`
             WHERE (`Codigo`        LIKE ?
                 OR `Descripcion`   LIKE ?
                 OR `Codigo_Barras` LIKE ?)
               AND `Activo` = 'S'
             ORDER BY `Descripcion`
             LIMIT {$limit}",
            [$searchTerm, $searchTerm, $searchTerm]
        );
    }

    /**
     * Obtener precio de venta según tarifa del cliente
     */
    public function getPrecio(int|string $idArticulo, int $tarifa = 1): float {
        $articulo = $this->find($idArticulo);

        if (!$articulo) {
            return 0;
        }

        $precioField = match($tarifa) {
            1 => 'Precio_Venta_1',
            2 => 'Precio_Venta_2',
            3 => 'Precio_Venta_3',
            4 => 'Precio_Venta_4',
            5 => 'Precio_Venta_5',
            6 => 'Precio_Venta_6',
            7 => 'Precio_Venta_7',
            8 => 'Precio_Venta_8',
            default => 'Precio_Venta_1',
        };

        return (float)($articulo[$precioField] ?? $articulo['Precio_Venta_1'] ?? 0);
    }

    /**
     * Obtener tipo de IVA del artículo
     */
    public function getTipoIVA(int|string $idArticulo): ?array {
        $articulo = $this->find($idArticulo);

        if (!$articulo || empty($articulo['Id_Tipo_IVA'])) {
            return null;
        }

        return Database::getInstance()->fetch(
            "SELECT * FROM `Tipos_IVA` WHERE `Codigo` = ?",
            [$articulo['Id_Tipo_IVA']]
        );
    }

    /**
     * Listar artículos con paginación
     */
    public function paginate(int $page = 1, int $perPage = 25): array {
        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;

        $total = $this->db->fetchCell(
            "SELECT COUNT(*) FROM `Articulos` WHERE `Activo` = 'S'"
        );

        $items = $this->db->fetchAll(
            "SELECT `Codigo`, `Descripcion`, `Codigo_Barras`, `Id_Tipo_IVA`,
                    `Id_Familia`, `Precio_Venta_1`
             FROM `Articulos`
             WHERE `Activo` = 'S'
             ORDER BY `Descripcion`
             LIMIT {$perPage} OFFSET {$offset}"
        );

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * Obtener precio con descuento de familia aplicado
     */
    public function getPrecioConDescuento(int|string $idArticulo, int $tarifa, ?int $idFamilia = null): array {
        $articulo = $this->find($idArticulo);

        if (!$articulo) {
            return ['precio' => 0, 'descuento' => 0, 'precio_final' => 0];
        }

        $precio    = $this->getPrecio($idArticulo, $tarifa);
        $descuento = (float)($articulo['Descuento'] ?? 0);

        if ($idFamilia) {
            $familia = Database::getInstance()->fetch(
                "SELECT * FROM `Familias` WHERE `Codigo` = ?",
                [$idFamilia]
            );

            if ($familia) {
                $descuentoField = match($tarifa) {
                    1 => 'Tarifa_1_Porcentaje',
                    2 => 'Tarifa_2_Porcentaje',
                    3 => 'Tarifa_3_Porcentaje',
                    4 => 'Tarifa_4_Porcentaje',
                    5 => 'Tarifa_5_Porcentaje',
                    6 => 'Tarifa_6_Porcentaje',
                    7 => 'Tarifa_7_Porcentaje',
                    8 => 'Tarifa_8_Porcentaje',
                    default => 'Tarifa_1_Porcentaje',
                };

                $descuentoFamilia = (float)($familia[$descuentoField] ?? 0);
                if ($descuentoFamilia > 0) {
                    $descuento = $descuentoFamilia;
                }
            }
        }

        $precioFinal = $precio * (1 - $descuento / 100);

        return [
            'precio'       => round($precio, 2),
            'descuento'    => $descuento,
            'precio_final' => round($precioFinal, 2),
        ];
    }
}
