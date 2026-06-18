<?php

/**
 * Controlador de Verifactu
 * Módulo de Facturación
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/NifInvalidoException.php';
require_once __DIR__ . '/../services/VerifactuService.php';
require_once __DIR__ . '/../models/VerifactuRegistro.php';

class VerifactuController
{
    private VerifactuService $verifactuService;
    private VerifactuRegistro $registroModel;

    public function __construct()
    {
        $this->verifactuService = new VerifactuService();
        $this->registroModel    = new VerifactuRegistro();
    }

    /**
     * Listar registros con paginación
     */
    public function index(): void
    {
        try {
            $page    = (int)($_GET['page']     ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 25);
            $estado  = $_GET['estado']         ?? null;
            $tipo    = $_GET['tipo']            ?? null;

            $filtros = array_filter(['estado' => $estado]);
            if (!empty($tipo)) {
                if (in_array($tipo, ['RECTIFICATIVA', 'SIMPLIFICADA', 'RECAPITULATIVA'])) {
                    $filtros['tipo_documento'] = $tipo;
                } else {
                    $filtros['tipo_origen'] = $tipo;
                }
            }

            $result = $this->registroModel->paginate($page, $perPage, $filtros);

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
        } catch (Exception $e) {
            Response::serverError('Error al listar registros', $e);
        }
    }

    /**
     * Estadísticas Verifactu
     */
    public function estadisticas(): void
    {
        try {
            $stats = $this->registroModel->getEstadisticas();
            Response::success($stats);
        } catch (Exception $e) {
            Response::serverError('Error al obtener estadísticas', $e);
        }
    }

    /**
     * Obtener estado de un documento
     */
    public function estado(string $codigo): void
    {
        try {
            $tipoOrigen = 'FACTURA';
            if (strpos($codigo, 'TICKET/') === 0) {
                $tipoOrigen = 'TICKET';
                $codigo     = substr($codigo, 7);
            } elseif (strpos($codigo, 'FACTURA/') === 0) {
                $codigo = substr($codigo, 8);
            }

            $estado = $this->verifactuService->getEstado($tipoOrigen, $codigo);

            if (!$estado) {
                Response::notFound('No existe registro Verifactu para este documento');
            }

            Response::success($estado);
        } catch (Exception $e) {
            Response::serverError('Error al obtener estado', $e);
        }
    }

    /**
     * Firmar un documento
     */
    public function firmar(): void
    {
        try {
            $cuerpoRequest = json_decode(file_get_contents('php://input'), true);

            if (!$cuerpoRequest || empty($cuerpoRequest['id_documento'])) {
                Response::error('ID de documento requerido');
            }

            $tipoOrigen  = $cuerpoRequest['tipo_origen']  ?? 'FACTURA';
            $idDocumento = $cuerpoRequest['id_documento'];
            $autoEnvio   = $cuerpoRequest['auto_envio']   ?? false;

            $resultado = $this->verifactuService->firmar($tipoOrigen, $idDocumento, $autoEnvio);

            Response::success($resultado, 'Documento firmado correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al firmar documento', $e);
        }
    }

    /**
     * Firmar y enviar (combinado)
     */
    public function firmarYEnviar(): void
    {
        try {
            $cuerpoRequest = json_decode(file_get_contents('php://input'), true);

            if (!$cuerpoRequest || empty($cuerpoRequest['id_documento'])) {
                Response::error('ID de documento requerido');
            }

            $tipoOrigen          = $cuerpoRequest['tipo_origen']           ?? 'FACTURA';
            $idDocumento         = $cuerpoRequest['id_documento'];
            $nifExportacion      = isset($cuerpoRequest['nif_exportacion']) ? (string)$cuerpoRequest['nif_exportacion'] : null;
            $nifExportacionVacio = (bool)($cuerpoRequest['nif_exportacion_vacio'] ?? false);
            $rawPais             = strtoupper(trim((string)($cuerpoRequest['pais_exportacion'] ?? '')));
            $paisExportacion     = preg_match('/^[A-Z]{2}$/', $rawPais) ? $rawPais : null;

            $resultado = $this->verifactuService->firmar($tipoOrigen, $idDocumento, true, $nifExportacion, $nifExportacionVacio, $paisExportacion);

            Response::success($resultado, 'Documento firmado y enviado');
        } catch (NifInvalidoException $e) {
            http_response_code(422);
            echo json_encode([
                'success'    => false,
                'message'    => $e->getMessage(),
                'nif_error'  => true,
                'id_cliente' => $e->idCliente,
                'nif_actual' => $e->nifActual,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            Response::serverError('Error al procesar', $e);
        }
    }

    /**
     * Enviar documento a Hacienda
     */
    public function enviar(string $codigo): void
    {
        try {
            $segmentosRuta = explode('/', $codigo);
            $tipoOrigen    = $segmentosRuta[0] === 'TICKET' ? 'TICKET' : 'FACTURA';
            $idDocumento   = $segmentosRuta[1] ?? $codigo;

            $resultado = $this->verifactuService->enviarAHacienda($tipoOrigen, $idDocumento);

            if ($resultado['ok']) {
                Response::success($resultado, 'Documento enviado a Hacienda');
            } else {
                Response::error($resultado['error'], 400);
            }
        } catch (Exception $e) {
            Response::serverError('Error al enviar documento', $e);
        }
    }

    /**
     * Reintentar envío
     */
    public function reintentar(string $codigo): void
    {
        try {
            $segmentosRuta = explode('/', $codigo);
            $tipoOrigen    = $segmentosRuta[0] === 'TICKET' ? 'TICKET' : 'FACTURA';
            $idDocumento   = $segmentosRuta[1] ?? $codigo;

            $resultado = $this->verifactuService->firmar($tipoOrigen, $idDocumento, true);
            Response::success($resultado, 'Documento reenviado a Hacienda');
        } catch (Exception $e) {
            Response::serverError('Error al reintentar', $e);
        }
    }

    /**
     * Estado del certificado
     */
    public function certificado(): void
    {
        try {
            require_once __DIR__ . '/../libs/VerifactuWrapper.php';

            $wrapper   = new VerifactuWrapper();
            $resultado = [
                'configurado'  => $wrapper->tieneCertificado(),
                'valido_hasta' => null,
            ];

            if ($wrapper->tieneCertificado()) {
                $certInfo = $wrapper->getInfoCertificado();
                if ($certInfo) {
                    $resultado['valido_hasta'] = $certInfo['valido_hasta'];
                }
            }

            Response::success($resultado);
        } catch (Exception $e) {
            Response::serverError('Error al verificar certificado', $e);
        }
    }

    /**
     * Descargar XML de factura
     *
     * @param string $codigo Formato: TIPO/CODIGO (ej: FACTURA/2026F1)
     * @param string $firmar 'signed'|'firmado' para XAdES, 'aeat' para respuesta AEAT
     */
    public function descargarXml(string $codigo, string $firmar = ''): void
    {
        try {
            require_once __DIR__ . '/../libs/VerifactuWrapper.php';
            require_once __DIR__ . '/../models/Factura.php';

            $segmentosRuta = explode('/', $codigo);
            $tipoOrigen    = $segmentosRuta[0] === 'TICKET' ? 'TICKET' : 'FACTURA';
            $idDocumento   = trim($segmentosRuta[1] ?? '');

            $facturaModel = new Factura();
            $factura      = $facturaModel->find($idDocumento);

            if (!$factura) {
                Response::error('Factura no encontrada', 404);
            }

            $lineas = $facturaModel->getLineas($idDocumento);

            // Obtener cliente
            require_once __DIR__ . '/../models/Cliente.php';
            $clienteModel  = new Cliente();
            $idClienteFact = $factura['Id_Cliente'] ?? '';

            // Para recapitulativas sin cliente: usar el del canal
            if (empty($idClienteFact) && ($factura['Tipo_Documento'] ?? '') === 'RECAPITULATIVA' && !empty($factura['Id_Canal'])) {
                $canalRow = Database::getInstance()->fetch(
                    "SELECT * FROM `Canales` WHERE `Codigo` = ?",
                    [$factura['Id_Canal']]
                );
                $idClienteFact = $canalRow['Id_Cliente_facturacion'] ?? $canalRow['Id_Cliente_Facturacion'] ?? '';
            }
            $cliente = !empty($idClienteFact) ? $clienteModel->find($idClienteFact) : null;

            // Normalizar fecha (MySQL/PDO devuelve strings)
            $fechaFactura = substr((string)($factura['Fecha'] ?? date('Y-m-d')), 0, 10);

            $codigoDb = (string)($factura['Codigo'] ?? $idDocumento);

            $datos = [
                'codigo'         => $codigoDb,
                'fecha'          => $fechaFactura,
                'tipo_documento' => (string)($factura['Tipo_Documento'] ?? $tipoOrigen),
                'descripcion'    => 'Factura electrónica',
                'base_imponible' => (float)($factura['Base_Imponible'] ?? 0),
                'importe_iva'    => (float)($factura['Importe_IVA']    ?? 0),
                'total'          => (float)($factura['Total']          ?? 0),
                'lineas'         => array_map(function ($linea) {
                    $descripcion = (string)(
                        $linea['Descripcion'] ?? $linea['Concepto'] ?? $linea['Articulo'] ?? $linea['descripcion'] ?? 'Línea'
                    );
                    $cantidad  = (float)($linea['Cantidad']      ?? $linea['cantidad']       ?? 1);
                    $precio    = (float)($linea['Precio']        ?? $linea['Precio_Unitario'] ?? $linea['precio'] ?? 0);
                    $baseLinea = (float)($linea['Base_Imponible'] ?? $linea['base_imponible'] ?? ($cantidad * $precio));

                    $codigoTipoIva = (string)($linea['Id_Tipo_IVA'] ?? '');
                    $datosTipo     = $this->obtenerDatosTipoIva($codigoTipoIva);

                    // Aplica_RE viene de BD como 'S'/'N' — 'N' es truthy, no usar !empty()
                    $aplicaRELinea = in_array($linea['Aplica_RE'] ?? null, ['S', 's', '1', 1, true], true);
                    $tipoRePct     = (float)($linea['tipo_re_pct'] ?? 0);
                    $cuotaRE       = (float)($linea['RE']          ?? 0);

                    if ($aplicaRELinea && $tipoRePct <= 0 && $cuotaRE > 0) {
                        $tipoRePct = $baseLinea > 0 ? round($cuotaRE / $baseLinea * 100, 2) : 0;
                    }
                    if ($aplicaRELinea && $tipoRePct > 0 && $cuotaRE == 0.0) {
                        $cuotaRE = round($baseLinea * $tipoRePct / 100, 2);
                    }

                    $claveRegimen = ($aplicaRELinea && $tipoRePct > 0) ? '18' : '01';
                    $storedCR     = trim((string)($linea['Clave_Regimen'] ?? ''));
                    if ($storedCR !== '' && $claveRegimen === '01') {
                        $claveRegimen = $storedCR;
                    }

                    return [
                        'descripcion'      => $descripcion,
                        'cantidad'         => $cantidad,
                        'precio'           => $precio,
                        'iva_porcentaje'   => (float)($linea['tipo_iva_pct'] ?? $datosTipo['iva']),
                        'iva_cuota'        => (float)($linea['IVA_Cuota']    ?? $linea['Cuota_IVA'] ?? $linea['iva_cuota'] ?? 0),
                        'base_imponible'   => $baseLinea,
                        're_cuota'         => $cuotaRE,
                        're_porcentaje'    => $tipoRePct,
                        'codigo_verifactu' => (string)($linea['codigo_verifactu'] ?? ''),
                        'tipo_territorio'  => (string)($linea['tipo_territorio']  ?? ''),
                        'id_tipo_iva'      => $codigoTipoIva,
                        'clave_regimen'    => $claveRegimen,
                        'Calificacion'     => (string)($linea['Calificacion'] ?? 'S1'),
                    ];
                }, $lineas),
            ];

            // Factura rectificativa: referencia a la factura origen
            if (($factura['Tipo_Documento'] ?? '') === 'RECTIFICATIVA' && !empty($factura['Id_Factura_Origen'])) {
                $facturaOrigen  = $facturaModel->find($factura['Id_Factura_Origen']);
                if ($facturaOrigen) {
                    $fechaOrigen    = substr((string)($facturaOrigen['Fecha'] ?? date('Y-m-d')), 0, 10);
                    $codigoOrigenDb = (string)($facturaOrigen['Codigo'] ?? $factura['Id_Factura_Origen']);
                    $serieOrigen    = (string)($facturaOrigen['Id_Canal'] ?? substr($codigoOrigenDb, 4, 2));
                    $numeroOrigen   = (string)($facturaOrigen['Numero']   ?? ltrim(substr($codigoOrigenDb, 6, 13), '0'));
                    if ($numeroOrigen === '') $numeroOrigen = '0';

                    $empresaConfig = require __DIR__ . '/../config/empresa.php';
                    $datos['factura_origen_nif']  = $empresaConfig['nif'] ?? '';
                    $datos['factura_origen']       = $serieOrigen . $numeroOrigen;
                    $datos['factura_origen_fecha'] = $fechaOrigen;
                }
            }

            // Facturas sustituidas en recapitulativas
            if (($factura['Tipo_Documento'] ?? '') === 'RECAPITULATIVA') {
                $sustituidas = Database::getInstance()->fetchAll(
                    "SELECT FS.`Id_Simplificada` AS codigo, F.`Fecha`
                     FROM `Facturas_Sustituidas` FS
                     JOIN `Facturas_Clientes` F ON F.`Codigo` = FS.`Id_Simplificada`
                     WHERE FS.`Id_Recapitulativa` = ?
                     ORDER BY F.`Fecha`, FS.`Id_Simplificada`",
                    [$idDocumento]
                );
                if (!empty($sustituidas)) {
                    $datos['facturas_sustituidas'] = array_map(function ($s) {
                        return [
                            'codigo' => $s['codigo'],
                            'fecha'  => substr((string)($s['Fecha'] ?? date('Y-m-d')), 0, 10),
                        ];
                    }, $sustituidas);
                }
            }

            // Cliente
            if (!empty($cliente)) {
                $nif    = trim((string)($cliente['NIF']            ?? ''));
                $nombre = trim((string)($cliente['Nombre']         ?? $cliente['Nombre'] ?? ''));
                $datos['cliente'] = [
                    'nif'             => $nif,
                    'razon_social'    => $nombre !== '' ? $nombre : 'Cliente',
                    'id_cliente'      => trim((string)($cliente['Codigo']         ?? '')),
                    'id_pais'         => (string)($cliente['Id_Pais']             ?? ''),
                    'id_tipo_cliente' => (string)($cliente['Id_Tipo_Cliente']     ?? ''),
                ];
            }

            $firmar    = strtolower($firmar);
            $esFirmado = in_array($firmar, ['signed', 'firmado'], true);
            $esAeat    = ($firmar === 'aeat');

            $wrapper = new VerifactuWrapper();

            if ($esAeat) {
                $xmlAeat = $this->obtenerXmlAeat($tipoOrigen, $idDocumento);
                if ($xmlAeat) {
                    $this->descargarArchivoXml($xmlAeat, $datos['codigo'], '_aeat');
                    return;
                }
                Response::error('XML de AEAT no disponible. La factura debe haber sido enviada primero.', 404);
            }

            // Servir el XML exacto enviado a AEAT si existe en disco
            $safe        = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $idDocumento);
            $rutaXmlEnvio = __DIR__ . '/../storage/xml/envio_' . $tipoOrigen . '_' . $safe . '.xml';

            if (file_exists($rutaXmlEnvio)) {
                $xmlEnvio = file_get_contents($rutaXmlEnvio);
                if ($esFirmado) {
                    require_once __DIR__ . '/../services/FirmaDigitalService.php';
                    $firmaService   = new FirmaDigitalService();
                    $resultadoFirma = $firmaService->firmarXml($xmlEnvio);
                    if (!empty($resultadoFirma['ok'])) {
                        $xmlEnvio = $resultadoFirma['xml'];
                    }
                }
                $this->descargarArchivoXml($xmlEnvio, $datos['codigo'], $esFirmado ? '_firmado' : '');
                return;
            }

            // Fallback: regenerar XML desde la BD
            $resultado = $wrapper->generarXml($datos, $esFirmado);

            if (!$resultado['ok']) {
                Response::error('Error al generar XML: ' . $resultado['error'], 500);
            }

            $this->descargarArchivoXml($resultado['xml'], $datos['codigo'], $esFirmado ? '_firmado' : '');
        } catch (Exception $e) {
            Response::serverError('Error al descargar XML', $e);
        }
    }

    /**
     * Descargar el XML exacto que se enviaría a AEAT (generado por VerifactuService)
     */
    public function descargarXmlEnvio(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/VerifactuService.php';

            $segmentos   = explode('/', $codigo, 2);
            $tipoOrigen  = strtoupper($segmentos[0] ?? 'FACTURA');
            $idDocumento = $segmentos[1] ?? '';

            if (!$idDocumento) {
                Response::error('Código de documento requerido', 400);
            }

            $service = new VerifactuService();
            $xml     = $service->previewXmlEnvio($tipoOrigen, $idDocumento);

            $this->descargarArchivoXml($xml, $idDocumento, '_envio');
        } catch (Exception $e) {
            Response::serverError('Error al generar XML de envío', $e);
        }
    }

    /**
     * Anular una factura enviada a Verifactu
     */
    public function anular(string $codigo): void
    {
        try {
            $cuerpoRequest = json_decode(file_get_contents('php://input'), true);

            if (empty($cuerpoRequest['motivo'])) {
                Response::error('El motivo de anulación es obligatorio');
            }

            $motivo = $cuerpoRequest['motivo'];

            $segmentosRuta = explode('/', $codigo);
            $tipoOrigen    = count($segmentosRuta) >= 2 ? $segmentosRuta[0] : 'FACTURA';
            $idDocumento   = count($segmentosRuta) >= 2 ? $segmentosRuta[1] : $codigo;

            require_once __DIR__ . '/../models/Factura.php';
            $facturaModel = new Factura();
            $factura      = $facturaModel->find($idDocumento);

            if (!$factura) {
                Response::error('Factura no encontrada', 404);
            }

            $registro = $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);

            if (!$registro || $registro['Estado_Envio'] !== 'ENVIADO') {
                Response::error('Solo se pueden anular facturas que han sido enviadas a Hacienda', 400);
            }

            if (($registro['Anulado'] ?? '') === 'S') {
                Response::error('Esta factura ya está anulada', 400);
            }

            require_once __DIR__ . '/../libs/VerifactuWrapper.php';
            $wrapper = new VerifactuWrapper();

            $fechaFactura = substr((string)($factura['Fecha'] ?? date('Y-m-d')), 0, 10);

            // El encadenamiento debe referenciar el último registro del canal
            $idCanal       = $factura['Id_Canal'] ?? '';
            $ultimoRegistro = !empty($idCanal)
                ? $this->registroModel->getUltimo($tipoOrigen, $idCanal)
                : null;
            if (!$ultimoRegistro) {
                $ultimoRegistro = $registro;
            }

            // Fecha del registro que encabeza la cadena
            $prevIdDoc = $ultimoRegistro['Id_Documento'] ?? $idDocumento;
            if ($prevIdDoc === $idDocumento) {
                $prevFecha = $fechaFactura;
            } else {
                $prevFacturaData = $facturaModel->find($prevIdDoc);
                $prevFecha       = substr((string)($prevFacturaData['Fecha'] ?? date('Y-m-d')), 0, 10);
            }

            $datosAnulacion = [];
            if (!empty($ultimoRegistro['Huella_Actual'])) {
                $datosAnulacion = [
                    'previous_hash'    => $ultimoRegistro['Huella_Actual'],
                    'previous_invoice' => [
                        'invoiceNumber' => $prevIdDoc,
                        'issueDate'     => $prevFecha,
                    ],
                ];
            }

            $resultado = $wrapper->anular(
                $registro['Id_Documento'],
                '',
                $fechaFactura,
                $motivo,
                $datosAnulacion
            );

            if (!$resultado['ok']) {
                $this->registroModel->actualizarEnvio($registro['Id'], [
                    'Estado_Envio'  => 'ERROR',
                    'Error_Mensaje' => 'Anulación: ' . ($resultado['error'] ?? 'Error desconocido'),
                ]);
                Response::error('Error al anular: ' . ($resultado['error'] ?? 'Error desconocido'), 500);
            }

            $this->registroModel->actualizarEnvio($registro['Id'], [
                'Anulado'          => 'S',
                'Fecha_Anulacion'  => date('Y-m-d H:i:s'),
                'Motivo_Anulacion' => $motivo,
            ]);

            if (!empty($resultado['hash'])) {
                $this->registroModel->actualizarEnvio($ultimoRegistro['Id'], [
                    'Huella_Actual' => $resultado['hash'],
                ]);
            }

            Response::success([
                'anulado'   => true,
                'resultado' => $resultado['resultado'] ?? 'OK',
            ], 'Factura anulada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al anular factura', $e);
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVADOS
    // -------------------------------------------------------------------------

    private function descargarArchivoXml(string $xml, string $numero, string $sufijo = ''): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="factura_' . $numero . $sufijo . '.xml"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . strlen($xml));
        echo $xml;
        exit;
    }

    private function obtenerXmlAeat(string $tipoOrigen, string $idDocumento): ?string
    {
        $registro = $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);

        if ($registro && !empty($registro['XML_Respuesta'])) {
            return $registro['XML_Respuesta'];
        }

        $rutaArchivo = __DIR__ . '/../storage/xml/aeat_' . $tipoOrigen . '_' . $idDocumento . '.xml';
        if (file_exists($rutaArchivo)) {
            return file_get_contents($rutaArchivo);
        }

        return null;
    }

    /**
     * Obtiene el porcentaje IVA y Codigo_Verifactu de un tipo de IVA.
     */
    private function obtenerDatosTipoIva(string $codigoTipoIva): array
    {
        static $cache = [];

        if ($codigoTipoIva === '') {
            return ['iva' => 21.0, 'codigo_verifactu' => '01'];
        }

        if (!isset($cache[$codigoTipoIva])) {
            $db  = Database::getInstance();
            $row = $db->fetch(
                "SELECT `IVA`, `Codigo_Verifactu` FROM `Tipos_IVA` WHERE `Codigo` = ?",
                [$codigoTipoIva]
            );

            $pct = (float)($row['IVA']             ?? 21);
            $cv  = (string)($row['Codigo_Verifactu'] ?? '');

            if ($cv === '') {
                $cv = $this->derivarCodigoVerifactu($codigoTipoIva, $pct);
            }

            $cache[$codigoTipoIva] = ['iva' => $pct, 'codigo_verifactu' => $cv];
        }

        return $cache[$codigoTipoIva];
    }

    private function derivarCodigoVerifactu(string $codigoTipoIva, float $pct): string
    {
        if (strncmp($codigoTipoIva, 'IG', 2) === 0 || $codigoTipoIva === 'I15') return '03'; // IGIC
        if (strncmp($codigoTipoIva, 'IP', 2) === 0)                              return '02'; // IPSI
        if ($codigoTipoIva === 'NSJ' || $codigoTipoIva === 'ISP')                return '04';
        if ($pct == 0.0)                return '04';
        if ($pct == 21.0)               return '01';
        if ($pct == 10.0)               return '02';
        if ($pct == 4.0 || $pct == 5.0) return '03';
        return '01';
    }
}
