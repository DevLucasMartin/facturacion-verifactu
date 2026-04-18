<?php
/**
 * Wrapper de integración con la API Verifactu (AEAT).
 * Usa la librería josemmo/verifactu-php para generar el XML SOAP de suministro.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../core/Validator.php';

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\CorrectiveType;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Services\AeatClient;
use UXML\UXML;

class VerifactuWrapper
{
    private array $empresa;
    private array $config;

    public function __construct()
    {
        $this->empresa = require __DIR__ . '/../config/empresa.php';
        $this->config  = require __DIR__ . '/../config/verifactu.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API PÚBLICA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera el XML SOAP de suministro para AEAT Verifactu.
     *
     * @param  array $datos   Datos normalizados del documento (de VerifactuService)
     * @param  bool  $firmar  Reservado — la firma XAdES se gestiona en VerifactuService
     * @return array ['ok' => bool, 'xml' => string] | ['ok' => false, 'error' => string]
     */
    public function generarXml(array $datos, bool $firmar = false): array
    {
        try {
            $registro = $this->crearRegistro($datos);
            $xml      = $this->exportarSoap($registro);
            return ['ok' => true, 'xml' => $xml];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Firma el documento (delegado en VerifactuService).
     */
    public function firmar(array $datos): array
    {
        return ['ok' => false, 'error' => 'VerifactuWrapper::firmar() está integrado en VerifactuService.'];
    }

    /**
     * Envía el registro a la AEAT (delegado en VerifactuService).
     */
    public function enviar(array $datos): array
    {
        return ['ok' => false, 'error' => 'VerifactuWrapper::enviar() está integrado en VerifactuService.'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DEL REGISTRO
    // ─────────────────────────────────────────────────────────────────────────

    private function crearRegistro(array $datos): RegistrationRecord
    {
        $tz        = new \DateTimeZone($this->empresa['timezone'] ?? 'Europe/Madrid');
        $nifEmisor = $this->empresa['nif'] ?? '';

        // ── ID de factura (emisor, número, fecha)
        $fechaFactura = \DateTimeImmutable::createFromFormat('Y-m-d', substr($datos['fecha'] ?? date('Y-m-d'), 0, 10));
        if ($fechaFactura === false) {
            $fechaFactura = new \DateTimeImmutable('today', $tz);
        }

        $invoiceId = new InvoiceIdentifier(
            $nifEmisor,
            (string)($datos['codigo'] ?? ''),
            $fechaFactura->setTime(0, 0, 0, 0)
        );

        // ── Tipo de factura
        $invoiceType = $this->mapearTipo($datos['tipo_documento'] ?? 'FACTURA');

        // ── Fecha/hora de generación del registro
        if (!empty($datos['hashedAt'])) {
            $hashedAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ISO8601, $datos['hashedAt']);
            if ($hashedAt === false) {
                $hashedAt = new \DateTimeImmutable($datos['hashedAt'], $tz);
            }
        } else {
            $hashedAt = new \DateTimeImmutable('now', $tz);
        }

        // ── Registro de alta
        $registro              = new RegistrationRecord();
        $registro->invoiceId   = $invoiceId;
        $registro->issuerName  = substr($this->empresa['razon_social'] ?? '', 0, 120);
        $registro->invoiceType = $invoiceType;
        $registro->description = substr($datos['descripcion'] ?? 'Factura electrónica', 0, 500);
        $registro->hashedAt    = $hashedAt;

        // ── Encadenamiento (huella y registro anterior)
        if (!empty($datos['previous_hash']) && !empty($datos['previous_invoice'])) {
            $prev      = $datos['previous_invoice'];
            $prevFecha = \DateTimeImmutable::createFromFormat('Y-m-d', substr((string)($prev['issueDate'] ?? date('Y-m-d')), 0, 10));
            $registro->previousInvoiceId = new InvoiceIdentifier(
                (string)($prev['issuerId']      ?? $nifEmisor),
                (string)($prev['invoiceNumber'] ?? ''),
                ($prevFecha ?: new \DateTimeImmutable('today', $tz))->setTime(0, 0, 0, 0)
            );
            $registro->previousHash = (string)$datos['previous_hash'];
        } else {
            // Primera factura del emisor en el sistema
            $registro->previousInvoiceId = null;
            $registro->previousHash      = null;
        }

        // ── Destinatarios
        $registro->recipients = $this->crearDestinatarios($datos, $invoiceType);

        // ── Tipo rectificativa y facturas corregidas (R1–R5)
        $esRectificativa = in_array($invoiceType, [
            InvoiceType::R1, InvoiceType::R2, InvoiceType::R3,
            InvoiceType::R4, InvoiceType::R5,
        ], true);

        if ($esRectificativa) {
            $tipoRect = strtoupper($datos['tipo_rectificativa'] ?? 'I');
            $registro->correctiveType = ($tipoRect === 'S')
                ? CorrectiveType::Substitution
                : CorrectiveType::Differences;

            if (!empty($datos['factura_origen'])) {
                $origenFecha = \DateTimeImmutable::createFromFormat(
                    'Y-m-d',
                    substr((string)($datos['factura_origen_fecha'] ?? date('Y-m-d')), 0, 10)
                );
                $registro->correctedInvoices[] = new InvoiceIdentifier(
                    (string)($datos['factura_origen_nif'] ?? $nifEmisor),
                    (string)$datos['factura_origen'],
                    ($origenFecha ?: new \DateTimeImmutable('today', $tz))->setTime(0, 0, 0, 0)
                );
            }
        }

        // ── Facturas sustituidas (F3 recapitulativa/sustitutiva)
        if ($invoiceType === InvoiceType::Sustitutiva && !empty($datos['facturas_sustituidas'])) {
            foreach ($datos['facturas_sustituidas'] as $fs) {
                $fsFecha = \DateTimeImmutable::createFromFormat(
                    'Y-m-d',
                    substr((string)($fs['fecha'] ?? date('Y-m-d')), 0, 10)
                );
                $registro->replacedInvoices[] = new InvoiceIdentifier(
                    $nifEmisor,
                    (string)($fs['codigo'] ?? ''),
                    ($fsFecha ?: new \DateTimeImmutable('today', $tz))->setTime(0, 0, 0, 0)
                );
            }
        }

        // ── Desglose e importes totales
        [$cuotaTotal, $importeTotal, $breakdown] = $this->crearDesglose($datos);
        $registro->breakdown      = $breakdown;
        $registro->totalTaxAmount = number_format($cuotaTotal,   2, '.', '');
        $registro->totalAmount    = number_format($importeTotal, 2, '.', '');

        // ── Huella SHA-256 (calculada por la librería con el mismo algoritmo AEAT)
        $registro->hash = $registro->calculateHash();

        return $registro;
    }

    /**
     * Construye la lista de destinatarios del registro.
     *
     * @return array<FiscalIdentifier|ForeignFiscalIdentifier>
     */
    private function crearDestinatarios(array $datos, InvoiceType $invoiceType): array
    {
        // Simplificadas y R5 no llevan destinatario (validación de la librería)
        if ($invoiceType === InvoiceType::Simplificada || $invoiceType === InvoiceType::R5) {
            return [];
        }

        $cliente = $datos['cliente'] ?? null;
        if (empty($cliente)) return [];

        $nif            = trim((string)($cliente['nif']             ?? ''));
        $razonSocial    = trim((string)($cliente['razon_social']    ?? ''));
        $nifExportacion = trim((string)($cliente['nif_exportacion'] ?? ''));
        $nifVacio       = !empty($cliente['nif_exportacion_vacio']);

        if ($nif === '' && $nifExportacion === '' && !$nifVacio) return [];

        $nombre = substr($razonSocial !== '' ? $razonSocial : 'Cliente', 0, 120);

        if ($nif !== '') {
            return [new FiscalIdentifier($nombre, $nif)];
        }

        if ($nifExportacion !== '') {
            $pais   = $this->normalizarCodigoPais($cliente['codigo_pais'] ?? 'ES');
            $idType = ForeignIdType::NationalId; // 04 = documento oficial del país de residencia
            return [new ForeignFiscalIdentifier($nombre, $pais, $idType, $nifExportacion)];
        }

        // $nifVacio sin ID extranjero → exportación sin identificar, sin destinatario
        return [];
    }

    /**
     * Agrupa las líneas por tramo impositivo y construye el desglose josemmo.
     *
     * @return array [cuotaTotal, importeTotal, BreakdownDetails[]]
     */
    private function crearDesglose(array $datos): array
    {
        $grupos    = $this->agruparLineas($datos['lineas'] ?? [], $datos);
        $breakdown = [];
        $cuotaTotal   = 0.0;
        $importeTotal = 0.0;

        foreach ($grupos as $g) {
            $bd = new BreakdownDetails();

            // Impuesto
            $bd->taxType = match ($g['impuesto']) {
                '02' => TaxType::IPSI,
                '03' => TaxType::IGIC,
                default => TaxType::IVA,
            };

            // Clave de régimen
            $claveReg = (string)($g['clave_regimen'] ?? '01');
            $bd->regimeType = RegimeType::tryFrom($claveReg) ?? RegimeType::C01;

            // Calificación / operación
            $cal    = (string)($g['calificacion'] ?? 'S1');
            $opType = OperationType::tryFrom($cal) ?? OperationType::Subject;
            $bd->operationType = $opType;

            $base = round($g['base'], 2);
            $bd->baseAmount = number_format($base, 2, '.', '');

            if ($opType->isSubject()) {
                $tipoIva = round((float)($g['tipo_iva'] ?? 0), 2);
                // S2 = inversión del sujeto pasivo: cuota 0 para el emisor
                $cuota   = ($cal === 'S1') ? round($base * $tipoIva / 100, 2) : 0.0;

                $bd->taxRate   = number_format($tipoIva, 2, '.', '');
                $bd->taxAmount = number_format($cuota,   2, '.', '');

                $cuotaTotal   += $cuota;
                $importeTotal += $base + $cuota;

                // Recargo de equivalencia (solo para S1, régimen C18)
                $tipoRe = round((float)($g['tipo_re'] ?? 0), 2);
                if ($cal === 'S1' && $tipoRe > 0) {
                    $cuotaRe = round($base * $tipoRe / 100, 2);
                    $bd->surchargeRate   = number_format($tipoRe,  2, '.', '');
                    $bd->surchargeAmount = number_format($cuotaRe, 2, '.', '');
                    $cuotaTotal   += $cuotaRe;
                    $importeTotal += $cuotaRe;
                }
            } else {
                // No sujeta o exenta: solo base contribuye al importe total
                $importeTotal += $base;
            }

            $breakdown[] = $bd;
        }

        return [round($cuotaTotal, 2), round($importeTotal, 2), $breakdown];
    }

    /**
     * Exporta el registro como sobre SOAP completo usando UXML (misma estructura que AeatClient).
     */
    private function exportarSoap(RegistrationRecord $registro): string
    {
        $system = $this->crearSistemaInformatico();

        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => AeatClient::NS_SOAPENV,
            'xmlns:sum'     => AeatClient::NS_AEAT,
            'xmlns:sum1'    => Record::NS,
        ]);
        $xml->add('soapenv:Header');
        $base = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Cabecera con el obligado a emitir
        $cabecera = $base->add('sum:Cabecera');
        $obligado = $cabecera->add('sum1:ObligadoEmision');
        $obligado->add('sum1:NombreRazon', $this->empresa['razon_social'] ?? '');
        $obligado->add('sum1:NIF',         $this->empresa['nif']          ?? '');

        // Registro de factura
        $registro->export($base->add('sum:RegistroFactura'), $system);

        return $xml->asXML();
    }

    private function crearSistemaInformatico(): ComputerSystem
    {
        $cfg = $this->config['sistema_informatico'] ?? [];

        $si = new ComputerSystem();
        $si->vendorName            = substr($cfg['nombre_razon'] ?? ($this->empresa['razon_social'] ?? ''), 0, 120);
        $si->vendorNif             = $cfg['nif'] ?? ($this->empresa['nif'] ?? '');
        $si->name                  = substr($cfg['nombre_sistema'] ?? 'SistemaGestionFacturas', 0, 30);
        $si->id                    = substr($cfg['id_sistema'] ?? 'SG', 0, 2);  // máx. 2 chars
        $si->version               = $cfg['version'] ?? '1.0';
        $si->installationNumber    = $cfg['num_instalacion'] ?? '00001';
        $si->onlySupportsVerifactu    = ($cfg['solo_verifactu'] ?? 'S') === 'S';
        $si->supportsMultipleTaxpayers = ($cfg['multi_ot'] ?? 'N') === 'S';
        $si->hasMultipleTaxpayers     = ($cfg['multiples_ot'] ?? 'N') === 'S';

        return $si;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AGRUPACIÓN DE LÍNEAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Agrupa las líneas por combinación impuesto/calificación/tipo_iva/tipo_re/clave_regimen.
     * Si no hay líneas, devuelve un grupo de fallback con IVA 21% S1.
     */
    private function agruparLineas(array $lineas, array $datos = []): array
    {
        if (empty($lineas)) {
            return [[
                'impuesto'      => '01',
                'calificacion'  => 'S1',
                'tipo_iva'      => 21.0,
                'tipo_re'       => 0.0,
                'clave_regimen' => '01',
                'base'          => (float)($datos['base_imponible'] ?? 0),
            ]];
        }

        $grupos = [];
        foreach ($lineas as $linea) {
            $cal       = (string)($linea['Calificacion']    ?? $linea['calificacion']   ?? 'S1');
            $tipoIva   = (float)($linea['iva_porcentaje']   ?? $linea['tipo_iva_pct']   ?? 0);
            $tipoRe    = (float)($linea['re_porcentaje']    ?? $linea['tipo_re_pct']    ?? 0);
            $base      = (float)($linea['base_imponible']   ?? $linea['Base_Imponible'] ?? 0);
            $territorio = (string)($linea['tipo_territorio'] ?? '');
            $claveReg  = (string)($linea['clave_regimen']   ?? '01');

            $impuesto = match ($territorio) {
                'CANARIAS'      => '03',
                'CEUTA_MELILLA' => '02',
                default         => '01',
            };

            $clave = "{$impuesto}_{$cal}_"
                   . number_format($tipoIva, 2, '.', '') . '_'
                   . number_format($tipoRe,  2, '.', '') . "_{$claveReg}";

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'impuesto'      => $impuesto,
                    'calificacion'  => $cal,
                    'tipo_iva'      => $tipoIva,
                    'tipo_re'       => $tipoRe,
                    'clave_regimen' => $claveReg,
                    'base'          => 0.0,
                ];
            }
            $grupos[$clave]['base'] += $base;
        }

        return array_values($grupos);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────────────────

    private function mapearTipo(string $tipo): InvoiceType
    {
        return match (strtoupper($tipo)) {
            'SIMPLIFICADA'   => InvoiceType::Simplificada,
            'RECTIFICATIVA'  => InvoiceType::R1,
            'RECAPITULATIVA' => InvoiceType::Sustitutiva,
            default          => InvoiceType::Factura,
        };
    }

    /**
     * Normaliza un código de país a ISO 3166-1 alpha-2 en mayúsculas.
     * Devuelve 'ES' si el valor no es válido.
     */
    private function normalizarCodigoPais(string $pais): string
    {
        $pais = strtoupper(trim($pais));
        return preg_match('/^[A-Z]{2}$/', $pais) ? $pais : 'ES';
    }
}
