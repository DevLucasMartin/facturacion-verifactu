<?php
/**
 * Servicio de Cálculo - Motor de cálculo de importes de facturas
 * Módulo de Facturación - Versión 3.2
 */

class CalculoService {
    private Database $db;
    private array $config;

    public const TIPO_FACTURA        = 'FACTURA';
    public const TIPO_SIMPLIFICADA   = 'SIMPLIFICADA';
    public const TIPO_RECTIFICATIVA  = 'RECTIFICATIVA';
    public const TIPO_RECAPITULATIVA = 'RECAPITULATIVA';

    public const LIMITE_SIMPLIFICADA   = 400;   // €
    public const LIMITE_RECAPITULATIVA = 3000;  // €

    public function __construct() {
        $this->db     = Database::getInstance();
        $this->config = require __DIR__ . '/../config/app.php';
    }

    /**
     * Calcular totales de una factura a partir de líneas.
     *
     * @param array  $lineas        Líneas con: Cantidad, Precio, Descuento, Id_Tipo_IVA
     * @param array  $descuentos    Descuentos generales: Descuento_Especial, Descuento_PP, Descuento_Comercial
     * @param float  $rePorcentaje  Porcentaje de RE del cliente (0 si no aplica)
     * @param string $tipoDocumento Tipo de documento para validar límites
     * @return array Totales calculados
     */
    public function calcular(
        array  $lineas,
        array  $descuentos    = [],
        float  $rePorcentaje  = 0,
        string $tipoDocumento = self::TIPO_FACTURA
    ): array {
        $descuentos = array_merge([
            'Descuento_Especial'  => 0,
            'Descuento_PP'        => 0,
            'Descuento_Comercial' => 0,
        ], $descuentos);

        // Paso 1: importe bruto de cada línea
        $lineasCalculadas = [];
        $subtotal    = 0;
        $netSubtotal = 0;

        foreach ($lineas as $i => $linea) {
            $cantidad       = (float)($linea['Cantidad']  ?? 0);
            $precio         = (float)($linea['Precio']    ?? 0);
            $descuentoLinea = (float)($linea['Descuento'] ?? 0);
            $aplicaRE       = !empty($linea['Aplica_RE']);

            $importeBruto       = $cantidad * $precio;
            $importeDescuento   = $importeBruto * ($descuentoLinea / 100);
            $baseImponibleLinea = $importeBruto - $importeDescuento;

            $lineasCalculadas[$i] = [
                'Cantidad'          => $cantidad,
                'Precio'            => $precio,
                'Descuento'         => $descuentoLinea,
                'Importe_Bruto'     => $importeBruto,
                'Importe_Descuento' => $importeDescuento,
                'Base_Imponible'    => $baseImponibleLinea,
                'Id_Tipo_IVA'       => $linea['Id_Tipo_IVA'] ?? 'G21',
                'Aplica_RE'         => $aplicaRE,
                'Calificacion'      => $linea['Calificacion'] ?? 'S1',
            ];

            $subtotal    += $importeBruto;
            $netSubtotal += $baseImponibleLinea;
        }

        // Paso 2: descuentos generales en cascada sobre el neto
        $dtoEspecial      = $netSubtotal * ($descuentos['Descuento_Especial'] / 100);
        $netTrasEspecial  = $netSubtotal - $dtoEspecial;
        $dtoComercial     = $netTrasEspecial * ($descuentos['Descuento_Comercial'] / 100);
        $netTrasComercial = $netTrasEspecial - $dtoComercial;
        $dtoPP            = $netTrasComercial * ($descuentos['Descuento_PP'] / 100);
        $baseGlobal       = $netTrasComercial - $dtoPP;

        $factor = $netSubtotal > 0 ? $baseGlobal / $netSubtotal : 0;

        // Paso 3: agrupar por (tipo IVA + aplica RE + calificación)
        $basesAgrupadas = [];
        $tipoIVAs       = [];

        foreach ($lineasCalculadas as $linea) {
            $idTipoIVA    = $linea['Id_Tipo_IVA'];
            $aplicaRE     = $linea['Aplica_RE'];
            $calificacion = $linea['Calificacion'] ?? 'S1';
            $esNoSujeto   = in_array($calificacion, ['N1', 'N2'], true);
            $esS2         = $calificacion === 'S2';
            $esExenta     = in_array($calificacion, ['E1', 'E2', 'E3', 'E4', 'E5', 'E6'], true);

            $key = $idTipoIVA
                 . ($aplicaRE   ? '_RE' : '')
                 . ($esS2       ? '_S2' : '')
                 . ($esNoSujeto ? '_NS' : '')
                 . ($esExenta   ? '_EX' : '');

            $baseConFactor = $linea['Base_Imponible'] * $factor;

            if (!isset($basesAgrupadas[$key])) {
                $tipoIVA = $this->obtenerTipoIVA($idTipoIVA);
                $rePct   = ($aplicaRE && $tipoIVA) ? (float)$tipoIVA['RE'] : 0;
                $tipoIVAs[$key] = [
                    'Id_Tipo_IVA'   => $idTipoIVA,
                    'IVA'           => ($esS2 || $esNoSujeto || $esExenta) ? 0 : ($tipoIVA ? (float)$tipoIVA['IVA'] : 21),
                    'Porcentaje_RE' => ($esNoSujeto || $esExenta) ? 0 : $rePct,
                    'Aplica_RE'     => $aplicaRE,
                ];
                $basesAgrupadas[$key] = 0;
            }
            $basesAgrupadas[$key] += $baseConFactor;
        }

        // Paso 4: cuotas de IVA y RE por grupo
        $cuotasIVA = [];
        $totalIVA  = 0;
        $totalRE   = 0;

        foreach ($basesAgrupadas as $key => $base) {
            $tipo = $tipoIVAs[$key];

            $cuotaIVA = $base * ($tipo['IVA'] / 100);
            $cuotaRE  = $tipo['Porcentaje_RE'] > 0 ? $base * ($tipo['Porcentaje_RE'] / 100) : 0;

            $cuotasIVA[] = [
                'Id_Tipo_IVA'    => $tipo['Id_Tipo_IVA'],
                'Aplica_RE'      => $tipo['Aplica_RE'],
                'Base_Imponible' => round($base, 2),
                'Porcentaje_IVA' => $tipo['IVA'],
                'Cuota_IVA'      => round($cuotaIVA, 2),
                'Porcentaje_RE'  => $tipo['Porcentaje_RE'],
                'Cuota_RE'       => round($cuotaRE, 2),
            ];

            $totalIVA += $cuotaIVA;
            $totalRE  += $cuotaRE;
        }

        // Paso 5: totales finales
        $total   = $baseGlobal + $totalIVA + $totalRE;
        $errores = [];

        if ($tipoDocumento === self::TIPO_RECAPITULATIVA && $total > self::LIMITE_RECAPITULATIVA) {
            $errores[] = 'El total de una factura recapitulativa no puede superar ' . self::LIMITE_RECAPITULATIVA . '€';
        }

        return [
            'lineas'       => $lineasCalculadas,
            'subtotal'     => round($subtotal, 2),
            'net_subtotal' => round($netSubtotal, 2),
            'descuentos'   => [
                'Descuento_Especial'    => round($dtoEspecial, 2),
                'Importe_Dto_Especial'  => round($dtoEspecial, 2),
                'Importe_Dto_Lineas'    => round($subtotal - $netSubtotal, 2),
                'Descuento_Comercial'   => round($dtoComercial, 2),
                'Importe_Dto_Comercial' => round($dtoComercial, 2),
                'Descuento_PP'          => round($dtoPP, 2),
                'Importe_Dto_PP'        => round($dtoPP, 2),
            ],
            'base_imponible'    => round($baseGlobal, 2),
            'cuotas_iva'        => $cuotasIVA,
            'importe_iva'       => round($totalIVA, 2),
            'importe_re'        => round($totalRE, 2),
            'total'             => round($total, 2),
            'factor_descuentos' => round($factor, 6),
            'errores'           => $errores,
            're_aplica'         => $totalRE > 0,
        ];
    }

