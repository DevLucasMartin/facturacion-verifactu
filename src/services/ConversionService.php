<?php
/**
 * ConversionService – Convierte albaranes en una sola factura
 * Módulo de Facturación - Versión 3.2
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/NifInvalidoException.php';
require_once __DIR__ . '/../models/Albaran.php';
require_once __DIR__ . '/../models/Articulo.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../services/CalculoService.php';
require_once __DIR__ . '/../services/NumeracionService.php';

class ConversionService
{
    private Database         $db;
    private Albaran          $albaranModel;
    private Articulo         $articuloModel;
    private Cliente          $clienteModel;
    private CalculoService   $calculoService;
    private NumeracionService $numeracionService;

    public function __construct()
    {
        $this->db                = Database::getInstance();
        $this->albaranModel      = new Albaran();
        $this->articuloModel     = new Articulo();
        $this->clienteModel      = new Cliente();
        $this->calculoService    = new CalculoService();
        $this->numeracionService = new NumeracionService();
    }

    /**
     * Convierte uno o más albaranes en una sola factura.
     *
     * @param array $request {
     *   albaranes:            [{codigo: string, lineas: int[]}],
     *   Tipo_Documento:       string  (FACTURA | SIMPLIFICADA | RECAPITULATIVA)
     *   Id_Forma_Pago:        string
     *   Fecha?:               string  YYYY-MM-DD  (default: hoy)
     *   Observaciones?:       string
     *   Descuento_Especial?:  float
     *   Descuento_PP?:        float
     *   Descuento_Comercial?: float
     * }
     * @return array ['codigo' => string, 'numero' => int, 'total' => float]
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function convertir(array $request): array
    {
        // ── 1. Validar cabecera ───────────────────────────────────────────────
        if (empty($request['albaranes'])) {
            throw new \InvalidArgumentException('Se requiere al menos un albarán.');
        }

        $tipoDocumento = $request['Tipo_Documento'] ?? 'FACTURA';
        if (!in_array($tipoDocumento, ['FACTURA', 'SIMPLIFICADA', 'RECAPITULATIVA'], true)) {
            throw new \InvalidArgumentException("Tipo de documento inválido: {$tipoDocumento}");
        }

        $fecha = $request['Fecha'] ?? date('Y-m-d');

        // ── 2. Cargar y validar albaranes ──────────────────────────────────────
        $idCliente = null;
        $idCanal   = null;
        $adList    = [];

        foreach ($request['albaranes'] as $item) {
            $codigo            = trim($item['codigo'] ?? '');
            $lineasSolicitadas = array_map('intval', $item['lineas'] ?? []);

            if ($codigo === '') {
                throw new \InvalidArgumentException('Código de albarán vacío.');
            }

            $albaran = $this->albaranModel->findWithLines($codigo);
            if (!$albaran) {
                throw new \InvalidArgumentException("Albarán no encontrado: {$codigo}");
            }

            if ($idCliente === null) {
                $idCliente = $albaran['Id_Cliente'];
                $idCanal   = $albaran['Id_Canal'];
            }

            $lineasAlbaran = $albaran['lineas'] ?? [];
            if (!empty($lineasSolicitadas)) {
                $lineasAlbaran = array_values(array_filter(
                    $lineasAlbaran,
                    fn($l) => in_array((int)$l['Linea'], $lineasSolicitadas, true)
                ));
            }

            // Excluir líneas ya facturadas
            $lineasAlbaran = array_values(array_filter(
                $lineasAlbaran,
                fn($l) => strtoupper((string)($l['Facturada'] ?? 'N')) !== 'S'
            ));

            if (empty($lineasAlbaran)) {
                throw new \InvalidArgumentException(
                    "No hay líneas pendientes de facturar en el albarán {$codigo}."
                );
            }

            $adList[] = [
                'albaran'          => $albaran,
                'lineas'           => $lineasAlbaran,
                'lineas_aplica_re' => $item['lineas_aplica_re'] ?? [],
            ];
        }

        // ── 3. Datos del cliente (RE) ──────────────────────────────────────────
        if (!empty($request['Id_Cliente'])) {
            $idCliente = $request['Id_Cliente'];
        }

        $cliente = null;
        if (!empty($idCliente)) {
            $cliente = $this->clienteModel->find($idCliente);
            if (!$cliente) {
                throw new \InvalidArgumentException("Cliente no encontrado: {$idCliente}");
            }
            $nifCliente = trim((string)($cliente['NIF'] ?? ''));
            if ($nifCliente !== '') {
                $errorNif = Validator::validarFormatoNif($nifCliente);
                if ($errorNif !== null) {
                    throw new NifInvalidoException(
                        "NIF del cliente inválido: {$errorNif}",
                        (string)$idCliente,
                        $nifCliente
                    );
                }
            }
        }
        $rePorcentaje    = (float)($cliente['RE_Porcentaje'] ?? 0);
        $aplicaRECliente = $rePorcentaje > 0 ? 1 : 0;

        // ── 4. Construir líneas de factura ─────────────────────────────────────
        $lineasFactura = [];
        $lineaNum      = 1;

        foreach ($adList as $ad) {
            $lineasAplicaRE = $ad['lineas_aplica_re'] ?? [];
            foreach ($ad['lineas'] as $la) {
                $idArticulo  = (string)($la['Id_Articulo'] ?? '');
                $descripcion = '';

                $articulo = $this->articuloModel->find($idArticulo);
                if ($articulo) {
                    $descripcion = (string)($articulo['Descripcion'] ?? '');
                }

                $lineaOrigen   = (int)$la['Linea'];
                $aplicaRELinea = isset($lineasAplicaRE[$lineaOrigen])
                    ? (int)$lineasAplicaRE[$lineaOrigen]
                    : $aplicaRECliente;

                $lineasFactura[] = [
                    'Linea'         => $lineaNum++,
                    'Id_Articulo'   => mb_substr($idArticulo, 0, 50),
                    'Descripcion'   => mb_substr($descripcion, 0, 200),
                    'Cantidad'      => (float)($la['Cantidad']  ?? 1),
                    'Precio'        => (float)($la['Precio']    ?? 0),
                    'Descuento'     => (float)($la['Descuento'] ?? 0),
                    'Id_Tipo_IVA'   => (string)($la['Id_Tipo_IVA'] ?? 'G21'),
                    'Aplica_RE'     => $aplicaRELinea,
                    'Calificacion'  => (string)($la['Calificacion'] ?? 'S1'),
                    '_Id_Albaran'   => $ad['albaran']['Codigo'],
                    '_Linea_Origen' => $lineaOrigen,
                ];
            }
        }

        // ── 5. Calcular totales ────────────────────────────────────────────────
        $descuentos = [
            'Descuento_Especial'  => (float)($request['Descuento_Especial']  ?? 0),
            'Descuento_PP'        => (float)($request['Descuento_PP']        ?? 0),
            'Descuento_Comercial' => (float)($request['Descuento_Comercial'] ?? 0),
        ];

        $lineasCalculo = array_map(fn($l) => [
            'Cantidad'     => $l['Cantidad'],
            'Precio'       => $l['Precio'],
            'Descuento'    => $l['Descuento'],
            'Id_Tipo_IVA'  => $l['Id_Tipo_IVA'],
            'Aplica_RE'    => $l['Aplica_RE'],
            'Calificacion' => $l['Calificacion'] ?? 'S1',
        ], $lineasFactura);

        $calc = $this->calculoService->calcular(
            $lineasCalculo,
            $descuentos,
            $rePorcentaje,
            $tipoDocumento
        );

        if (!empty($calc['errores'])) {
            throw new \InvalidArgumentException($calc['errores'][0]);
        }

        // ── 6. Asignar número ──────────────────────────────────────────────────
        $ejercicio  = (int)date('Y', strtotime($fecha));
        $numeracion = $this->numeracionService->asignarNumero($idCanal, $ejercicio, $tipoDocumento);

        // ── 7. Mezclar datos calculados con metadatos de trazabilidad ──────────
        $lineasConRE  = $this->calculoService->generarLineasParaGuardar($lineasCalculo, $descuentos, $rePorcentaje);
        $lineasGuardar = [];

        foreach ($lineasConRE as $i => $lc) {
            $orig = $lineasFactura[$i];
            $lineasGuardar[] = [
                'Linea'             => (int)$orig['Linea'],
                'Id_Articulo'       => mb_substr((string)$orig['Id_Articulo'], 0, 50),
                'Descripcion'       => mb_substr((string)$orig['Descripcion'], 0, 200),
                'Cantidad'          => (float)$lc['Cantidad'],
                'Precio'            => (float)$lc['Precio'],
                'Descuento'         => (float)$lc['Descuento'],
                'Id_Tipo_IVA'       => (string)$lc['Id_Tipo_IVA'],
                'Importe_Bruto'     => (float)$lc['Importe_Bruto'],
                'Importe_Descuento' => (float)$lc['Importe_Descuento'],
                'Base_Imponible'    => (float)$lc['Base_Imponible'],
                'Total'             => (float)$lc['Base_Imponible'],
                'Calificacion'      => (string)($orig['Calificacion'] ?? 'S1'),
                'Clave_Regimen'     => (string)($orig['Clave_Regimen'] ?? '01'),
                'RE'                => (float)($lc['RE'] ?? 0),
                'Aplica_RE'         => ($lc['Aplica_RE'] ?? ($orig['Aplica_RE'] ?? 0)) ? 'S' : 'N',
            ];
        }

        // ── 8. Cabecera de la factura ──────────────────────────────────────────
        $facturaHeader = [
            'Codigo'               => $numeracion['codigo'],
            'Numero'               => $numeracion['numero'],
            'Id_Canal'             => $idCanal,
            'Fecha'                => $fecha,
            'Id_Cliente'           => !empty($idCliente) ? $idCliente : null,
            'Id_Forma_Pago'        => ($request['Id_Forma_Pago'] ?? '') ?: null,
            'Tipo_Documento'       => $tipoDocumento,
            'Abono'                => 'N',
            'Observaciones'        => $request['Observaciones'] ?? '',
            'Descuento_Especial'   => $descuentos['Descuento_Especial'],
            'Descuento_PP'         => $descuentos['Descuento_PP'],
            'Descuento_Comercial'  => $descuentos['Descuento_Comercial'],
            'Importe_Dto_Especial' => $calc['descuentos']['Importe_Dto_Especial'],
            'Importe_Dto_PP'       => $calc['descuentos']['Importe_Dto_PP'],
            'Importe_Bruto'        => $calc['subtotal'],
            'Base_Imponible'       => $calc['base_imponible'],
            'Cuota_IVA'            => $calc['importe_iva'],
            'Total'                => $calc['total'],
            'Cerrada'              => 'N',
            'Cobrada'              => 'N',
            'Fecha_Alta'           => date('Y-m-d H:i:s'),
            'Usuario_Alta'         => $_SESSION['usuario'] ?? 'sistema',
            'Ultima_Modificacion'  => date('Y-m-d H:i:s'),
        ];

        // ── 9. Persistir en una única transacción ──────────────────────────────
        $this->db->beginTransaction();
        try {
            $this->db->insert('Facturas_Clientes', $facturaHeader);

            foreach ($lineasGuardar as $linea) {
                $linea['Id_Factura'] = $numeracion['codigo'];
                $this->db->insert('Lineas_Facturas_Clientes', $linea);
            }

            foreach ($adList as $ad) {
                foreach ($ad['lineas'] as $la) {
                    $this->db->update(
                        'Lineas_Albaranes_Clientes',
                        ['Facturada' => 'S'],
                        'Id_Albaran = ? AND Linea = ?',
                        [$ad['albaran']['Codigo'], (int)$la['Linea']]
                    );
                }

                // Marcar albarán completo si no quedan líneas pendientes
                $pendientes = (int)$this->db->fetchCell(
                    "SELECT COUNT(*)
                     FROM Lineas_Albaranes_Clientes
                     WHERE Id_Albaran = ?
                       AND IFNULL(Facturada, 'N') = 'N'",
                    [$ad['albaran']['Codigo']]
                );

                if ($pendientes === 0) {
                    $this->db->update(
                        'Albaranes_Clientes',
                        [
                            'Facturado'           => 'S',
                            'Ultima_Modificacion' => date('Y-m-d H:i:s'),
                        ],
                        'Codigo = ?',
                        [$ad['albaran']['Codigo']]
                    );
                }
                // Cerrado se deja en 'N' intencionalmente
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'codigo' => $numeracion['codigo'],
            'numero' => $numeracion['numero'],
            'total'  => $calc['total'],
        ];
    }
}
