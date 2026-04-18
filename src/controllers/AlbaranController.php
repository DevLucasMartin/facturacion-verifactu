<?php

/**
 * Controlador de Albaranes
 * Módulo de Facturación
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/Albaran.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../services/CalculoService.php';
require_once __DIR__ . '/../services/NumeracionService.php';

class AlbaranController
{
    private Albaran $albaranModel;
    private Cliente $clienteModel;
    private CalculoService $calculoService;
    private NumeracionService $numeracionService;

    public function __construct()
    {
        $this->albaranModel      = new Albaran();
        $this->clienteModel      = new Cliente();
        $this->calculoService    = new CalculoService();
        $this->numeracionService = new NumeracionService();
    }

    /**
     * Listar albaranes con paginación
     */
    public function index(): void
    {
        try {
            $page       = (int)($_GET['page']      ?? 1);
            $perPage    = min((int)($_GET['per_page']  ?? 25), 100);
            $idCliente  = $_GET['id_cliente']  ?? null;
            $fechaDesde = $_GET['fecha_desde'] ?? null;
            $fechaHasta = $_GET['fecha_hasta'] ?? null;
            $idCanal    = $_GET['id_canal']    ?? null;
            $facturado  = $_GET['facturado']   ?? null;
            $cerrado    = $_GET['cerrado']     ?? null;
            $codigo     = $_GET['codigo']      ?? null;

            $filtros = array_filter([
                'id_cliente'  => $idCliente,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'id_canal'    => $idCanal,
                'facturado'   => $facturado,
                'cerrado'     => $cerrado,
                'codigo'      => $codigo,
            ]);

            $result = $this->albaranModel->paginate($page, $perPage, $filtros);

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
        } catch (Exception $e) {
            Response::serverError('Error al listar albaranes', $e);
        }
    }

    /**
     * Exportar albaranes a Excel
     */
    public function exportar(): void
    {
        try {
            session_write_close();

            $filtros = array_filter([
                'id_cliente'  => $_GET['id_cliente']  ?? null,
                'fecha_desde' => $_GET['fecha_desde'] ?? null,
                'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
                'id_canal'    => $_GET['id_canal']    ?? null,
                'codigo'      => $_GET['codigo']      ?? null,
            ]);

            require_once __DIR__ . '/../core/DatabaseExport.php';
            $dbExport          = new DatabaseExport();
            $albaranModelExport = new Albaran($dbExport);

            $albaranes = $albaranModelExport->getAll($filtros);
            $dbExport->close();

            if (empty($albaranes)) {
                Response::error('No hay albaranes para exportar con los filtros seleccionados', 404);
                return;
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $cabeceras = [
                'Codigo', 'Id canal', 'Numero', 'Fecha', 'Id del cliente',
                'Id forma de pago', 'Fecha alta', 'Fecha modificacion',
                'Usuario alta', 'Usuario ultima modificacion',
                'Importe bruto', 'Base imponible',
                'Cerrado', 'Facturado', 'Direccion', 'Cobrado', 'Fecha cobro',
            ];

            $datos = array_map(function ($f) {
                // En MySQL/PDO las fechas vienen como strings — normalizar a Y-m-d
                $normalizar = fn($v) => $v ? substr((string)$v, 0, 10) : '';

                return [
                    (string)($f['Codigo']                     ?? ''),
                    $f['Id_Canal']                            ?? '',
                    $f['Numero']                              ?? '',
                    $normalizar($f['Fecha']                   ?? null),
                    $f['Id_Cliente']                          ?? '',
                    $f['Id_Forma_Pago']                       ?? '',
                    $normalizar($f['Fecha_Alta']              ?? null),
                    $normalizar($f['Ultima_modificacion']     ?? null),
                    $f['Usuario_Alta']                        ?? '',
                    $f['Usuario_Ultima_Modificacion']         ?? '',
                    (float)($f['Importe_Bruto']               ?? 0),
                    (float)($f['Base_Imponible']              ?? 0),
                    ($f['Cerrado']   ?? '') === 'S' ? 'Sí' : 'No',
                    ($f['Facturado'] ?? '') === 'S' ? 'Sí' : 'No',
                    $f['Direccion']                           ?? '',
                    ($f['Cobrado']   ?? '') === 'S' ? 'Sí' : 'No',
                    $normalizar($f['Fecha_Cobro']             ?? null),
                ];
            }, $albaranes);

            $excel = \avadim\FastExcelWriter\Excel::create();
            $sheet = $excel->getSheet();

            foreach ([11, 12] as $col) {
                $sheet->setColFormat($col, '#,##0.00 €');
            }

            $anchosColumnas = [12, 12, 10, 12, 15, 15, 12, 18, 15, 22, 14, 14, 10, 10, 30, 10, 12];
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

            $nombreArchivo = 'albaranes_' . date('Ymd_His') . '.xlsx';
            $rutaTmp       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombreArchivo;
            $excel->save($rutaTmp);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'archivo' => $nombreArchivo]);
        } catch (Exception $e) {
            Response::serverError('Error al exportar albaranes', $e);
        }
    }

    /**
     * Ver un albarán específico
     */
    public function show(string $codigo): void
    {
        try {
            $albaran = $this->albaranModel->findWithLines($codigo);

            if (!$albaran) {
                Response::notFound('Albarán no encontrado');
            }

            if ($albaran['Id_Cliente']) {
                $albaran['cliente'] = $this->clienteModel->find($albaran['Id_Cliente']);
            }

            Response::success($albaran);
        } catch (Exception $e) {
            Response::serverError('Error al obtener albarán', $e);
        }
    }

    /**
     * Generar PDF de un albarán
     */
    public function generarPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';

            $pdfService = new PdfService('ALBARAN');
            $resultado  = $pdfService->descargarPdfAlbaran($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }

            Response::success($resultado);
        } catch (Exception $e) {
            Response::serverError('Error al generar PDF', $e);
        }
    }

    /**
     * Ver PDF en pantalla
     */
    public function verPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';

            $pdfService = new PdfService('ALBARAN');
            $resultado  = $pdfService->descargarPdfAlbaran($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }

            $ruta    = $resultado['ruta'];
            $formato = $resultado['formato'] ?? 'docx';

            $mimeTypes = [
                'pdf'  => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            header('Content-Type: ' . ($mimeTypes[$formato] ?? 'application/octet-stream'));
            header('Content-Disposition: inline; filename="albaran_' . $codigo . '.' . $formato . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;
        } catch (Exception $e) {
            Response::serverError('Error al ver PDF', $e);
        }
    }

    /**
     * Descargar PDF
     */
    public function descargarPdf(string $codigo): void
    {
        try {
            require_once __DIR__ . '/../services/PdfService.php';

            $pdfService = new PdfService('ALBARAN');
            $resultado  = $pdfService->descargarPdfAlbaran($codigo);

            if (!$resultado['ok']) {
                Response::error($resultado['error'], 500);
            }

            $ruta    = $resultado['ruta'];
            $formato = $resultado['formato'] ?? 'docx';

            $mimeTypes = [
                'pdf'  => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            header('Content-Type: ' . ($mimeTypes[$formato] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="albaran_' . $codigo . '.' . $formato . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;
        } catch (Exception $e) {
            Response::serverError('Error al descargar PDF', $e);
        }
    }

    /**
     * Enviar albarán por email
     */
    public function enviarEmail(string $codigo): void
    {
        try {
            $data         = json_decode(file_get_contents('php://input'), true);
            $emailDestino = $data['email'] ?? null;
            $proforma     = !empty($data['proforma']);

            if (!$emailDestino) {
                Response::error('Email de destino requerido');
            }

            $albaran = $this->albaranModel->findWithLines($codigo);
            if (!$albaran) {
                Response::notFound('Albarán no encontrado');
            }

            $cliente = $this->clienteModel->find($albaran['Id_Cliente'] ?? '');

            require_once __DIR__ . '/../services/EmailService.php';
            require_once __DIR__ . '/../services/PdfService.php';

            if ($proforma) {
                $albaran['_proforma'] = true;
            }

            $pdfService   = new PdfService('ALBARAN');
            $resultadoPdf = $pdfService->generarPdf($albaran, $cliente ?? [], $albaran['lineas'] ?? []);

            $adjunto = $resultadoPdf['ok'] ? ($resultadoPdf['ruta'] ?? null) : null;

            $emailService = new EmailService();
            $resultado    = $emailService->enviarFactura($albaran, $cliente ?? [], $emailDestino, $adjunto);

            if ($resultado['ok']) {
                Response::success($resultado, 'Email enviado correctamente');
            } else {
                Response::error($resultado['error'], 500);
            }
        } catch (Exception $e) {
            Response::serverError('Error al enviar email', $e);
        }
    }

    /**
     * Crear un nuevo albarán
     */
    public function store(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::error('Datos inválidos');
            }

            $errors = Validator::validarAlbaran($data);
            if (empty($data['Id_Cliente'])) {
                $errors = array_values(array_filter($errors, function ($e) {
                    $text = is_array($e) ? implode(' ', $e) : (string)$e;
                    return stripos($text, 'cliente') === false && stripos($text, 'Id_Cliente') === false;
                }));
            }

            if (!empty($errors)) {
                Response::validationError($errors);
            }

            $cliente = null;
            if (!empty($data['Id_Cliente'])) {
                $cliente = $this->clienteModel->find($data['Id_Cliente']);
                if (!$cliente) {
                    Response::error('Cliente no encontrado');
                }
            }

            $ejercicio  = (int)date('Y', strtotime($data['Fecha']));
            $numeracion = $this->numeracionService->asignarNumero($data['Id_Canal'], $ejercicio, 'ALBARAN');

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje     = (float)($cliente['RE_Porcentaje'] ?? 0);
            $resultadoCalculo = $this->calculoService->calcular(
                $data['lineas'],
                $descuentos,
                $rePorcentaje,
                'ALBARAN'
            );

            if (!empty($resultadoCalculo['errores'])) {
                Response::error($resultadoCalculo['errores'][0]);
            }

            $albaranData = [
                'Codigo'                => $numeracion['codigo'],
                'Numero'                => $numeracion['numero'],
                'Id_Canal'              => $data['Id_Canal'],
                'Fecha'                 => $data['Fecha'],
                'Id_Cliente'            => $data['Id_Cliente'],
                'Id_Forma_Pago'         => $data['Id_Forma_Pago'],
                'Observaciones'         => $data['Observaciones']       ?? '',
                'Direccion'             => $data['Direccion']           ?? '',
                'Descuento_Especial'    => $descuentos['Descuento_Especial'],
                'Descuento_PP'          => $descuentos['Descuento_PP'],
                'Descuento_Comercial'   => $descuentos['Descuento_Comercial'],
                'Importe_Dto_Especial'  => $resultadoCalculo['descuentos']['Importe_Dto_Especial'],
                'Importe_Dto_PP'        => $resultadoCalculo['descuentos']['Importe_Dto_PP'],
                'Importe_Dto_Comercial' => $resultadoCalculo['descuentos']['Importe_Dto_Comercial'],
                'Importe_Bruto'         => $resultadoCalculo['subtotal'],
                'Base_Imponible'        => $resultadoCalculo['base_imponible'],
                'Importe_IVA'           => $resultadoCalculo['importe_iva'],
                'Total'                 => $resultadoCalculo['total'],
                'Cerrado'               => 'N',
                'Facturado'             => 'N',
                'Cobrado'               => 'N',
                'Impreso'               => 'N',
                'Es_Plantilla'          => ($data['Es_Plantilla'] ?? '') === 'S' ? 'S' : 'N',
                'Nombre_Plantilla'      => ($data['Nombre_Plantilla'] ?? null) ?: null,
            ];

            $lineasAlbaran = $this->calculoService->generarLineasParaGuardar(
                $data['lineas'],
                $descuentos,
                $rePorcentaje
            );
            $albaranData['lineas'] = array_map(function ($l) {
                unset($l['RE'], $l['Aplica_RE']);
                return $l;
            }, $lineasAlbaran);

            $codigoCreado = $this->albaranModel->create($albaranData);

            Response::created([
                'codigo' => $codigoCreado,
                'numero' => $numeracion['numero'],
                'total'  => $resultadoCalculo['total'],
            ], 'Albarán creado correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al crear albarán', $e);
        }
    }

    /**
     * Actualizar un albarán
     */
    public function update(string $codigo): void
    {
        try {
            $albaran = $this->albaranModel->find($codigo);

            if (!$albaran) {
                Response::notFound('Albarán no encontrado');
            }

            if ($albaran['Cerrado'] === 'S') {
                Response::error('No se puede modificar un albarán cerrado');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::error('Datos inválidos');
            }

            if (empty($data['Id_Forma_Pago'])) {
                $data['Id_Forma_Pago'] = $albaran['Id_Forma_Pago'] ?? '';
            }

            $errors = Validator::validarAlbaran($data);
            if (empty($data['Id_Cliente'])) {
                $errors = array_values(array_filter($errors, function ($e) {
                    $text = is_array($e) ? implode(' ', $e) : (string)$e;
                    return stripos($text, 'cliente') === false && stripos($text, 'Id_Cliente') === false;
                }));
            }

            if (!empty($errors)) {
                Response::validationError($errors);
            }

            $cliente = null;
            if (!empty($data['Id_Cliente'])) {
                $cliente = $this->clienteModel->find($data['Id_Cliente']);
            }

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje     = (float)($cliente['RE_Porcentaje'] ?? 0);
            $resultadoCalculo = $this->calculoService->calcular(
                $data['lineas'],
                $descuentos,
                $rePorcentaje,
                'ALBARAN'
            );

            $albaranData = [
                'Fecha'                 => $data['Fecha'],
                'Id_Cliente'            => $data['Id_Cliente'],
                'Id_Forma_Pago'         => $data['Id_Forma_Pago'],
                'Observaciones'         => $data['Observaciones']       ?? '',
                'Direccion'             => $data['Direccion']           ?? '',
                'Descuento_Especial'    => $descuentos['Descuento_Especial'],
                'Descuento_PP'          => $descuentos['Descuento_PP'],
                'Descuento_Comercial'   => $descuentos['Descuento_Comercial'],
                'Importe_Dto_Especial'  => $resultadoCalculo['descuentos']['Importe_Dto_Especial'],
                'Importe_Dto_PP'        => $resultadoCalculo['descuentos']['Importe_Dto_PP'],
                'Importe_Dto_Comercial' => $resultadoCalculo['descuentos']['Importe_Dto_Comercial'],
                'Importe_Bruto'         => $resultadoCalculo['subtotal'],
                'Base_Imponible'        => $resultadoCalculo['base_imponible'],
                'Importe_IVA'           => $resultadoCalculo['importe_iva'],
                'Total'                 => $resultadoCalculo['total'],
                'Numero_Lineas'         => count($data['lineas']),
            ];

            if (isset($data['Cerrado']) && $data['Cerrado'] === 'S') {
                $albaranData['Cerrado']      = 'S';
                $albaranData['Fecha_Cierre'] = date('Y-m-d');
            }

            $lineasAlbaran = $this->calculoService->generarLineasParaGuardar(
                $data['lineas'],
                $descuentos,
                $rePorcentaje
            );
            $albaranData['lineas'] = array_map(function ($l) {
                unset($l['RE'], $l['Aplica_RE']);
                return $l;
            }, $lineasAlbaran);

            $this->albaranModel->update($codigo, $albaranData);

            Response::success([
                'codigo' => $codigo,
                'total'  => $resultadoCalculo['total'],
            ], 'Albarán actualizado correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al actualizar albarán', $e);
        }
    }

    /**
     * Eliminar un albarán
     */
    public function destroy(string $codigo): void
    {
        try {
            $albaran = $this->albaranModel->find($codigo);

            if (!$albaran) {
                Response::notFound('Albarán no encontrado');
            }

            if ($albaran['Cerrado'] === 'S') {
                Response::error('No se puede eliminar un albarán cerrado');
            }

            $this->albaranModel->delete($codigo);

            Response::success(['codigo' => $codigo], 'Albarán eliminado correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al eliminar albarán', $e);
        }
    }

    /**
     * Buscar albaranes
     */
    public function search(): void
    {
        try {
            $query = $_GET['q'] ?? '';

            if (strlen($query) < 2) {
                Response::error('La búsqueda debe tener al menos 2 caracteres');
            }

            $result = $this->albaranModel->findByCodigo($query);

            Response::success($result);
        } catch (Exception $e) {
            Response::serverError('Error en la búsqueda', $e);
        }
    }

    /**
     * Calcular totales (preview)
     */
    public function calcular(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || empty($data['lineas'])) {
                Response::error('Se requieren líneas de albarán');
            }

            $descuentos = [
                'Descuento_Especial'  => $data['Descuento_Especial']  ?? 0,
                'Descuento_PP'        => $data['Descuento_PP']        ?? 0,
                'Descuento_Comercial' => $data['Descuento_Comercial'] ?? 0,
            ];

            $rePorcentaje = (float)($data['re_porcentaje'] ?? 0);
            $resultado    = $this->calculoService->calcular(
                $data['lineas'],
                $descuentos,
                $rePorcentaje,
                'ALBARAN'
            );

            Response::success($resultado);
        } catch (Exception $e) {
            Response::serverError('Error al calcular', $e);
        }
    }

    /**
     * Obtener estadísticas
     */
    public function estadisticas(): void
    {
        try {
            $ejercicio = isset($_GET['ejercicio']) ? (int)$_GET['ejercicio'] : null;
            $stats     = $this->albaranModel->getEstadisticas($ejercicio);
            Response::success($stats);
        } catch (Exception $e) {
            Response::serverError('Error al obtener estadísticas', $e);
        }
    }

    /**
     * Listar plantillas
     */
    public function plantillas(): void
    {
        try {
            Response::success($this->albaranModel->getPlantillas());
        } catch (Exception $e) {
            Response::serverError('Error al obtener plantillas', $e);
        }
    }

    /**
     * Marcar un albarán como plantilla
     */
    public function guardarComoPlantilla(string $codigo): void
    {
        try {
            $data   = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($data['nombre'] ?? '');

            if ($nombre === '') {
                Response::error('El nombre de la plantilla es obligatorio');
            }

            $albaran = $this->albaranModel->find($codigo);
            if (!$albaran) {
                Response::notFound('Albarán no encontrado');
            }

            $this->albaranModel->marcarComoPlantilla($codigo, $nombre);
            Response::success(['codigo' => $codigo, 'nombre' => $nombre], 'Plantilla guardada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al guardar plantilla', $e);
        }
    }

    /**
     * Eliminar una plantilla
     */
    public function eliminarPlantilla(string $codigo): void
    {
        try {
            $this->albaranModel->eliminarPlantilla($codigo);
            Response::success(['codigo' => $codigo], 'Plantilla eliminada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al eliminar plantilla', $e);
        }
    }

    /**
     * Renombrar una plantilla
     */
    public function renombrarPlantilla(string $codigo): void
    {
        try {
            $data   = json_decode(file_get_contents('php://input'), true);
            $nombre = trim($data['nombre'] ?? '');

            if ($nombre === '') {
                Response::error('El nombre de la plantilla es obligatorio');
            }

            $this->albaranModel->renombrarPlantilla($codigo, $nombre);
            Response::success(['codigo' => $codigo, 'nombre' => $nombre], 'Plantilla renombrada correctamente');
        } catch (Exception $e) {
            Response::serverError('Error al renombrar plantilla', $e);
        }
    }

    /**
     * Obtener siguiente número (preview para el formulario)
     */
    public function siguienteNumero(): void
    {
        try {
            $idCanal   = $_GET['canal'] ?? 'R5';
            $fecha     = $_GET['fecha'] ?? date('Y-m-d');
            $ejercicio = (int)date('Y', strtotime($fecha));

            $codigo = $this->numeracionService->getSiguienteCodigo($idCanal, $ejercicio, 'ALBARAN');
            $numero = $this->numeracionService->getSiguienteNumero($idCanal, $ejercicio, 'ALBARAN');

            Response::success([
                'codigo'    => $codigo,
                'numero'    => $numero,
                'canal'     => $idCanal,
                'ejercicio' => $ejercicio,
            ]);
        } catch (Exception $e) {
            Response::serverError('Error al obtener número', $e);
        }
    }
}