    public function calcularRE(float $baseImponible, float $rePorcentaje): float {
        return $rePorcentaje <= 0 ? 0 : round($baseImponible * ($rePorcentaje / 100), 2);
    }

    private function obtenerTipoIVA(string $codigo): ?array {
        return $this->db->fetch(
            'SELECT Codigo, IVA, RE FROM Tipos_IVA WHERE Codigo = ?',
            [$codigo]
        );
    }

    public function formatearImporte(float $importe, string $simbolo = '€'): string {
        $cfg = $this->config['facturacion'] ?? [];
        return number_format(
            $importe,
            $cfg['decimales_importes'] ?? 2,
            $cfg['separador_decimal']  ?? ',',
            $cfg['separador_miles']    ?? '.'
        ) . ' ' . $simbolo;
    }

    public function validarLinea(array $linea): array {
        $errores = [];
        if ((float)($linea['Cantidad'] ?? 0) <= 0) $errores[] = 'La cantidad debe ser mayor que 0';
        if ((float)($linea['Precio']   ?? 0) < 0)  $errores[] = 'El precio no puede ser negativo';
        if (empty($linea['Id_Articulo']))            $errores[] = 'El artículo es obligatorio';
        if (empty($linea['Id_Tipo_IVA']))            $errores[] = 'El tipo de IVA es obligatorio';
        return $errores;
    }

