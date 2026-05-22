<?php
/**
 * Wrapper de integración con la API Verifactu (AEAT).
 * Usa la librería josemmo/verifactu-php para generar el XML SOAP de suministro.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/NifInvalidoException.php';

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\CancellationRecord;
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
     * Bifurca entre NIF español (FiscalIdentifier) y extranjero (ForeignFiscalIdentifier)
     * según las calificaciones de operación de las líneas (N2/E2/E5 = export siempre extranjero).
     *
     * @return array<FiscalIdentifier|ForeignFiscalIdentifier>
     */
    private function crearDestinatarios(array $datos, InvoiceType $invoiceType): array
    {
        // Simplificadas y R5 no llevan destinatario
        if ($invoiceType === InvoiceType::Simplificada || $invoiceType === InvoiceType::R5) {
            return [];
        }

        $cliente = $datos['cliente'] ?? null;
        if (empty($cliente)) return [];

        $lineas      = $datos['lineas'] ?? [];
        $nifRaw      = trim((string)($cliente['nif'] ?? ''));
        $idClienteWr = (string)($cliente['id_cliente'] ?? '');
        $nombre      = substr(trim((string)($cliente['razon_social'] ?? '')) ?: 'Cliente', 0, 120);

        // Detectar calificaciones de exportación en las líneas
        $tieneN2 = !empty(array_filter($lineas, fn($l) => (string)($l['Calificacion'] ?? '') === 'N2'));
        $tieneE2 = !empty(array_filter($lineas, fn($l) => (string)($l['Calificacion'] ?? '') === 'E2'));
        $tieneE5 = !empty(array_filter($lineas, fn($l) => (string)($l['Calificacion'] ?? '') === 'E5'));

        if ($tieneN2 || $tieneE2 || $tieneE5) {
            // Operaciones de exportación/no localizadas: destinatario siempre extranjero.
            $pais = $this->resolverPaisExportacion($cliente);
            if ($pais === null) {
                $calif = $tieneN2 ? 'N2' : ($tieneE2 ? 'E2' : 'E5');
                throw new NifInvalidoException(
                    "Operación {$calif}: falta el código de país del destinatario. Indícalo en la factura o asígnalo al cliente en su ficha.",
                    $idClienteWr,
                    $nifRaw
                );
            }

            if ($tieneE5) {
                // E5 (entrega intracomunitaria art. 25 LIVA): IDOtro con IDType VAT.
                $clienteE5 = array_merge($cliente, ['nif_exportacion_vacio' => false]);
                $nifExt    = $this->resolverNifExportacion($clienteE5);
                // Si el NIF lleva prefijo de país, extraerlo como CodigoPais
                if (strlen($nifExt) > 2 && preg_match('/^[A-Z]{2}/', $nifExt, $m)) {
                    $pais = $m[0];
                }
                if ($nifExt !== '' && !str_starts_with($nifExt, $pais)) {
                    $nifExt = $pais . $nifExt;
                }
                return [new ForeignFiscalIdentifier(
                    $nombre, $pais, ForeignIdType::VAT, $nifExt !== '' ? $nifExt : '0'
                )];
            }

            // N2 o E2: ForeignFiscalIdentifier con tipo según tipo de cliente
            $nifExt = $this->resolverNifExportacion($cliente);
            $tipoId = $this->tipoForeignIdPorTipoCliente($cliente);
            return [new ForeignFiscalIdentifier(
                $nombre, $pais, $tipoId, $nifExt !== '' ? $nifExt : '0'
            )];
        }

        if ($this->esClienteExtranjero($cliente)) {
            // Cliente extranjero con calificación no-export (S2, E3, E4, E6, etc.)
            $pais = $this->resolverPaisExportacion($cliente);
            if ($pais === null) {
                throw new NifInvalidoException(
                    'El cliente extranjero no tiene código de país asignado. Es obligatorio para emitir una factura a un destinatario fuera de España.',
                    $idClienteWr,
                    $nifRaw
                );
            }
            $nifExt = $this->resolverNifExportacion($cliente);
            $tipoId = $this->tipoForeignIdPorTipoCliente($cliente);
            return [new ForeignFiscalIdentifier(
                $nombre, $pais, $tipoId, $nifExt !== '' ? $nifExt : '0'
            )];
        }

        // Cliente español: NIF obligatorio y validado
        if ($nifRaw === '') {
            throw new NifInvalidoException(
                'El cliente no tiene NIF registrado. Es obligatorio para emitir una factura a Verifactu.',
                $idClienteWr,
                ''
            );
        }
        $errorNif = Validator::validarFormatoNif($nifRaw);
        if ($errorNif !== null) {
            throw new NifInvalidoException(
                "NIF del cliente inválido ('{$nifRaw}'): {$errorNif}",
                $idClienteWr,
                $nifRaw
            );
        }
        return [new FiscalIdentifier($nombre, $nifRaw)];
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
                // S2 = inversión del sujeto pasivo: TipoImpositivo y CuotaRepercutida deben ser 0 (error AEAT 1198)
                $esS2    = ($cal === 'S2');
                $taxRate = $esS2 ? 0.0 : $tipoIva;
                $cuota   = $esS2 ? 0.0 : round($base * $tipoIva / 100, 2);

                $bd->taxRate   = number_format($taxRate, 2, '.', '');
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
     * Normaliza un código de país a ISO 3166-1 alpha-2 (2 letras mayúsculas).
     * Acepta códigos de 2 letras directamente; convierte los alpha-3 más comunes.
     * Devuelve '' si no se puede normalizar (el caller decide el fallback).
     */
    private function normalizarCodigoPais(string $codigoRaw): string
    {
        $codigo = strtoupper(trim($codigoRaw));

        if (preg_match('/^[A-Z]{2}$/', $codigo)) {
            return $codigo;
        }

        $alpha3 = [
            'AFG'=>'AF','ALB'=>'AL','DZA'=>'DZ','AND'=>'AD','AGO'=>'AO','ARG'=>'AR','ARM'=>'AM',
            'AUS'=>'AU','AUT'=>'AT','AZE'=>'AZ','BHS'=>'BS','BHR'=>'BH','BGD'=>'BD','BLR'=>'BY',
            'BEL'=>'BE','BLZ'=>'BZ','BGR'=>'BG','BRA'=>'BR','BRN'=>'BN','CAN'=>'CA','CHL'=>'CL',
            'CHN'=>'CN','COL'=>'CO','CRI'=>'CR','HRV'=>'HR','CUB'=>'CU','CYP'=>'CY','CZE'=>'CZ',
            'DNK'=>'DK','DOM'=>'DO','ECU'=>'EC','EGY'=>'EG','SLV'=>'SV','EST'=>'EE','ETH'=>'ET',
            'FIN'=>'FI','FRA'=>'FR','DEU'=>'DE','GHA'=>'GH','GRC'=>'GR','GTM'=>'GT','HND'=>'HN',
            'HUN'=>'HU','ISL'=>'IS','IND'=>'IN','IDN'=>'ID','IRN'=>'IR','IRQ'=>'IQ','IRL'=>'IE',
            'ISR'=>'IL','ITA'=>'IT','JAM'=>'JM','JPN'=>'JP','JOR'=>'JO','KAZ'=>'KZ','KEN'=>'KE',
            'KOR'=>'KR','KWT'=>'KW','LAO'=>'LA','LVA'=>'LV','LBN'=>'LB','LBY'=>'LY','LIE'=>'LI',
            'LTU'=>'LT','LUX'=>'LU','MYS'=>'MY','MLT'=>'MT','MEX'=>'MX','MDA'=>'MD','MCO'=>'MC',
            'MNG'=>'MN','MNE'=>'ME','MAR'=>'MA','MOZ'=>'MZ','MMR'=>'MM','NPL'=>'NP','NLD'=>'NL',
            'NZL'=>'NZ','NGA'=>'NG','NOR'=>'NO','OMN'=>'OM','PAK'=>'PK','PAN'=>'PA','PRY'=>'PY',
            'PER'=>'PE','PHL'=>'PH','POL'=>'PL','PRT'=>'PT','QAT'=>'QA','ROU'=>'RO','RUS'=>'RU',
            'SAU'=>'SA','SEN'=>'SN','SRB'=>'RS','SGP'=>'SG','SVK'=>'SK','SVN'=>'SI','ZAF'=>'ZA',
            'LKA'=>'LK','SDN'=>'SD','SWE'=>'SE','CHE'=>'CH','SYR'=>'SY','TJK'=>'TJ','TZA'=>'TZ',
            'THA'=>'TH','TGO'=>'TG','TTO'=>'TT','TUN'=>'TN','TUR'=>'TR','UGA'=>'UG','UKR'=>'UA',
            'ARE'=>'AE','GBR'=>'GB','USA'=>'US','URY'=>'UY','UZB'=>'UZ','VEN'=>'VE','VNM'=>'VN',
            'YEM'=>'YE','ZMB'=>'ZM','ZWE'=>'ZW',
        ];

        return $alpha3[$codigo] ?? '';
    }

    /**
     * Detecta si el cliente es extranjero (no establecido en España).
     * Criterio: id_pais distinto de ES, o NIF presente que no valida como NIF español.
     */
    private function esClienteExtranjero(array $cliente): bool
    {
        $paisRaw = strtoupper(trim((string)($cliente['id_pais'] ?? '')));
        if (in_array($paisRaw, ['ES', 'ESP'], true)) {
            return false;
        }
        if ($paisRaw !== '') {
            $paisNorm = $this->normalizarCodigoPais($paisRaw);
            if ($paisNorm !== '' && $paisNorm !== 'ES') {
                return true;
            }
        }
        $nif = trim((string)($cliente['nif'] ?? ''));
        if ($nif !== '' && Validator::validarFormatoNif($nif) !== null) {
            return true;
        }
        return false;
    }

    /**
     * Resuelve el código de país (alpha-2) para operaciones N2/E2/E5.
     * Prioridad: pais_exportacion (usuario) → id_pais del cliente en BD.
     * Devuelve null si no hay ningún valor válido.
     */
    private function resolverPaisExportacion(array $cliente): ?string
    {
        $explicit = trim((string)($cliente['pais_exportacion'] ?? ''));
        if ($explicit !== '') {
            $norm = $this->normalizarCodigoPais($explicit);
            if ($norm !== '') return $norm;
        }
        $paisBD = trim((string)($cliente['id_pais'] ?? ''));
        if ($paisBD !== '') {
            $norm = $this->normalizarCodigoPais($paisBD);
            if ($norm !== '') return $norm;
        }
        return null;
    }

    /**
     * Resuelve el NIF/identificador para operaciones de exportación (N2/E2/E5).
     * Prioridad: nif_exportacion (UI) > nif_exportacion_vacio > nif del cliente en BD.
     */
    private function resolverNifExportacion(array $cliente): string
    {
        $override = isset($cliente['nif_exportacion']) ? trim((string)$cliente['nif_exportacion']) : null;
        if ($override !== null && $override !== '') {
            return strtoupper($override);
        }
        if (!empty($cliente['nif_exportacion_vacio'])) {
            return '';
        }
        return strtoupper(trim((string)($cliente['nif'] ?? '')));
    }

    /**
     * Resuelve el ForeignIdType según el tipo de cliente.
     * EMP/TIEN (empresas) → NationalId (04). Resto → Other (06).
     */
    private function tipoForeignIdPorTipoCliente(array $cliente): ForeignIdType
    {
        $tipoCliente = strtoupper(trim((string)($cliente['id_tipo_cliente'] ?? '')));
        return in_array($tipoCliente, ['EMP', 'TIEN'], true)
            ? ForeignIdType::NationalId
            : ForeignIdType::Other;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ANULACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Anula una factura ya enviada a la AEAT.
     */
    public function anular(string $numeroFactura, string $serie, string $fecha, string $motivo, array $datos = []): array
    {
        try {
            $tz = new \DateTimeZone($this->empresa['timezone'] ?? 'Europe/Madrid');

            $record           = new CancellationRecord();
            $record->invoiceId = new InvoiceIdentifier(
                $this->empresa['nif'] ?? '',
                $numeroFactura,
                new \DateTimeImmutable($fecha, $tz)
            );
            $record->hashedAt = new \DateTimeImmutable('now', $tz);

            if (!empty($datos['previous_hash']) && !empty($datos['previous_invoice'])) {
                $prev = $datos['previous_invoice'];
                $record->previousInvoiceId = new InvoiceIdentifier(
                    $prev['issuerId'] ?? ($this->empresa['nif'] ?? ''),
                    $prev['invoiceNumber'],
                    new \DateTimeImmutable($prev['issueDate'], $tz)
                );
                $record->previousHash = $datos['previous_hash'];
            } else {
                $record->previousInvoiceId = null;
                $record->previousHash      = null;
            }

            $record->hash = $record->calculateHash();
            $record->validate();

            $system   = $this->crearSistemaInformatico();
            $taxpayer = new FiscalIdentifier(
                $this->empresa['razon_social'] ?? '',
                $this->empresa['nif']          ?? ''
            );
            $entorno = $this->config['entorno'] ?? 'pruebas';
            $client  = new AeatClient($system, $taxpayer);
            $client->setProduction($entorno === 'produccion');

            $certPath = $this->config['certificado']['ruta']     ?? '';
            $certPass = $this->config['certificado']['password']  ?? '';
            if (!empty($certPath) && file_exists($certPath)) {
                $client->setCertificate($certPath, $certPass);
            }

            $response = $client->send([$record])->wait();

            if ($response->status !== \josemmo\Verifactu\Models\Responses\ResponseStatus::Correct) {
                $errorDesc = '';
                if (!empty($response->items)) {
                    $errorDesc = $response->items[0]->errorDescription ?? '';
                }
                return ['ok' => false, 'error' => $errorDesc ?: 'Error en respuesta AEAT'];
            }

            return [
                'ok'        => true,
                'resultado' => $response->result ?? 'OK',
                'hash'      => $record->hash,
            ];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CERTIFICADO
    // ─────────────────────────────────────────────────────────────────────────

    public function tieneCertificado(): bool
    {
        $ruta = $this->config['certificado']['ruta'] ?? '';
        return !empty($ruta) && file_exists($ruta);
    }

    public function getInfoCertificado(): ?array
    {
        if (!$this->tieneCertificado()) return null;

        $data = file_get_contents($this->config['certificado']['ruta']);
        if (!$data) return null;

        $password = $this->config['certificado']['password'] ?? '';
        if (!openssl_pkcs12_read($data, $certs, $password)) return null;

        $certInfo = openssl_x509_parse($certs['cert'] ?? '');
        if (!$certInfo) return null;

        return [
            'sujeto'       => $certInfo['subject']      ?? [],
            'emisor'       => $certInfo['issuer']        ?? [],
            'valido_desde' => date('Y-m-d', $certInfo['validFrom_time_t']  ?? 0),
            'valido_hasta' => date('Y-m-d', $certInfo['validTo_time_t']    ?? 0),
            'serial'       => $certInfo['serialNumber']  ?? '',
        ];
    }
}
