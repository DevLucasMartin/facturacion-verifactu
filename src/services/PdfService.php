<?php
/**
 * Servicio de PDF para facturas
 * Módulo de Facturación - Versión 3.2
 *
 * Genera PDFs usando PhpWord + Gotenberg (LibreOffice).
 * Requiere: composer require phpoffice/phpword guzzlehttp/guzzle
 */

require_once __DIR__ . '/../config/empresa.php';

class PdfService
{
    private string $templatePath;
    private string $outputPath;
    private array  $empresa;
    private string $documentoTipo;

    public function __construct(string $documentoTipo = 'FACTURA')
    {
        $this->documentoTipo  = strtoupper($documentoTipo);
        $this->templatePath   = $this->resolverTemplatePath($this->documentoTipo);
        $this->outputPath     = __DIR__ . '/../storage/pdf';
        $this->empresa        = require __DIR__ . '/../config/empresa.php';

        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /** Generar PDF de una factura/albarán. */
    public function generarPdf(array $documento, array $cliente, array $lineas): array
    {
        try {
            $tipo = strtoupper($documento['Tipo_Documento'] ?? '');
            if ($tipo !== '' && $tipo !== $this->documentoTipo) {
                $this->documentoTipo = $tipo;
                $this->templatePath  = $this->resolverTemplatePath($tipo);
            }

            if (!file_exists($this->templatePath)) {
                return ['ok' => false, 'error' => 'Template de factura no encontrado: ' . $this->templatePath];
            }

            $datos    = $this->prepararDatos($documento, $cliente, $lineas);
            $docxPath = $this->generarDocx($datos, $lineas);

            if (!$docxPath) {
                return ['ok' => false, 'error' => 'Error al generar documento Word'];
            }

            $pdfPath = $this->convertirPdf($docxPath);

            if (!$pdfPath) {
                return ['ok' => true, 'ruta' => $docxPath, 'formato' => 'docx', 'mensaje' => 'PDF no disponible, se entrega en Word'];
            }

            return ['ok' => true, 'ruta' => $pdfPath, 'formato' => 'pdf'];

        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function resolverTemplatePath(string $tipo): string
    {
        return match ($tipo) {
            'ALBARAN'      => __DIR__ . '/../templates/docs/Albaran_template.docx',
            'PROFORMA'     => __DIR__ . '/../templates/docs/Proforma_template.docx',
            'SIMPLIFICADA' => __DIR__ . '/../templates/docs/Simplificada_template.docx',
            default        => __DIR__ . '/../templates/docs/factura_template.docx',
        };
    }

    private function prepararDatos(array $documento, array $cliente, array $lineas): array
    {
        $esAlbaran = $this->documentoTipo === 'ALBARAN';
        $dtoTotal = ($documento['Importe_Bruto'] ?? 0) - ($documento['Base_Imponible'] ?? 0);
        if ($dtoTotal < 0) $dtoTotal = 0;

        $empresaDir = $this->empresa['direccion'] ?? [];

        $empresa = [
            'EMPRESA_RAZON_SOCIAL' => $this->empresa['razon_social'] ?? '',
            'EMPRESA_NIF'          => $this->empresa['nif']           ?? '',
            'EMPRESA_DIRECCION'    => is_array($empresaDir) ? ($empresaDir['direccion']     ?? '') : ($empresaDir ?? ''),
            'EMPRESA_CP'           => is_array($empresaDir) ? ($empresaDir['codigo_postal'] ?? '') : '',
            'EMPRESA_LOCALIDAD'    => is_array($empresaDir) ? ($empresaDir['poblacion']     ?? '') : '',
            'EMPRESA_PROVINCIA'    => is_array($empresaDir) ? ($empresaDir['provincia']     ?? '') : '',
            'EMPRESA_TELEFONO'     => $this->empresa['telefono'] ?? '',
            'EMPRESA_EMAIL'        => $this->empresa['email']    ?? '',
            'EMPRESA_WEB'          => $this->empresa['web']      ?? '',
        ];

        if ($esAlbaran) {
            $esProforma = !empty($documento['_proforma']);
            if ($esProforma) {
                $this->documentoTipo = 'PROFORMA';
                $this->templatePath  = $this->resolverTemplatePath('PROFORMA');
            }
            $documentoData = [
                'ALBARAN_NUMERO' => ($documento['Id_Canal'] ?? '') . ($documento['Numero'] ?? ''),
                'ALBARAN_FECHA'  => $this->formatDate($documento['Fecha'] ?? date('Y-m-d')),
                'ALBARAN_SERIE'  => $documento['Id_Canal'] ?? 'A',
                'FACTURA_TIPO'   => $esProforma ? 'PROFORMA' : 'ALBARÁN',
                'LUGAR_ENTREGA'  => $documento['Direccion']   ?? '',
                'FECHA_ENTREGA'  => $documento['Fecha_Cobro'] ?? '',
            ];
        } else {
            $documentoData = [
                'FACTURA_NUMERO' => ($documento['Id_Canal'] ?? '') . ($documento['Numero'] ?? ''),
                'FACTURA_FECHA'  => $this->formatDate($documento['Fecha'] ?? date('Y-m-d')),
                'FACTURA_CANAL'  => $documento['Id_Canal'] ?? 'F',
                'FACTURA_TIPO'   => $this->getTipoLabel($documento['Tipo_Documento'] ?? 'FACTURA'),
                'FACTURA_TITULO' => strtoupper($this->getTipoLabel($documento['Tipo_Documento'] ?? 'FACTURA')),
            ];
        }

        $nombreCompleto = trim($cliente['Archivar_Como'] ?? '');
        if ($nombreCompleto === '') {
            $nombreCompleto = $cliente['NIF'] ?? '';
        }

        $clienteData = [
            'CLIENTE_NOMBRE'       => $nombreCompleto,
            'CLIENTE_ORGANIZACION' => '',
            'CLIENTE_NIF'          => $cliente['NIF']           ?? '',
            'CLIENTE_DIRECCION'    => $cliente['Direccion']     ?? '',
            'CLIENTE_CP'           => $cliente['CP']            ?? '',
            'CLIENTE_LOCALIDAD'    => $cliente['Poblacion']     ?? '',
            'CLIENTE_PROVINCIA'    => $cliente['Provincia']     ?? '',
        ];

        $importeRE = array_sum(array_column($lineas, 'RE'));

        $totales = [
            'IMPORTE_BRUTO'   => $this->formatCurrency($documento['Importe_Bruto']   ?? 0),
            'IMPORTE_DTO'     => $dtoTotal > 0 ? $this->formatCurrency($dtoTotal)  : '0,00 €',
            'BASE_IMPONIBLE'  => $this->formatCurrency($documento['Base_Imponible'] ?? 0),
            'IMPORTE_IVA'     => $this->formatCurrency($documento['Cuota_IVA']      ?? 0),
            'IMPORTE_RE'      => $importeRE > 0 ? $this->formatCurrency($importeRE) : '0,00 €',
            'TOTAL'           => $this->formatCurrency($documento['Total'] ?? 0),
        ];

        $extras = [
            'OBSERVACIONES'   => $documento['Observaciones']    ?? '',
            'FORMA_PAGO'      => $documento['Id_Forma_Pago']    ?? '',
            'DATOS_BANCARIOS' => $this->empresa['datos_bancarios'] ?? '',
        ];

        return array_merge($empresa, $documentoData, $clienteData, $totales, $extras, [
            'path' => $documento['Codigo'] ?? '',
        ]);
    }

    private function generarDocx(array $datos, array $lineas = []): ?string
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
        require_once 'QRcodeService.php';

        $template = new \PhpOffice\PhpWord\TemplateProcessor($this->templatePath);
        $template->setValues($datos);

        $n = count($lineas);
        if ($n > 0) {
            $template->cloneRow('LINEA_NUMERO', $n);
            foreach ($lineas as $i => $l) {
                $idx = $i + 1;
                $template->setValue("LINEA_NUMERO#{$idx}",      (string)$idx);
                $template->setValue("LINEA_DESCRIPCION#{$idx}", (string)($l['Descripcion'] ?? $l['Id_Articulo'] ?? ''));
                $template->setValue("LINEA_CANTIDAD#{$idx}",    number_format((float)($l['Cantidad'] ?? 1), 2, ',', '.'));
                $template->setValue("LINEA_PRECIO#{$idx}",      $this->formatCurrency((float)($l['Precio'] ?? 0)));
                $template->setValue("LINEA_DTO#{$idx}",         number_format((float)($l['Descuento'] ?? 0), 2, ',', '.'));
                $template->setValue("LINEA_IVA#{$idx}",
                    isset($l['tipo_iva_pct'])
                        ? number_format((float)$l['tipo_iva_pct'], 0, ',', '.') . '%'
                        : $this->getIvaLabel((string)($l['Id_Tipo_IVA'] ?? ''))
                );
                $template->setValue("LINEA_IMPORTE#{$idx}", $this->formatCurrency((float)($l['Total'] ?? 0)));
            }
        } else {
            $template->cloneRow('LINEA_NUMERO', 1);
            foreach (['LINEA_NUMERO','LINEA_DESCRIPCION','LINEA_CANTIDAD','LINEA_PRECIO','LINEA_DTO','LINEA_IVA','LINEA_IMPORTE'] as $v) {
                $template->setValue("{$v}#1", '');
            }
        }

        $qrPath = buildQrToTempFile($datos['path']);
        if ($qrPath !== null) {
            $template->setImageValue('QR_FACTURA', [
                'path'   => $qrPath,
                'width'  => 110,
                'height' => 110,
                'ratio'  => true,
            ]);
        }

        $numero   = $datos['FACTURA_NUMERO'] ?? $datos['ALBARAN_NUMERO'] ?? ('doc_' . time());
        $prefix   = match ($this->documentoTipo) {
            'ALBARAN'  => 'albaran_',
            'PROFORMA' => 'proforma_',
            default    => 'factura_',
        };
        $filename   = $prefix . $numero . '_' . time() . '.docx';
        $outputFile = $this->outputPath . '/' . $filename;

        try {
            $template->saveAs($outputFile);
            return $outputFile;
        } catch (\Exception $e) {
            error_log('Error generating DOCX: ' . $e->getMessage());
            return null;
        }
    }

    private function convertirPdf(string $docxPath): ?string
    {
        $gotenbergUrl = getenv('GOTENBERG_URL') ?: 'http://localhost:3000';
        $pdfFilename  = str_replace('|', '', basename($docxPath, '.docx') . '.pdf');
        $pdfPath      = $this->outputPath . '/' . $pdfFilename;

        try {
            $client   = new \GuzzleHttp\Client();
            $response = $client->post($gotenbergUrl . '/forms/libreoffice/convert', [
                'multipart' => [[
                    'name'     => 'files',
                    'contents' => fopen($docxPath, 'r'),
                    'filename' => basename($docxPath),
                ]],
                'timeout' => 60,
            ]);

            if ($response->getStatusCode() === 200) {
                file_put_contents($pdfPath, $response->getBody());
                return $pdfPath;
            }
            return null;
        } catch (\Exception $e) {
            error_log('Gotenberg error: ' . $e->getMessage());
            return null;
        }
    }

    private function formatDate(string|\DateTimeInterface|null $date): string
    {
        if ($date instanceof \DateTimeInterface) return $date->format('d/m/Y');
        if ($date === null || $date === '')      return (new \DateTime())->format('d/m/Y');
        return (new \DateTime($date))->format('d/m/Y');
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function getTipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'SIMPLIFICADA'   => 'Factura Simplificada',
            'RECTIFICATIVA'  => 'Factura Rectificativa',
            'RECAPITULATIVA' => 'Factura Recapitulativa',
            default          => 'Factura',
        };
    }

    private function getIvaLabel(string $tipoIva): string
    {
        return match ($tipoIva) {
            'G21'  => '21%', 'G10' => '10%', 'G4' => '4%', 'G0' => '0%',
            'E21'  => '21%', 'E10' => '10%', 'E4' => '4%',
            'EX'   => 'Exento',
            default => ($tipoIva ?: '?%'),
        };
    }

    /** Descargar PDF de una factura. */
    public function descargarPdf(string $facturaId): array
    {
        require_once __DIR__ . '/../models/Factura.php';
        require_once __DIR__ . '/../models/Cliente.php';

        $facturaModel = new Factura();
        $factura      = $facturaModel->find($facturaId);
        if (!$factura) {
            return ['ok' => false, 'error' => 'Factura no encontrada'];
        }

        $clienteModel = new Cliente();
        $cliente      = $clienteModel->find($factura['Id_Cliente'] ?? '');
        $lineas       = $facturaModel->getLineas($facturaId);

        return $this->generarPdf($factura, $cliente ?? [], $lineas);
    }

    /** Descargar PDF de un albarán. */
    public function descargarPdfAlbaran(string $albaranId): array
    {
        require_once __DIR__ . '/../models/Albaran.php';
        require_once __DIR__ . '/../models/Cliente.php';

        $albaranModel = new Albaran();
        $albaran      = $albaranModel->findWithLines($albaranId);
        if (!$albaran) {
            return ['ok' => false, 'error' => 'Albarán no encontrado'];
        }

        $clienteModel = new Cliente();
        $cliente      = $clienteModel->find($albaran['Id_Cliente'] ?? '');

        return $this->generarPdf($albaran, $cliente ?? [], $albaran['lineas'] ?? []);
    }
}