    public function calcularLinea(float $cantidad, float $precio, float $descuento = 0): array {
        $importeBruto     = $cantidad * $precio;
        $importeDescuento = $importeBruto * ($descuento / 100);
        return [
            'importe_bruto'     => round($importeBruto, 2),
            'importe_descuento' => round($importeDescuento, 2),
            'base_imponible'    => round($importeBruto - $importeDescuento, 2),
        ];
    }

    /**
     * Generar líneas con datos calculados listos para INSERT en Lineas_Facturas_Clientes.
     */
    public function generarLineasParaGuardar(
        array $lineas,
        array $descuentos,
        float $rePorcentaje = 0
    ): array {
        $resultado = $this->calcular($lineas, $descuentos, $rePorcentaje);
        $factor    = $resultado['factor_descuentos'];

        $lineasGuardar = [];
        foreach ($resultado['lineas'] as $i => $linea) {
            $aplicaRE      = $linea['Aplica_RE'];
            $tipoIVA       = $this->obtenerTipoIVA($linea['Id_Tipo_IVA']);
            $rePct         = ($aplicaRE && $tipoIVA) ? (float)$tipoIVA['RE'] : 0;
            $baseConFactor = $linea['Base_Imponible'] * $factor;
            $cuotaRE       = round($baseConFactor * $rePct / 100, 2);

            $lineasGuardar[] = [
                'Linea'             => (int)($i + 1),
                'Id_Articulo'       => (string)($lineas[$i]['Id_Articulo'] ?? ''),
                'Descripcion'       => (string)($lineas[$i]['Descripcion'] ?? ''),
                'Cantidad'          => (float)$linea['Cantidad'],
                'Precio'            => (float)$linea['Precio'],
                'Descuento'         => (float)$linea['Descuento'],
                'Id_Tipo_IVA'       => (string)$linea['Id_Tipo_IVA'],
                'Importe_Bruto'     => (float)$linea['Importe_Bruto'],
                'Importe_Descuento' => (float)$linea['Importe_Descuento'],
                'Base_Imponible'    => (float)$linea['Base_Imponible'],
                'Total'             => (float)$linea['Base_Imponible'],
                'Calificacion'      => (string)($lineas[$i]['Calificacion'] ?? 'S1'),
                'Clave_Regimen'     => (string)($lineas[$i]['Clave_Regimen'] ?? '01'),
                'Aplica_RE'         => $aplicaRE ? 1 : 0,
                'RE'                => $cuotaRE,
            ];
        }
        return $lineasGuardar;
    }

    public function obtenerDesgloseImpuestos(array $lineas, float $rePorcentaje = 0): array {
        return $this->calcular($lineas, [], $rePorcentaje)['cuotas_iva'];
    }
}
