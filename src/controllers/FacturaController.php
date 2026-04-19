<?php
/**
 * Controlador de Facturas
 * Módulo de Facturación - VeriFACTU
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/NifInvalidoException.php';
require_once __DIR__ . '/../models/Factura.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Albaran.php';
require_once __DIR__ . '/../models/VerifactuRegistro.php';
require_once __DIR__ . '/../services/CalculoService.php';
require_once __DIR__ . '/../services/NumeracionService.php';
require_once __DIR__ . '/../services/ConversionService.php';
require_once __DIR__ . '/../libs/VerifactuWrapper.php';

class FacturaController
{
    private Factura $facturaModel;
    private Cliente $clienteModel;
    private Albaran $albaranModel;
    private CalculoService $calculoService;
    private NumeracionService $numeracionService;
    private VerifactuWrapper $verifactu;
    private VerifactuRegistro $verifactuRegistro;

    public function __construct()
    {
        $this->facturaModel      = new Factura();
        $this->clienteModel      = new Cliente();
        $this->albaranModel      = new Albaran();
        $this->calculoService    = new CalculoService();
        $this->numeracionService = new NumeracionService();
        $this->verifactu         = new VerifactuWrapper();
        $this->verifactuRegistro = new VerifactuRegistro();
    }

    // -------------------------------------------------------------------------
    // LISTADO Y BÚSQUEDA
    // -------------------------------------------------------------------------

    public function index(): void
    {
        try {
            $page          = (int)($_GET['page']     ?? 1);
            $perPage       = min((int)($_GET['per_page'] ?? $_GET['limit'] ?? 25), 100);
            $tipoDocumento = $_GET['tipo_documento'] ?? null;
            $idCliente     = $_GET['id_cliente']     ?? null;
            $fechaDesde    = $_GET['fecha_desde']    ?? null;
            $fechaHasta    = $_GET['fecha_hasta']    ?? null;
            $idCanal       = $_GET['id_canal']       ?? null;
            $codigo        = $_GET['codigo']         ?? null;

            $filtros = array_filter([
                'tipo_documento' => $tipoDocumento,
                'id_cliente'     => $idCliente,
                'fecha_desde'    => $fechaDesde,
                'fecha_hasta'    => $fechaHasta,
                'id_canal'       => $idCanal,
                'codigo'         => $codigo,
            ]);

            $result = $this->facturaModel->paginate($page, $perPage, $filtros);

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
        } catch (Exception $e) {
            Response::serverError('Error al listar facturas', $e);
        }
    }

    public function search(): void
    {
        $query = $_GET['q'] ?? '';
        try {
            if (strlen($query) < 2) {
                Response::error('La búsqueda debe tener al menos 2 caracteres');
            }
            $result = $this->facturaModel->findByCodigo($query);
            Response::success($result);
        } catch (Exception $e) {
            Response::serverError('Error en la búsqueda', $e);
        }
    }

    public function origen(): void
    {
        try {
            $cliente   = $_GET['cliente']   ?? '';
            $ejercicio = (int)($_GET['ejercicio'] ?? date('Y'));
            $limit     = (int)($_GET['limit'] ?? 200);

            if ($cliente === '') {
                Response::error('Falta parámetro cliente');
            }

            $rows = $this->facturaModel->origen($cliente, $ejercicio, $limit);
            Response::success($rows);
        } catch (Exception $e) {
            Response::serverError('Error al obtener facturas origen', $e);
        }
    }

    public function estadisticas(): void
    {
        try {
            $ejercicio = isset($_GET['ejercicio']) ? (int)$_GET['ejercicio'] : null;
            $stats     = $this->facturaModel->getEstadisticas($ejercicio);
            Response::success($stats);
        } catch (Exception $e) {
            Response::serverError('Error al obtener estadísticas', $e);
        }
    }

    public function siguienteNumero(): void
    {
        try {
            $idCanal       = $_GET['canal']          ?? 'R5';
            $tipoDocumento = $_GET['tipo_documento'] ?? 'FACTURA';
            $fecha         = $_GET['fecha']          ?? date('Y-m-d');
            $ejercicio     = (int)date('Y', strtotime($fecha));

            $codigo = $this->numeracionService->getSiguienteCodigo($idCanal, $ejercicio, $tipoDocumento);
            $numero = $this->numeracionService->getSiguienteNumero($idCanal, $ejercicio, $tipoDocumento);

            Response::success([
                'codigo'         => $codigo,
                'numero'         => $numero,
                'canal'          => $idCanal,
                'ejercicio'      => $ejercicio,
                'tipo_documento' => $tipoDocumento,
            ]);
        } catch (Exception $e) {
            Response::serverError('Error al obtener número', $e);
        }
    }

    // -------------------------------------------------------------------------
    // VER
    // -------------------------------------------------------------------------

    public function show(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->findWithLines($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }

            // Para simplificadas indicar si ya ha sido recapitulada
            if (($factura['Tipo_Documento'] ?? '') === 'SIMPLIFICADA') {
                $db         = Database::getInstance();
                $sustituida = $db->fetch(
                    "SELECT 1 FROM `Facturas_Sustituidas` WHERE `Id_Simplificada` = ?",
                    [$codigo]
                );
                $factura['recapitulada'] = $sustituida !== null;
            }

            // Cargar datos del cliente
            $idCliente = $factura['Id_Cliente'] ?? '';

            // Para recapitulativas sin cliente: usar el cliente de facturación del canal
            if (empty($idCliente) && ($factura['Tipo_Documento'] ?? '') === 'RECAPITULATIVA' && !empty($factura['Id_Canal'])) {
                $db    = Database::getInstance();
                $canal = $db->fetch(
                    "SELECT * FROM `Canales` WHERE `Codigo` = ?",
                    [$factura['Id_Canal']]
                );
                $idCliente = $canal['Id_Cliente_facturacion'] ?? $canal['Id_Cliente_Facturacion'] ?? '';

                if (!empty($idCliente)) {
                    $factura['cliente'] = $this->clienteModel->find($idCliente);
                } elseif ($canal) {
                    $factura['cliente'] = [
                        'Nombre'      => $canal['Descripcion'] ?? $canal['Codigo'],
                        'NIF'         => '',
                        'Direccion'   => $canal['Direccion_facturacion'] ?? '',
                        'Id_Tipo_IVA' => '',
                        'Aplica_RE'   => 0,
                    ];
                }
            } elseif (!empty($idCliente)) {
                $factura['cliente'] = $this->clienteModel->find($idCliente);
            }

            Response::success($factura);
        } catch (Exception $e) {
            Response::serverError('Error al obtener factura', $e);
        }
    }

    // -------------------------------------------------------------------------
    // PDF / EMAIL
    // -------------------------------------------------------------------------

    public function generarPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';
            $pdfService = new PdfService();
            $resultado  = $pdfService->descargarPdf($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }
            Response::success($resultado);
        } catch (Exception $e) {
            Response::serverError('Error al generar PDF', $e);
        }
    }

    public function verPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';
            $pdfService = new PdfService();
            $resultado  = $pdfService->descargarPdf($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }

            $ruta    = $resultado['ruta'];
            $formato = $resultado['formato'] ?? 'pdf';

            $mimeTypes = [
                'pdf'  => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            header('Content-Type: ' . ($mimeTypes[$formato] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="factura_' . $codigo . '.' . $formato . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;
        } catch (Exception $e) {
            Response::serverError('Error al ver PDF', $e);
        }
    }

    public function descargarPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';
            $pdfService = new PdfService();
            $resultado  = $pdfService->descargarPdf($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }

            $ruta    = $resultado['ruta'];
            $formato = $resultado['formato'] ?? 'pdf';

            $mimeTypes = [
                'pdf'  => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            header('Content-Type: ' . ($mimeTypes[$formato] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="factura_' . $codigo . '.' . $formato . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;
        } catch (Exception $e) {
            Response::serverError('Error al descargar PDF', $e);
        }
    }

    public function enviarEmail(string $codigo): void
    {
        try {
            $data         = json_decode(file_get_contents('php://input'), true);
            $emailDestino = $data['email'] ?? null;

            if (!$emailDestino) {
                Response::error('Email de destino requerido');
            }

            $factura = $this->facturaModel->findWithLines($codigo);
            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }

            $cliente = $this->clienteModel->find($factura['Id_Cliente'] ?? '');

            require_once __DIR__ . '/../services/PdfService.php';
            require_once __DIR__ . '/../services/EmailService.php';

            $pdfService    = new PdfService();
            $resultadoPdf  = $pdfService->generarPdf($factura, $cliente ?? [], $factura['lineas'] ?? []);

            if (!$resultadoPdf['ok']) {
                Response::error('Error al generar PDF: ' . ($resultadoPdf['error'] ?? 'Error desconocido'));
            }

            $adjunto = $resultadoPdf['ruta'] ?? null;
            if ($adjunto && !file_exists($adjunto)) {
                Response::error('El archivo PDF no existe: ' . $adjunto);
            }

            $emailService = new EmailService();
            $resultado    = $emailService->enviarFactura($factura, $cliente ?? [], $emailDestino, $adjunto);

            if ($resultado['ok']) {
                Response::success($resultado, 'Email enviado correctamente');
            } else {
                Response::error($resultado['error'], 500);
            }
        } catch (Exception $e) {
            Response::serverError('Error al enviar email', $e);
        }
    }

    // -------------------------------------------------------------------------
    // EXPORTAR A EXCEL
    // -------------------------------------------------------------------------

    public function exportar(): void
    {
        try {
            session_write_close();

            require_once __DIR__ . '/../core/DatabaseExport.php';

            $dbExport          = new DatabaseExport();
            $facturaModelExport = new Factura($dbExport);

            $filtros = array_filter([
                'tipo_documento' => $_GET['tipo_documento'] ?? null,
                'id_cliente'     => $_GET['id_cliente']     ?? null,
                'fecha_desde'    => $_GET['fecha_desde']    ?? null,
                'fecha_hasta'    => $_GET['fecha_hasta']    ?? null,
                'id_canal'       => $_GET['id_canal']       ?? null,
                'codigo'         => $_GET['codigo']         ?? null,
            ]);

            $facturas = $facturaModelExport->getAll($filtros);
            $dbExport->close();

            if (empty($facturas)) {
                Response::error('No hay facturas para exportar con los filtros seleccionados', 404);
                return;
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $cabeceras = [
                'Código', 'Id del Canal', 'Numero', 'Fecha', 'Id del cliente',
                'Id forma de pago', 'Observaciones', 'Descuento especial', 'Descuento PP',
                'Descuento comercial', 'Importe bruto', 'Importe dto especial', 'Importe dto PP',
                'Total', 'Direccion', 'Cobrada', 'Fecha cobro', 'Abono',
            ];

            $datos = array_map(function ($f) {
                return [
                    isset($f['Codigo'])     ? (string)$f['Codigo'] : '',
                    $f['Id_canal']          ?? '',
                    $f['Numero']            ?? '',
                    isset($f['Fecha'])      ? (string)$f['Fecha'] : '',
                    $f['Id_Cliente']        ?? '',
                    $f['Id_Forma_Pago']     ?? '',
                    $f['Observaciones']     ?? '',
                    $f['Descuento_Especial']    ?? 0,
                    $f['Descuento_PP']          ?? 0,
                    $f['Descuento_Comercial']   ?? 0,
                    $f['Importe_Bruto']         ?? 0,
                    $f['Importe_Dto_Especial']  ?? 0,
                    $f['Importe_Dto_PP']        ?? 0,
                    (float)($f['Total']         ?? 0),
                    $f['Direccion']             ?? '',
                    $f['Cobrada'] === 'S' ? 'Sí' : 'No',
                    isset($f['Fecha_Cobro']) ? (string)$f['Fecha_Cobro'] : '',
                    $f['Abono']   === 'S' ? 'Sí' : 'No',
                ];
            }, $facturas);

            $excel = \avadim\FastExcelWriter\Excel::create();
            $sheet = $excel->getSheet();

            $formatoEuro = '#,##0.00 €';
            foreach ([7, 8, 9, 10, 11, 12, 13, 14] as $col) {
                $sheet->setColFormat($col, $formatoEuro);
            }

            $anchosColumnas = [12, 12, 10, 12, 15, 15, 30, 14, 14, 14, 14, 14, 14, 14, 30, 10, 12, 10];
            $sheet->setColWidths($anchosColumnas);

            $headStyle = [
                'font'           => ['style' => 'bold', 'color' => '#FFFFFF'],
                'fill'           => '#97b06b',
                'text-align'     => 'center',
                'vertical-align' => 'center',
                'border'         => 'thin',
                'text-wrap'      => true,
                'height'         => 28,
            ];

            $sheet->writeHeader(array_fill_keys($cabeceras, null), $headStyle);

            foreach ($datos as $index => $fila) {
                $rowOptions = $index % 2 === 0 ? ['fill' => '#dcdcdc'] : [];
                $sheet->writeRow($fila, $rowOptions, ['vertical-align' => 'center', 'height' => 20]);
            }

            $nombreArchivo = 'facturas_' . date('Ymd_His') . '.xlsx';
            $rutaTmp       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombreArchivo;
            $excel->save($rutaTmp);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'archivo' => $nombreArchivo]);
        } catch (Exception $e) {
            Response::serverError('Error al exportar facturas', $e);
        }
    }

    // -------------------------------------------------------------------------
    // CREAR / ACTUALIZAR / ELIMINAR
    // -------------------------------------------------------------------------

    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::error('Datos inválidos');
            }

            $errors = Validator::validarFactura($data);
            if (!empty($errors)) {
                Response::validationError($errors);
            }

            $cliente = [];
            if (($data['Tipo_Documento'] ?? '') !== 'SIMPLIFICADA') {
                $cliente = $this->clienteModel->find($data['Id_Cliente']);
                if (!$cliente) {
                    Response::error('Cliente no encontrado');
                    return;
                }

                $nifCliente      = trim((string)($cliente['NIF'] ?? ''));
                $califExportacion = ['N2', 'E2', 'E5'];
                $soloExportacion  = !empty($data['lineas']) && array_reduce(
                    $data['lineas'],
                    fn($ok, $l) => $ok && in_array(strtoupper((string)($l['Calificacion'] ?? 'S1')), $califExportacion, true),
                    true
                );

                if (!$soloExportacion) {
                    if ($nifCliente === '') {
                        Response::json([
                            'success'    => false,
                            'message'    => 'El NIF del cliente es obligatorio para este tipo de operación',
                            'nif_error'  => true,
                            'id_cliente' => $data['Id_Cliente'],
                            'nif_actual' => '',
                        ], 400);
                        return;
                    }
                    $errorNif = Validator::validarFormatoNif($nifCliente);
                    if ($errorNif !== null) {
                        Response::json([
                            'success'    => false,
                            'message'    => "NIF del cliente inválido: {$errorNif}",
                            'nif_error'  => true,
                            'id_cliente' => $data['Id_Cliente'],
                            'nif_actual' => $nifCliente,
                        ], 400);
                        return;
                    }
                }
            }

            $ejercicio  = (int)date('Y', strtotime($data['Fecha']));
            $numeracion = $this->numeracionService->asignarNumero($data['Id_Canal'], $ejercicio, $data['Tipo_Documento']);

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje    = (float)($cliente['RE_Porcentaje'] ?? 0);
            $resultadoCalculo = $this->calculoService->calcular(
                $data['lineas'],
                $descuentos,
                $rePorcentaje,
                $data['Tipo_Documento']
            );

            if (!empty($resultadoCalculo['errores'])) {
                Response::error($resultadoCalculo['errores'][0]);
            }

            $facturaData = [
                'Codigo'               => $numeracion['codigo'],
                'Numero'               => $numeracion['numero'],
                'Id_Canal'             => $data['Id_Canal'],
                'Fecha'                => $data['Fecha'],
                'Id_Cliente'           => $data['Id_Cliente']   ?? null,
                'Id_Forma_Pago'        => $data['Id_Forma_Pago'] ?? null,
                'Tipo_Documento'       => $data['Tipo_Documento'],
                'Abono'                => $data['Tipo_Documento'] === 'RECTIFICATIVA' ? 'S' : 'N',
                'Observaciones'        => $data['Observaciones'] ?? '',
                'Direccion'            => $data['Direccion']     ?? '',
                'Descuento_Especial'   => $descuentos['Descuento_Especial'],
                'Descuento_PP'         => $descuentos['Descuento_PP'],
                'Descuento_Comercial'  => $descuentos['Descuento_Comercial'],
                'Importe_Dto_Especial' => $resultadoCalculo['descuentos']['Importe_Dto_Especial'],
                'Importe_Dto_PP'       => $resultadoCalculo['descuentos']['Importe_Dto_PP'],
                'Importe_Bruto'        => $resultadoCalculo['subtotal'],
                'Base_Imponible'       => $resultadoCalculo['base_imponible'],
                'Cuota_IVA'            => $resultadoCalculo['importe_iva'],
                'Total'                => $resultadoCalculo['total'],
                'Factura_Rectificada_Id' => $data['Id_Factura_Origen'] ?? null,
                'Motivo_Rectificacion'   => $data['Motivo_Rectificacion'] ?? null,
                'Cerrada'              => 'N',
                'Cobrada'              => 'N',
            ];

            $lineasGuardar        = $this->calculoService->generarLineasParaGuardar($data['lineas'], $descuentos, $rePorcentaje);
            $facturaData['lineas'] = $this->normalizarLineasParaInsert($lineasGuardar);

            $codigoCreado = $this->facturaModel->create($facturaData);

            // Si viene de un albarán, marcarlo como facturado
            if (!empty($data['Id_Albaran'])) {
                $this->albaranModel->marcarFacturado((string)$data['Id_Albaran']);
            }

            Response::created([
                'codigo' => $codigoCreado,
                'numero' => $numeracion['numero'],
                'total'  => $resultadoCalculo['total'],
            ], 'Factura creada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al crear factura', $e);
        }
    }

    public function update(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->find($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }
            if ($factura['Cerrada'] === 'S') {
                Response::error('No se puede modificar una factura cerrada');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                Response::error('Datos inválidos');
            }

            if (empty($data['Id_Forma_Pago'])) {
                $data['Id_Forma_Pago'] = $factura['Id_Forma_Pago'] ?? '';
            }
            if (empty($data['Tipo_Documento'])) {
                $data['Tipo_Documento'] = $factura['Tipo_Documento'] ?? 'FACTURA';
            }

            $errors = Validator::validarFactura($data);
            if (!empty($errors)) {
                Response::validationError($errors);
            }

            $cliente = [];
            if (($data['Tipo_Documento'] ?? '') !== 'SIMPLIFICADA') {
                $cliente = $this->clienteModel->find($data['Id_Cliente']);
            }

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje    = (float)($cliente['RE_Porcentaje'] ?? 0);
            $resultadoCalculo = $this->calculoService->calcular(
                $data['lineas'],
                $descuentos,
                $rePorcentaje,
                $data['Tipo_Documento']
            );

            $facturaData = [
                'Fecha'                       => $data['Fecha'],
                'Id_Cliente'                  => $data['Id_Cliente'],
                'Id_Forma_Pago'               => $data['Id_Forma_Pago'],
                'Observaciones'               => $data['Observaciones'] ?? '',
                'Direccion'                   => $data['Direccion']     ?? '',
                'Descuento_Especial'          => $descuentos['Descuento_Especial'],
                'Descuento_PP'                => $descuentos['Descuento_PP'],
                'Descuento_Comercial'         => $descuentos['Descuento_Comercial'],
                'Importe_Dto_Especial'        => $resultadoCalculo['descuentos']['Importe_Dto_Especial'],
                'Importe_Dto_PP'              => $resultadoCalculo['descuentos']['Importe_Dto_PP'],
                'Importe_Dto_Comercial'       => $resultadoCalculo['descuentos']['Importe_Dto_Comercial'],
                'Importe_Bruto'               => $resultadoCalculo['subtotal'],
                'Base_Imponible'              => $resultadoCalculo['base_imponible'],
                'Importe_IVA'                 => $resultadoCalculo['importe_iva'],
                'Importe_RE'                  => $resultadoCalculo['importe_re'],
                'Total'                       => $resultadoCalculo['total'],
                'Numero_Lineas'               => count($data['lineas']),
                'Id_Factura_Origen'           => $data['Id_Factura_Origen']            ?? null,
                'Motivo_Rectificacion'        => $data['Motivo_Rectificacion']         ?? null,
                'Tipo_Rectificativa_Verifactu' => $data['Tipo_Rectificativa_Verifactu'] ?? null,
                'Subtipo_Rectificativa'       => $data['Subtipo_Rectificativa']        ?? null,
                'Base_Rectificada'            => isset($data['Base_Rectificada'])       ? (float)$data['Base_Rectificada']      : null,
                'Cuota_Rectificada'           => isset($data['Cuota_Rectificada'])      ? (float)$data['Cuota_Rectificada']     : null,
                'Cuota_Recargo_Rectificado'   => isset($data['Cuota_Recargo_Rectificado']) ? (float)$data['Cuota_Recargo_Rectificado'] : null,
            ];

            if (isset($data['Cerrada']) && $data['Cerrada'] === 'S') {
                $facturaData['Cerrada']      = 'S';
                $facturaData['Fecha_Cierre'] = date('Y-m-d');
            }

            $facturaData['lineas'] = $this->calculoService->generarLineasParaGuardar($data['lineas'], $descuentos, $rePorcentaje);

            $this->facturaModel->update($codigo, $facturaData);

            Response::success([
                'codigo' => $codigo,
                'total'  => $resultadoCalculo['total'],
            ], 'Factura actualizada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al actualizar factura', $e);
        }
    }

    public function destroy(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->find($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }
            if ($factura['Cerrada'] === 'S') {
                Response::error('La factura ya está cerrada');
            }

            $this->facturaModel->delete($codigo);
            Response::success(['codigo' => $codigo], 'Factura eliminada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al eliminar factura', $e);
        }
    }

    public function cerrar(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->find($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }
            if ($factura['Cerrada'] === 'S') {
                Response::error('La factura ya está cerrada');
            }

            $this->facturaModel->cerrar($codigo);
            Response::success(['codigo' => $codigo], 'Factura cerrada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al cerrar factura', $e);
        }
    }

    // -------------------------------------------------------------------------
    // CÁLCULO (preview)
    // -------------------------------------------------------------------------

    public function calcular(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['lineas'])) {
                Response::error('Se requieren líneas de factura');
            }

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje  = (float)($data['re_porcentaje']  ?? 0);
            $tipoDocumento = $data['Tipo_Documento'] ?? 'FACTURA';

            $resultado = $this->calculoService->calcular($data['lineas'], $descuentos, $rePorcentaje, $tipoDocumento);
            Response::success($resultado);
        } catch (Exception $e) {
            Response::serverError('Error al calcular', $e);
        }
    }

    // -------------------------------------------------------------------------
    // SIMPLIFICADAS / RECAPITULATIVA
    // -------------------------------------------------------------------------

    public function simplificadasPendientes(): void
    {
        $idCanal         = $_GET['id_canal'] ?? null;
        $anio            = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int)$_GET['anio'] : null;
        $mes             = isset($_GET['mes'])  && $_GET['mes']  !== '' ? (int)$_GET['mes']  : null;
        $soloEnVerifactu = !empty($_GET['verifactu']);

        $facturas = $this->facturaModel->findSimplificadasPendientes(
            $idCanal ?: null,
            $anio,
            $mes,
            $soloEnVerifactu
        );

        Response::success(['facturas' => $facturas]);
    }

    public function crearRecapitulativa(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $idCanal       = trim($data['Id_Canal']     ?? '');
            $idCliente     = ($data['Id_Cliente']    ?? '') ?: null;
            $fecha         = trim($data['Fecha']      ?? date('Y-m-d'));
            $idFormaPago   = ($data['Id_Forma_Pago'] ?? '') ?: null;
            $observaciones = $data['Observaciones']  ?? '';
            $anio          = isset($data['anio']) && $data['anio'] !== '' ? (int)$data['anio'] : null;
            $mes           = isset($data['mes'])  && $data['mes']  !== '' ? (int)$data['mes']  : null;
            $codigos       = $data['codigos']       ?? [];

            if ($idCanal === '') {
                Response::error('El canal es obligatorio para crear una recapitulativa.', 422);
                return;
            }
            if (empty($idCliente)) {
                Response::error('El cliente es obligatorio para crear una recapitulativa.', 422);
                return;
            }
            if ($mes === null || $mes < 1 || $mes > 12) {
                Response::error('Debe seleccionar un mes para crear la recapitulativa.', 422);
                return;
            }

            // 1. Cargar simplificadas registradas en Verifactu
            $simplificadas = $this->facturaModel->findSimplificadasPendientes($idCanal, $anio, $mes, true);

            if (!empty($codigos) && is_array($codigos)) {
                $simplificadas = array_values(
                    array_filter($simplificadas, fn($sf) => in_array($sf['Codigo'], $codigos))
                );
            }

            if (empty($simplificadas)) {
                Response::error('No hay facturas simplificadas pendientes de recapitular con esos filtros.', 422);
                return;
            }

            // 2. Construir líneas combinadas
            $lineasFactura = [];
            $lineaNum      = 1;

            foreach ($simplificadas as $sf) {
                $lineas = $this->facturaModel->getLineas($sf['Codigo']);
                foreach ($lineas as $l) {
                    $lineasFactura[] = [
                        'Linea'              => $lineaNum++,
                        'Id_Articulo'        => mb_substr((string)($l['Id_Articulo'] ?? ''), 0, 50),
                        'Descripcion'        => mb_substr((string)($l['Descripcion'] ?? ''), 0, 200),
                        'Cantidad'           => (float)($l['Cantidad']  ?? 1),
                        'Precio'             => (float)($l['Precio']    ?? 0),
                        'Descuento'          => (float)($l['Descuento'] ?? 0),
                        'Id_Tipo_IVA'        => (string)($l['Id_Tipo_IVA'] ?? 'G21'),
                        'Calificacion'       => (string)($l['Calificacion'] ?? 'S1'),
                        '_Id_Factura_Origen' => $sf['Codigo'],
                    ];
                }
            }

            if (empty($lineasFactura)) {
                Response::error('Las facturas simplificadas seleccionadas no tienen líneas.', 422);
                return;
            }

            // 3. Calcular totales
            $lineasCalculo = array_map(fn($l) => [
                'Cantidad'    => $l['Cantidad'],
                'Precio'      => $l['Precio'],
                'Descuento'   => $l['Descuento'],
                'Id_Tipo_IVA' => $l['Id_Tipo_IVA'],
            ], $lineasFactura);

            $calc = $this->calculoService->calcular($lineasCalculo, [], 0, 'RECAPITULATIVA');

            if (!empty($calc['errores'])) {
                Response::error($calc['errores'][0], 422);
                return;
            }

            // 4. Asignar número
            $ejercicio  = (int)date('Y', strtotime($fecha));
            $numeracion = $this->numeracionService->asignarNumero($idCanal, $ejercicio, 'RECAPITULATIVA');

            // 5. Preparar líneas para persistir
            $lineasGuardar = [];
            foreach ($calc['lineas'] as $i => $lc) {
                $orig            = $lineasFactura[$i];
                $lineasGuardar[] = [
                    'Linea'             => (int)$orig['Linea'],
                    'Id_Articulo'       => $orig['Id_Articulo'],
                    'Descripcion'       => $orig['Descripcion'],
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
                ];
            }

            // 6. Persistir en transacción
            $db = Database::getInstance();
            $db->beginTransaction();
            try {
                $db->insert('Facturas_Clientes', [
                    'Codigo'                => $numeracion['codigo'],
                    'Numero'                => $numeracion['numero'],
                    'Id_Canal'              => $idCanal,
                    'Fecha'                 => $fecha,
                    'Id_Cliente'            => $idCliente,
                    'Id_Forma_Pago'         => $idFormaPago,
                    'Tipo_Documento'        => 'RECAPITULATIVA',
                    'Abono'                 => 'N',
                    'Observaciones'         => $observaciones,
                    'Descuento_Especial'    => 0,
                    'Descuento_PP'          => 0,
                    'Descuento_Comercial'   => 0,
                    'Importe_Bruto'         => $calc['subtotal'],
                    'Importe_Dto_Especial'  => 0,
                    'Importe_Dto_Comercial' => 0,
                    'Importe_Dto_PP'        => 0,
                    'Base_Imponible'        => $calc['base_imponible'],
                    'Importe_IVA'           => $calc['importe_iva'],
                    'Importe_RE'            => $calc['importe_re'],
                    'Total'                 => $calc['total'],
                    'Numero_Lineas'         => count($lineasGuardar),
                    'Cerrada'               => 'N',
                    'Cobrada'               => 'N',
                    'Liquidada'             => 'N',
                    'Contabilizada'         => 'N',
                    'Impresa'               => 'N',
                    'Fecha_Alta'            => date('Y-m-d H:i:s'),
                    'Usuario_Alta'          => $_SESSION['usuario'] ?? 'sistema',
                    'Ultima_Modificacion'   => date('Y-m-d H:i:s'),
                ]);

                foreach ($lineasGuardar as $linea) {
                    $linea['Id_Factura'] = $numeracion['codigo'];
                    $db->insert('Lineas_Facturas_Clientes', $linea);
                }

                foreach ($simplificadas as $sf) {
                    $db->insert('Facturas_Sustituidas', [
                        'Id_Recapitulativa' => $numeracion['codigo'],
                        'Id_Simplificada'   => $sf['Codigo'],
                    ]);
                }

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Response::created([
                'codigo' => $numeracion['codigo'],
                'numero' => $numeracion['numero'],
                'total'  => $calc['total'],
            ], 'Factura recapitulativa creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Exception $e) {
            Response::serverError('Error al crear la factura recapitulativa', $e);
        }
    }

    // -------------------------------------------------------------------------
    // CONVERSIÓN DESDE ALBARANES
    // -------------------------------------------------------------------------

    public function convertirDesdeAlbaranes(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['albaranes'])) {
                Response::error('Se requiere al menos un albarán con sus líneas.');
                return;
            }

            $idFormaPago = trim($data['Id_Forma_Pago'] ?? '');
            if (empty($idFormaPago)) {
                Response::error('Debe seleccionar una forma de pago antes de crear la factura.', 422);
                return;
            }

            $fecha = trim($data['Fecha'] ?? '');
            if (empty($fecha)) {
                Response::error('La fecha de la factura es obligatoria.', 422);
                return;
            }

            $conversionService = new ConversionService();
            $resultado         = $conversionService->convertir($data);

            Response::created($resultado, 'Factura creada correctamente desde albaranes.');
        } catch (NifInvalidoException $e) {
            Response::json([
                'success'    => false,
                'message'    => $e->getMessage(),
                'nif_error'  => true,
                'id_cliente' => $e->idCliente,
                'nif_actual' => $e->nifActual,
            ], 422);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Exception $e) {
            Response::serverError('Error al convertir albaranes en factura', $e);
        }
    }

    // -------------------------------------------------------------------------
    // VERIFACTU
    // -------------------------------------------------------------------------

    public function firmar(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->findWithLines($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }

            $registro = $this->verifactuRegistro->findByDocumento('FACTURA', $codigo);
            if ($registro && $registro['Estado_Envio'] === 'ENVIADO') {
                Response::error('La factura ya está enviada a Hacienda');
            }

            if ($factura['Id_Cliente']) {
                $factura['cliente'] = $this->clienteModel->find($factura['Id_Cliente']);
            }

            $datos    = $this->prepararDatosVerifactu($factura);
            $resultado = $this->verifactu->firmar($datos);

            if (!$resultado['ok']) {
                Response::error('Error al firmar: ' . $resultado['error']);
            }

            $this->guardarRegistro($factura, $resultado, 'GENERADO');

            Response::success([
                'codigo' => $codigo,
                'huella' => $resultado['huella'],
                'numero' => $resultado['numero'],
                'fecha'  => $resultado['fecha'],
            ], 'Factura firmada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al firmar factura', $e);
        }
    }

    public function enviar(string $codigo): void
    {
        try {
            $factura = $this->facturaModel->findWithLines($codigo);

            if (!$factura) {
                Response::notFound('Factura no encontrada');
            }

            if ($factura['Id_Cliente']) {
                $factura['cliente'] = $this->clienteModel->find($factura['Id_Cliente']);
            }

            $datos    = $this->prepararDatosVerifactu($factura);
            $resultado = $this->verifactu->enviar($datos);

            if (!$resultado['ok']) {
                $this->guardarRegistro($factura, [
                    'huella' => $resultado['huella'] ?? '',
                    'error'  => $resultado['error'],
                ], 'ERROR');
                Response::error('Error al enviar a Hacienda: ' . $resultado['error']);
            }

            $this->guardarRegistro($factura, $resultado, 'ENVIADO');

            Response::success([
                'codigo'          => $codigo,
                'csv'             => $resultado['csv'],
                'huella'          => $resultado['huella'],
                'fecha_registro'  => $resultado['fecha_registro'] ?? date('Y-m-d H:i:s'),
            ], 'Factura enviada a Hacienda correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al enviar factura', $e);
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVADOS
    // -------------------------------------------------------------------------

    private function prepararDatosVerifactu(array $factura): array
    {
        $serie = strlen($factura['Codigo']) >= 4 ? substr($factura['Codigo'], 0, 2) : '';

        $tipoDocumento = match ($factura['Tipo_Documento']) {
            'FACTURA', 'SIMPLIFICADA', 'RECTIFICATIVA', 'RECAPITULATIVA' => $factura['Tipo_Documento'],
            default => 'FACTURA',
        };

        $clienteNif    = $factura['cliente']['NIF']           ?? '';
        $clienteNombre = $factura['cliente']['Nombre']        ?? $factura['cliente']['Razon_Social'] ?? '';

        $lineas = [];
        foreach ($factura['lineas'] ?? [] as $linea) {
            $reCuota  = (float)($linea['RE'] ?? 0);
            $aplicaRE = $reCuota > 0;
            $lineas[] = [
                'descripcion'      => $linea['Descripcion'] ?? $linea['Id_Articulo'],
                'cantidad'         => (float)($linea['Cantidad']      ?? 1),
                'precio'           => (float)($linea['Precio']        ?? 0),
                'base_imponible'   => (float)($linea['Base_Imponible'] ?? 0),
                'iva_porcentaje'   => (float)($linea['tipo_iva_pct']  ?? 21),
                'iva_cuota'        => 0,
                'codigo_verifactu' => (string)($linea['codigo_verifactu'] ?? ''),
                'tipo_territorio'  => (string)($linea['tipo_territorio']  ?? ''),
                're_porcentaje'    => $aplicaRE ? (float)($linea['tipo_re_pct'] ?? 0) : 0,
                're_cuota'         => $reCuota,
                'clave_regimen'    => $aplicaRE ? '18' : '01',
            ];
        }

        return [
            'serie'          => $serie,
            'numero'         => $factura['Numero'],
            'fecha'          => $factura['Fecha'],
            'tipo_documento' => $tipoDocumento,
            'descripcion'    => 'Factura electrónica',
            'base_imponible' => (float)$factura['Base_Imponible'],
            'importe_iva'    => (float)$factura['Importe_IVA'],
            'total'          => (float)$factura['Total'],
            'cliente'        => ['nif' => $clienteNif, 'razon_social' => $clienteNombre],
            'lineas'         => $lineas,
        ];
    }

    private function guardarRegistro(array $factura, array $resultado, string $estado): void
    {
        $this->verifactuRegistro->create([
            'Id_Documento'   => $factura['Codigo'],
            'Tipo_Origen'    => 'FACTURA',
            'Serie'          => substr($factura['Codigo'], 0, 2),
            'Numero'         => $factura['Numero'],
            'Fecha_Generacion' => date('Y-m-d H:i:s'),
            'Factura_Total'  => $factura['Total'],
            'Cliente_NIF'    => $factura['cliente']['NIF']    ?? '',
            'Cliente_Nombre' => $factura['cliente']['Nombre'] ?? '',
            'Huella'         => $resultado['huella']          ?? '',
            'CSV_Hacienda'   => $resultado['csv']             ?? '',
            'Estado_Envio'   => $estado,
            'Mensaje_Error'  => $resultado['error']           ?? '',
        ]);
    }

    private function normalizarLineasParaInsert(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $l) {
            $row = [
                'Linea'             => (int)($l['Linea']      ?? 0),
                'Id_Articulo'       => (string)($l['Id_Articulo']  ?? ''),
                'Descripcion'       => (string)($l['Descripcion']  ?? ''),
                'Cantidad'          => (float)($l['Cantidad']   ?? 0),
                'Precio'            => (float)($l['Precio']     ?? 0),
                'Descuento'         => (float)($l['Descuento']  ?? 0),
                'Id_Tipo_IVA'       => (string)($l['Id_Tipo_IVA']  ?? ''),
                'Importe_Bruto'     => (float)($l['Importe_Bruto']     ?? 0),
                'Importe_Descuento' => (float)($l['Importe_Descuento'] ?? 0),
                'Base_Imponible'    => (float)($l['Base_Imponible']    ?? 0),
                'Total'             => (float)($l['Total']      ?? 0),
                'RE'                => (float)($l['RE']         ?? 0),
                'Aplica_RE'         => ($l['Aplica_RE'] ?? 0) ? 'S' : 'N',
                'Calificacion'      => (string)($l['Calificacion']  ?? 'S1'),
                'Clave_Regimen'     => (string)($l['Clave_Regimen'] ?? '01'),
            ];

            if ($row['Linea'] <= 0 || $row['Id_Articulo'] === '' || $row['Cantidad'] <= 0) {
                continue;
            }

            $out[] = $row;
        }
        return $out;
    }
}
