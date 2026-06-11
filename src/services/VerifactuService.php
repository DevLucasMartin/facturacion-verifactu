<?php
/**
 * Servicio de Verifactu - Facturación Electrónica AEAT
 * Módulo de Facturación - Versión 3.2
 */

require_once __DIR__ . '/../models/VerifactuRegistro.php';
require_once __DIR__ . '/../models/Factura.php';
require_once __DIR__ . '/../config/empresa.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/NifInvalidoException.php';
require_once __DIR__ . '/../core/Logger.php';

class VerifactuService
{
    private VerifactuRegistro $registroModel;
    private Factura           $facturaModel;
    private array             $config;
    private array             $empresa;

    const TIPO_FACTURA        = 'F1';
    const TIPO_SIMPLIFICADA   = 'F2';
    const TIPO_RECTIFICATIVA  = 'R1';
    const TIPO_RECAPITULATIVA = 'F3';

    const NS_SOAP         = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_VERIFACTU_LR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    const NS_VERIFACTU    = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    public function __construct()
    {
        $this->registroModel = new VerifactuRegistro();
        $this->facturaModel  = new Factura();
        $this->config        = require __DIR__ . '/../config/verifactu.php';
        $this->empresa       = require __DIR__ . '/../config/empresa.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API PÚBLICA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Firmar un documento (factura o ticket).
     */
    public function firmar(string $tipoOrigen, string $idDocumento, ?bool $autoEnvio = null, ?string $nifExportacion = null, bool $nifExportacionVacio = false, ?string $paisExportacion = null): array
    {
        $doc = $this->obtenerDatosDocumento($tipoOrigen, $idDocumento);
        if (!$doc) {
            throw new Exception("Documento no encontrado: {$idDocumento}");
        }

        $this->validarNifCliente($doc);

        $huellaAnterior = $this->obtenerHuellaAnterior($tipoOrigen, $doc['Id_Canal'] ?? '');
        $fechaHoraGen   = $this->ahoraLocal()->format('c');
        $cadena         = $this->generarCadenaFirma($doc, $huellaAnterior, $fechaHoraGen);
        $huella         = strtoupper(hash('sha256', $cadena));
        $codigoSeguridad = substr($huella, 0, 16);

        $autoEnvio = $autoEnvio ?? ($this->config['auto_envio'] ?? false);
        $estado    = $autoEnvio
            ? VerifactuRegistro::ESTADO_PENDIENTE
            : VerifactuRegistro::ESTADO_GENERADO;

        $registroExistente = $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);
        if ($registroExistente) {
            $this->registroModel->update($registroExistente['Id'], [
                'Huella_Anterior'  => $huellaAnterior,
                'Huella_Actual'    => $huella,
                'Codigo_Seguridad' => $codigoSeguridad,
                'Cadena_Firma'     => $cadena,
                'Estado_Envio'     => $estado,
                'Reintentos'       => 0,
            ]);
            $registroId = $registroExistente['Id'];
        } else {
            $registroId = $this->registroModel->create([
                'Tipo_Origen'      => $tipoOrigen,
                'Id_Documento'     => $idDocumento,
                'Huella_Anterior'  => $huellaAnterior,
                'Huella_Actual'    => $huella,
                'Codigo_Seguridad' => $codigoSeguridad,
                'Cadena_Firma'     => $cadena,
                'Estado_Envio'     => $estado,
            ]);
        }

        $resultado = [
            'ok'               => true,
            'registro_id'      => $registroId,
            'codigo_seguridad' => $codigoSeguridad,
            'huella'           => $huella,
            'cadena'           => $cadena,
            'estado'           => $estado,
        ];

        if ($autoEnvio) {
            $resultado['envio'] = $this->enviarAHacienda($tipoOrigen, $idDocumento, $nifExportacion, $nifExportacionVacio, $paisExportacion);
        }

        return $resultado;
    }

    /**
     * Enviar un documento ya firmado a la AEAT.
     */
    public function enviarAHacienda(string $tipoOrigen, string $idDocumento, ?string $nifExportacion = null, bool $nifExportacionVacio = false, ?string $paisExportacion = null): array
    {
        $registro = $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);
        if (!$registro) {
            throw new Exception("No existe registro Verifactu para: {$idDocumento}");
        }

        $maxReintentos = $this->config['max_reintentos'] ?? 5;
        if ($registro['Reintentos'] >= $maxReintentos) {
            throw new Exception("Máximo de reintentos alcanzado para: {$idDocumento}");
        }

        $doc = $this->obtenerDatosDocumento($tipoOrigen, $idDocumento);
        if (!$doc) {
            throw new Exception("Documento no encontrado: {$idDocumento}");
        }

        $this->validarNifCliente($doc);

        try {
            $xml = $this->generarXmlConWrapper($tipoOrigen, $idDocumento, $doc, $registro, $nifExportacion, $nifExportacionVacio, $paisExportacion);
            $this->guardarXmlEnvio($tipoOrigen, $idDocumento, $xml);
            $respuesta = $this->enviarXML($xml, $doc);

            $urlVerificacion = null;
            if (!empty($respuesta['ok'])) {
                $fechaDoc = $doc['Fecha'] instanceof \DateTimeInterface
                    ? $doc['Fecha']->format('d-m-Y')
                    : date('d-m-Y', strtotime((string)$doc['Fecha']));
                $urlVerificacion = $this->getUrlVerificacion(
                    $doc['Codigo'],
                    $fechaDoc,
                    $this->calcularImporteTotal($doc)
                );
            }

            $this->procesarRespuesta($registro['Id'], $respuesta, $urlVerificacion);

            return [
                'ok'               => true,
                'csv'              => $respuesta['csv'] ?? null,
                'url_verificacion' => $urlVerificacion,
                'estado'           => 'ENVIADO',
            ];
        } catch (Exception $e) {
            $this->registroModel->incrementarReintentos($registro['Id']);
            $this->registroModel->marcarError($registro['Id'], $e->getMessage());
            Logger::exception('verifactu', $e, [
                'accion'    => 'enviarAHacienda',
                'tipo'      => $tipoOrigen,
                'documento' => $idDocumento,
            ]);
            return ['ok' => false, 'error' => $e->getMessage(), 'estado' => 'ERROR'];
        }
    }

    public function regenerarFirma(string $tipoOrigen, string $idDocumento): array
    {
        return $this->firmar($tipoOrigen, $idDocumento, false);
    }

    public function getEstado(string $tipoOrigen, string $idDocumento): ?array
    {
        return $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);
    }

    public function getUrlVerificacion(string $numSerie, string $fecha, string $importe): string
    {
        $entorno = $this->config['entorno'] ?? 'pruebas';
        $base    = $this->config['urls'][$entorno]['verificacion'];
        return $base . '?' . http_build_query([
            'nif'      => $this->empresa['nif'],
            'numserie' => $numSerie,
            'fecha'    => $fecha,
            'importe'  => $importe,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVADOS – DATOS
    // ─────────────────────────────────────────────────────────────────────────

    private function obtenerDatosDocumento(string $tipoOrigen, string $idDocumento): ?array
    {
        if ($tipoOrigen === 'FACTURA') {
            return $this->facturaModel->findWithLines($idDocumento);
        }
        return Database::getInstance()->fetch(
            'SELECT * FROM Ticket WHERE Codigo = ?',
            [$idDocumento]
        );
    }

    /**
     * Huella del último documento registrado a nivel global (por emisor, no por canal).
     *
     * @param string $tipoOrigen
     * @param string $idCanal    (ignorado — la spec AEAT encadena por emisor)
     */
    private function obtenerHuellaAnterior(string $tipoOrigen, string $idCanal = ''): ?string
    {
        $ultimo = $this->registroModel->getUltimoGlobal($tipoOrigen);
        if ($ultimo) {
            return $ultimo['Huella_Actual'];
        }
        return $this->config['semilla']['huella'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVADOS – FIRMA / CADENA DE HASH
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera la cadena de firma según la especificación AEAT Verifactu.
     * Formato: IDEmisorFactura=…&NumSerieFactura=…&FechaExpedicionFactura=dd-mm-YYYY&
     *          TipoFactura=…&CuotaTotal=…&ImporteTotal=…&Huella=…&FechaHoraHusoGenRegistro=ISO8601
     */
    private function generarCadenaFirma(array $doc, ?string $huellaAnterior, string $fechaHoraGen): string
    {
        $fechaRaw = $doc['Fecha'];
        $fechaFactura = $fechaRaw instanceof \DateTimeInterface
            ? $fechaRaw->format('d-m-Y')
            : date('d-m-Y', strtotime((string)$fechaRaw));

        $tipoDoc = $this->mapearTipoDocumento(
            $doc['Tipo_Documento'] ?? 'FACTURA',
            $doc['Tipo_Rectificativa_Verifactu'] ?? null
        );

        $gruposIVA        = $this->calcularGruposIVA($doc);
        $cuotaTotalCalc   = 0.0;
        $importeTotalCalc = 0.0;

        foreach ($gruposIVA as $grupo) {
            $base             = round((float)$grupo['base'], 2);
            $cuotaRepercutida = ($grupo['calificacion'] === 'S1')
                ? round($base * (float)$grupo['tipo_iva'] / 100, 2)
                : 0.0;
            $cuotaTotalCalc   += $cuotaRepercutida;
            $importeTotalCalc += $base + $cuotaRepercutida;
            if ($grupo['calificacion'] === 'S1' && !empty($grupo['tipo_re']) && (float)$grupo['tipo_re'] > 0) {
                $cuotaRE           = round($base * (float)$grupo['tipo_re'] / 100, 2);
                $cuotaTotalCalc   += $cuotaRE;
                $importeTotalCalc += $cuotaRE;
            }
        }

        $cuotaTotal   = number_format(round($cuotaTotalCalc, 2), 2, '.', '');
        $importeTotal = number_format(round($importeTotalCalc, 2), 2, '.', '');

        return 'IDEmisorFactura='           . $this->empresa['nif']
             . '&NumSerieFactura='          . $doc['Codigo']
             . '&FechaExpedicionFactura='   . $fechaFactura
             . '&TipoFactura='              . $tipoDoc
             . '&CuotaTotal='               . $cuotaTotal
             . '&ImporteTotal='             . $importeTotal
             . '&Huella='                   . ($huellaAnterior ?? '')
             . '&FechaHoraHusoGenRegistro=' . $fechaHoraGen;
    }

    private function mapearTipoDocumento(string $tipo, ?string $tipoVerifactu = null): string
    {
        if ($tipo === 'RECTIFICATIVA') {
            return in_array($tipoVerifactu, ['R1', 'R2', 'R3', 'R4', 'R5'], true)
                ? $tipoVerifactu
                : self::TIPO_RECTIFICATIVA;
        }
        return match ($tipo) {
            'SIMPLIFICADA'   => self::TIPO_SIMPLIFICADA,
            'RECAPITULATIVA' => self::TIPO_RECAPITULATIVA,
            default          => self::TIPO_FACTURA,
        };
    }

    private function derivarCodigoVerifactu(string $codigoTipoIva, float $pct): string
    {
        if (strncmp($codigoTipoIva, 'IG', 2) === 0 || $codigoTipoIva === 'I15') return '03';
        if (strncmp($codigoTipoIva, 'IP', 2) === 0) return '02';
        if ($codigoTipoIva === 'NSJ' || $codigoTipoIva === 'ISP') return '04';
        if ($pct == 0.0)               return '04';
        if ($pct == 21.0)              return '01';
        if ($pct == 10.0)              return '02';
        if ($pct == 4.0 || $pct == 5.0) return '03';
        return '01';
    }

    private function calcularGruposIVA(array $doc): array
    {
        $grupos = [];

        if (!empty($doc['lineas'])) {
            foreach ($doc['lineas'] as $linea) {
                $idTipoIva       = (string)($linea['Id_Tipo_IVA']    ?? '');
                $tipoIvaPct      = (float)($linea['tipo_iva_pct']     ?? 0);
                $territorio      = (string)($linea['tipo_territorio'] ?? '');
                $codigoVerifactu = (string)($linea['codigo_verifactu'] ?? '');

                $aplicaRELinea = !empty($linea['Aplica_RE']);
                $reCuotaLinea  = (float)($linea['RE'] ?? 0);

                if ($aplicaRELinea) {
                    $tipoRePct = (float)($linea['tipo_re_pct'] ?? 0);
                    if ($tipoRePct <= 0 && $reCuotaLinea > 0) {
                        $lineaBase = (float)($linea['Base_Imponible'] ?? $linea['Total'] ?? 0);
                        $tipoRePct = $lineaBase > 0 ? round($reCuotaLinea / $lineaBase * 100, 2) : 0;
                    }
                    $claveRegimen = $tipoRePct > 0 ? '18' : '01';
                } elseif ($reCuotaLinea > 0) {
                    $tipoRePct = (float)($linea['tipo_re_pct'] ?? 0);
                    if ($tipoRePct <= 0) {
                        $lineaBase = (float)($linea['Base_Imponible'] ?? $linea['Total'] ?? 0);
                        $tipoRePct = $lineaBase > 0 ? round($reCuotaLinea / $lineaBase * 100, 2) : 0;
                    }
                    $claveRegimen = $tipoRePct > 0 ? '18' : '01';
                } else {
                    $tipoRePct    = 0.0;
                    $claveRegimen = '01';
                }

                $params = $this->resolverParamsDesglose($idTipoIva, $tipoIvaPct, $territorio, $codigoVerifactu);

                $calificacionUsuario = (string)($linea['Calificacion'] ?? $linea['CalificacionOperacion'] ?? '');
                if (in_array($calificacionUsuario, ['S1','S2','N1','N2','E1','E2','E3','E4','E5','E6'], true)) {
                    $params['calificacion'] = $calificacionUsuario;
                }

                if ($params['calificacion'] === 'E2' || $params['calificacion'] === 'E3') {
                    $claveRegimen = '02';
                } elseif (in_array($params['calificacion'], ['E4','E5','E6'], true)) {
                    $stored = trim((string)($linea['Clave_Regimen'] ?? ''));
                    if ($stored !== '') $claveRegimen = $stored;
                }

                $claveGrupo = $params['impuesto'] . '_' . $params['calificacion']
                            . '_' . number_format($params['tipo_iva'], 2, '.', '')
                            . '_' . number_format($tipoRePct, 2, '.', '')
                            . '_' . $claveRegimen;

                if (!isset($grupos[$claveGrupo])) {
                    $grupos[$claveGrupo] = [
                        'impuesto'      => $params['impuesto'],
                        'calificacion'  => $params['calificacion'],
                        'tipo_iva'      => $params['tipo_iva'],
                        'tipo_re'       => $tipoRePct,
                        'clave_regimen' => $claveRegimen,
                        'base'          => 0.0,
                    ];
                }
                $grupos[$claveGrupo]['base'] += (float)($linea['Base_Imponible'] ?? $linea['Total'] ?? 0);
            }

            // Prorratear bases al total de cabecera
            $netSubtotal = 0.0;
            foreach ($grupos as $g) $netSubtotal += $g['base'];
            if ($netSubtotal > 0) {
                $baseGlobal = (float)($doc['Base_Imponible'] ?? $netSubtotal);
                $factor     = $baseGlobal / $netSubtotal;
                if (abs($factor - 1.0) > 1e-9) {
                    foreach ($grupos as &$grupo) $grupo['base'] = round($grupo['base'] * $factor, 10);
                    unset($grupo);
                }
            }

            return array_values($grupos);
        }

        return [[
            'impuesto'      => '01',
            'calificacion'  => 'S1',
            'tipo_iva'      => 21.0,
            'tipo_re'       => 0.0,
            'clave_regimen' => '01',
            'base'          => (float)($doc['Base_Imponible'] ?? 0),
        ]];
    }

    private function resolverParamsDesglose(string $idTipoIva, float $tipoIva, string $territorio, string $codigoVerifactu = ''): array
    {
        if ($territorio === '') {
            if (str_starts_with($idTipoIva, 'IG') || str_starts_with($idTipoIva, 'I1')) $territorio = 'CANARIAS';
            elseif (str_starts_with($idTipoIva, 'IP'))                                    $territorio = 'CEUTA_MELILLA';
        }
        $impuesto = match ($territorio) {
            'CANARIAS'      => '03',
            'CEUTA_MELILLA' => '02',
            default         => '01',
        };

        $calificacion = '';
        if ($codigoVerifactu !== '') {
            $resto = preg_match('/^\d{2}(.+)/', $codigoVerifactu, $m) ? $m[1] : $codigoVerifactu;
            if (preg_match('/^([SEN]\d?)/', $resto, $m)) {
                $calificacion = $m[1];
                $tipoIvaCode  = substr($resto, strlen($calificacion));
                if ($tipoIvaCode !== '') $tipoIva = (float)$tipoIvaCode;
            }
        }

        if ($calificacion === '') {
            if ($idTipoIva === 'ISP') return ['impuesto' => $impuesto, 'calificacion' => 'S2', 'tipo_iva' => 0.0];
            if ($idTipoIva === 'NSJ') return ['impuesto' => $impuesto, 'calificacion' => 'N1', 'tipo_iva' => 0.0];
            $calificacion = ($tipoIva == 0.0 && $impuesto !== '03') ? 'E1' : 'S1';
        }

        return ['impuesto' => $impuesto, 'calificacion' => $calificacion, 'tipo_iva' => $tipoIva];
    }

    private function obtenerFechaFactura(string $idDocumento): string
    {
        $row = Database::getInstance()->fetch(
            'SELECT Fecha FROM Facturas_Clientes WHERE Codigo = ?',
            [$idDocumento]
        );
        if (!$row) return '';
        $fecha = $row['Fecha'];
        return $fecha instanceof \DateTimeInterface
            ? $fecha->format('d-m-Y')
            : date('d-m-Y', strtotime((string)$fecha));
    }

    private function obtenerCliente(string $idCliente): ?array
    {
        $row = Database::getInstance()->fetch(
            'SELECT NIF, Nombre, Nombre FROM Clientes WHERE Codigo = ?',
            [$idCliente]
        );
        if (!$row) return null;
        $row['Nombre'] = trim((string)($row['Nombre'] ?? $row['Nombre'] ?? ''));
        return $row;
    }

    private function validarNifCliente(array $doc): void
    {
        $nif = $doc['cliente']['nif'] ?? '';
        if ($nif === '') return;
        $error = Validator::validarFormatoNif($nif);
        if ($error !== null) {
            throw new Exception("NIF del cliente inválido ('{$nif}'): {$error}");
        }
    }

    private function normalizarNif(string $nif): string
    {
        $nif = strtoupper(trim($nif));
        if (preg_match('/^(\d+)([A-Z])$/', $nif, $m)) {
            $nif = ltrim($m[1], '0') . $m[2];
            if ($nif === $m[2]) $nif = '0' . $m[2];
        }
        return $nif;
    }

    private function ahoraLocal(): \DateTimeImmutable
    {
        $tz = $this->empresa['timezone'] ?? 'Europe/Madrid';
        return new \DateTimeImmutable('now', new \DateTimeZone($tz));
    }

    private function generarXmlConWrapper(string $tipoOrigen, string $idDocumento, array $doc, array $registro, ?string $nifExportacion = null, bool $nifExportacionVacio = false, ?string $paisExportacion = null): string
    {
        require_once __DIR__ . '/../libs/VerifactuWrapper.php';
        require_once __DIR__ . '/../models/Cliente.php';

        $wrapper    = new VerifactuWrapper();
        $codigoDb   = (string)($doc['Codigo'] ?? $idDocumento);

        $fechaFactura = $doc['Fecha'] ?? null;
        if ($fechaFactura instanceof \DateTimeInterface) {
            $fechaFactura = $fechaFactura->format('Y-m-d');
        } else {
            $fechaFactura = $fechaFactura ? substr((string)$fechaFactura, 0, 10) : date('Y-m-d');
        }

        $lineas = $doc['lineas'] ?? [];
        if (empty($lineas)) {
            $lineas = $this->facturaModel->getLineas($idDocumento);
        }

        $idClienteFact = $doc['Id_Cliente'] ?? '';
        if (empty($idClienteFact) && ($doc['Tipo_Documento'] ?? '') === 'RECAPITULATIVA' && !empty($doc['Id_Canal'])) {
            $canalRow = Database::getInstance()->fetch(
                'SELECT * FROM Canales WHERE Codigo = ?',
                [$doc['Id_Canal']]
            );
            $idClienteFact = $canalRow['Id_Cliente_facturacion'] ?? $canalRow['Id_Cliente_Facturacion'] ?? '';
        }
        $clienteModel = new Cliente();
        $cliente = !empty($idClienteFact) ? $clienteModel->find($idClienteFact) : null;

        // Construir líneas sintéticas pre-agregadas por grupo IVA usando los MISMOS
        // números que calcularGruposIVA() / generarCadenaFirma(). Si pasáramos líneas
        // individuales al wrapper, el redondeo por línea puede diferir del redondeo
        // sobre la suma, provocando que el portal AEAT devuelva "factura no encontrada".
        $lineasWrapper = array_map(function ($grupo) {
            $base       = round((float)$grupo['base'], 2);
            $tipoIvaPct = (float)$grupo['tipo_iva'];
            $tipoRePct  = (float)($grupo['tipo_re'] ?? 0);
            $cuotaIva   = ($grupo['calificacion'] === 'S1')
                ? round($base * $tipoIvaPct / 100, 2) : 0.0;
            $cuotaRE    = ($grupo['calificacion'] === 'S1' && $tipoRePct > 0)
                ? round($base * $tipoRePct / 100, 2) : 0.0;
            $territorio = match ($grupo['impuesto']) {
                '03'    => 'CANARIAS',
                '02'    => 'CEUTA_MELILLA',
                default => '',
            };
            return [
                'descripcion'      => 'Desglose',
                'cantidad'         => 1,
                'precio'           => $base,
                'iva_porcentaje'   => $tipoIvaPct,
                'iva_cuota'        => $cuotaIva,
                'base_imponible'   => $base,
                're_cuota'         => $cuotaRE,
                're_porcentaje'    => $tipoRePct,
                'codigo_verifactu' => '',
                'tipo_territorio'  => $territorio,
                'id_tipo_iva'      => '',
                'clave_regimen'    => (string)($grupo['clave_regimen'] ?? '01'),
                'Calificacion'     => (string)$grupo['calificacion'],
            ];
        }, $this->calcularGruposIVA($doc));

        $datos = [
            'codigo'         => $codigoDb,
            'fecha'          => $fechaFactura,
            'tipo_documento' => (string)($doc['Tipo_Documento'] ?? $tipoOrigen),
            'descripcion'    => 'Factura electrónica',
            'base_imponible' => (float)($doc['Base_Imponible'] ?? 0),
            'importe_iva'    => (float)($doc['Importe_IVA']    ?? 0),
            'total'          => (float)($doc['Total']          ?? 0),
            'lineas'         => $lineasWrapper,
        ];

        // Factura rectificada
        if (($doc['Tipo_Documento'] ?? '') === 'RECTIFICATIVA' && !empty($doc['Id_Factura_Origen'])) {
            $facturaOrigen = $this->facturaModel->find($doc['Id_Factura_Origen']);
            if ($facturaOrigen) {
                $fechaOrigen = $facturaOrigen['Fecha'] ?? null;
                if ($fechaOrigen instanceof \DateTimeInterface) {
                    $fechaOrigen = $fechaOrigen->format('Y-m-d');
                } else {
                    $fechaOrigen = $fechaOrigen ? substr((string)$fechaOrigen, 0, 10) : date('Y-m-d');
                }
                $codigoOrigenDb = (string)($facturaOrigen['Codigo'] ?? $doc['Id_Factura_Origen']);
                $datos['factura_origen_nif']  = $this->empresa['nif'] ?? '';
                $datos['factura_origen']       = $codigoOrigenDb;
                $datos['factura_origen_fecha'] = $fechaOrigen;
            }
        }

        // Facturas sustituidas para recapitulativas
        if (($doc['Tipo_Documento'] ?? '') === 'RECAPITULATIVA') {
            $sustituidas = Database::getInstance()->fetchAll(
                'SELECT FS.Id_Simplificada AS codigo, F.Fecha
                 FROM Facturas_Sustituidas FS
                 JOIN Facturas_Clientes F ON F.Codigo = FS.Id_Simplificada
                 WHERE FS.Id_Recapitulativa = ?
                 ORDER BY F.Fecha, FS.Id_Simplificada',
                [$idDocumento]
            );
            if (!empty($sustituidas)) {
                $datos['facturas_sustituidas'] = array_map(function ($s) {
                    $fecha = $s['Fecha'] ?? null;
                    if ($fecha instanceof \DateTimeInterface) {
                        $fecha = $fecha->format('Y-m-d');
                    } else {
                        $fecha = $fecha ? substr((string)$fecha, 0, 10) : date('Y-m-d');
                    }
                    return ['codigo' => $s['codigo'], 'fecha' => $fecha];
                }, $sustituidas);
            }
        }

        if (!empty($cliente)) {
            $nif    = trim((string)($cliente['NIF']          ?? ''));
            $nombre = trim((string)($cliente['Nombre']       ?? $cliente['Nombre'] ?? ''));
            $datos['cliente'] = [
                'nif'                   => $nif,
                'razon_social'          => ($nombre !== '' ? $nombre : 'Cliente'),
                'id_cliente'            => trim((string)($cliente['Codigo']          ?? '')),
                'id_pais'               => (string)($cliente['Id_Pais']              ?? ''),
                'id_tipo_cliente'       => (string)($cliente['Id_Tipo_Cliente']      ?? ''),
                'pais_exportacion'      => $paisExportacion,
                'nif_exportacion'       => $nifExportacion,
                'nif_exportacion_vacio' => $nifExportacionVacio,
            ];
        }

        if (!empty($registro['Cadena_Firma'])) {
            if (preg_match('/FechaHoraHusoGenRegistro=([^\s&]+)$/', $registro['Cadena_Firma'], $m)) {
                $datos['hashedAt'] = trim($m[1]);
            }
        }

        if (!empty($registro['Huella_Anterior'])) {
            $prevReg = $this->registroModel->findByHuellaActual($registro['Huella_Anterior']);
            if (!$prevReg) {
                $prevReg = $this->registroModel->findPreviousByRegistroId((int)$registro['Id']);
            }
            if ($prevReg) {
                $prevFecha = null;
                $df = $prevReg['Doc_Fecha'] ?? null;
                if ($df instanceof \DateTimeInterface) {
                    $prevFecha = $df->format('Y-m-d');
                } elseif ($df) {
                    $prevFecha = substr((string)$df, 0, 10);
                }
                if (!$prevFecha && !empty($prevReg['Cadena_Firma'])) {
                    if (preg_match('/FechaExpedicionFactura=(\d{2}-\d{2}-\d{4})/', $prevReg['Cadena_Firma'], $fm)) {
                        [$d, $mo, $y] = explode('-', $fm[1]);
                        $prevFecha = "$y-$mo-$d";
                    }
                }
                $datos['previous_hash']    = $registro['Huella_Anterior'];
                $datos['previous_invoice'] = [
                    'issuerId'      => $this->empresa['nif'],
                    'invoiceNumber' => $prevReg['Id_Documento'],
                    'issueDate'     => $prevFecha ?? date('Y-m-d'),
                ];
            }
        }

        $resultado = $wrapper->generarXml($datos, false);
        if (!$resultado['ok']) {
            throw new Exception('Error al generar XML: ' . $resultado['error']);
        }

        // Sincronizar huella generada por josemmo con la BD
        if (preg_match_all('/<sum1:Huella>([0-9A-F]{64})<\/sum1:Huella>/', $resultado['xml'], $hm)) {
            $joseHash = end($hm[1]);
            if ($joseHash !== ($registro['Huella_Actual'] ?? '')) {
                $this->registroModel->update((int)$registro['Id'], [
                    'Huella_Actual'    => $joseHash,
                    'Codigo_Seguridad' => substr($joseHash, 0, 16),
                ]);
            }
        }

        return $resultado['xml'];
    }

    private function obtenerDatosTipoIva(string $codigoTipoIva): array
    {
        static $cache = [];
        if ($codigoTipoIva === '') return ['iva' => 21.0, 'codigo_verifactu' => '01'];

        if (!isset($cache[$codigoTipoIva])) {
            $row = Database::getInstance()->fetch(
                'SELECT IVA, Codigo_Verifactu FROM Tipos_IVA WHERE Codigo = ?',
                [$codigoTipoIva]
            );
            $pct = (float)($row['IVA'] ?? 21);
            $cv  = (string)($row['Codigo_Verifactu'] ?? '');
            if ($cv === '') $cv = $this->derivarCodigoVerifactu($codigoTipoIva, $pct);
            $cache[$codigoTipoIva] = ['iva' => $pct, 'codigo_verifactu' => $cv];
        }
        return $cache[$codigoTipoIva];
    }

    private function addElement(DOMDocument $dom, DOMNode $parent, string $tag, string $value): DOMElement
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
        return $el;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVADOS – ENVÍO HTTP
    // ─────────────────────────────────────────────────────────────────────────

    private function debugLog(string $label, string $content): void
    {
        $dir = __DIR__ . '/../storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/verifactu_debug.log',
            "\n\n=== [" . date('Y-m-d H:i:s') . "] {$label} ===\n{$content}\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public function previewXmlEnvio(string $tipoOrigen, string $idDocumento): string
    {
        $registro = $this->registroModel->findByDocumento($tipoOrigen, $idDocumento);
        if (!$registro) throw new Exception("No existe registro Verifactu para: {$idDocumento}");
        $doc = $this->obtenerDatosDocumento($tipoOrigen, $idDocumento);
        if (!$doc) throw new Exception("Documento no encontrado: {$idDocumento}");
        return $this->generarXmlConWrapper($tipoOrigen, $idDocumento, $doc, $registro);
    }

    private function guardarXmlEnvio(string $tipoOrigen, string $idDocumento, string $xml): void
    {
        $dir = __DIR__ . '/../storage/xml';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $idDocumento);
        file_put_contents($dir . '/envio_' . $tipoOrigen . '_' . $safe . '.xml', $xml, LOCK_EX);
    }

    private function enviarXML(string $xml, array $doc): array
    {
        $entorno  = $this->config['entorno'] ?? 'pruebas';
        $url      = $this->config['urls'][$entorno]['factura'] ?? '';
        $certRuta = $this->config['certificado']['ruta'] ?? '';

        if (empty($url)) {
            throw new Exception("URL de envío no configurada para entorno: {$entorno}");
        }

        if ($entorno === 'produccion') {
            if (empty($certRuta) || !file_exists($certRuta)) {
                throw new Exception(
                    "Certificado digital no encontrado en: {$certRuta}. " .
                    "El envío a producción requiere certificado válido."
                );
            }
        }

        if ($entorno === 'pruebas' && !file_exists($certRuta)) {
            return $this->simularRespuesta($doc);
        }

        $headers = ['Content-Type: text/xml; charset=utf-8', 'Accept: text/xml'];
        $timeout = $this->config['timeout'] ?? 30;

        $this->debugLog('SOAP REQUEST – ' . ($doc['Codigo'] ?? '?'), $xml);

        return function_exists('curl_init')
            ? $this->enviarConCurl($url, $xml, $headers, $timeout, $certRuta)
            : $this->enviarConFileGetContents($url, $xml, $headers, $timeout);
    }

    private function enviarConCurl(string $url, string $xml, array $headers, int $timeout, string $certRuta): array
    {
        $ch         = curl_init();
        $certOptions = [];
        if (!empty($certRuta) && file_exists($certRuta)) {
            $certOptions = [
                CURLOPT_SSLCERT       => $certRuta,
                CURLOPT_SSLCERTTYPE   => strtolower(pathinfo($certRuta, PATHINFO_EXTENSION)) === 'p12' ? 'P12' : 'PEM',
                CURLOPT_SSLCERTPASSWD => $this->config['certificado']['password'] ?? '',
            ];
        }
        curl_setopt_array($ch, $certOptions + [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) throw new Exception("Error cURL: {$curlError}");
        if ($httpCode !== 200 && $httpCode !== 201) {
            $safeBody = mb_convert_encoding((string)$response, 'UTF-8', 'auto');
            throw new Exception("Error HTTP {$httpCode}: " . substr($safeBody, 0, 500));
        }

        return $this->parsearRespuesta($response);
    }

    private function enviarConFileGetContents(string $url, string $xml, array $headers, int $timeout): array
    {
        $context  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $xml,
                'timeout' => $timeout,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) throw new Exception('Error al conectar con Hacienda');
        return $this->parsearRespuesta($response);
    }

    private function simularRespuesta(array $doc): array
    {
        $csv = 'VERI' . date('Ymd') . strtoupper(substr(md5($doc['Codigo']), 0, 12));
        return [
            'ok'             => true,
            'csv'            => $csv,
            'codigo'         => '200',
            'descripcion'    => 'Factura registrada correctamente (simulación)',
            'fecha_registro' => date('Y-m-d H:i:s'),
        ];
    }

    private function parsearRespuesta(string $response): array
    {
        $response = mb_convert_encoding($response, 'UTF-8', 'auto');
        $this->debugLog('AEAT RESPONSE', $response);

        $tag = function (string $name) use ($response): ?string {
            if (preg_match('/<(?:\w+:)?' . preg_quote($name, '/') . '>([^<]+)<\/(?:\w+:)?' . preg_quote($name, '/') . '>/i', $response, $m)) {
                return trim($m[1]);
            }
            return null;
        };

        // Detectar SOAP Fault antes de buscar campos normales
        $faultstring = $tag('faultstring');
        if ($faultstring !== null) {
            throw new Exception("AEAT: {$faultstring}");
        }

        $csv          = $tag('CSV');
        $estadoEnvio  = $tag('EstadoEnvio');
        $estadoLinea  = $tag('EstadoRegistro');
        $codigoError  = $tag('CodigoErrorRegistro') ?? $tag('CodigoError');
        $descripError = $tag('DescripcionErrorRegistro') ?? $tag('DescripcionError');

        if ($estadoEnvio === 'Incorrecto' && !$csv) {
            throw new Exception("Envío rechazado por AEAT (código {$codigoError}): {$descripError}");
        }

        $ok = in_array($estadoLinea, ['Correcto', 'AceptadoConErrores'], true)
           || in_array($estadoEnvio, ['Correcto', 'ParcialmenteCorrecto'], true)
           || $csv !== null;

        return [
            'ok'           => $ok,
            'csv'          => $csv,
            'estado_envio' => $estadoEnvio,
            'estado_linea' => $estadoLinea,
            'codigo_error' => $codigoError,
            'descripcion'  => $descripError,
            'raw'          => $response,
        ];
    }

    private function procesarRespuesta(int $registroId, array $respuesta, ?string $urlVerificacion = null): void
    {
        if ($respuesta['ok']) {
            $this->registroModel->marcarEnviado(
                $registroId,
                json_encode($respuesta),
                $respuesta['csv'] ?? null,
                $urlVerificacion
            );
        } else {
            $error = $respuesta['descripcion']
                ?? ($respuesta['codigo_error'] ? "Código {$respuesta['codigo_error']}" : null)
                ?? 'Error desconocido en respuesta AEAT';
            $this->registroModel->marcarError($registroId, $error, $respuesta['raw'] ?? null);
        }
    }

    private function calcularImporteTotal(array $doc): string
    {
        $total = 0.0;
        foreach ($this->calcularGruposIVA($doc) as $g) {
            $base  = round((float)$g['base'], 2);
            $cuota = ($g['calificacion'] === 'S1') ? round($base * (float)$g['tipo_iva'] / 100, 2) : 0.0;
            $total += $base + $cuota;
            if ($g['calificacion'] === 'S1' && !empty($g['tipo_re']) && (float)$g['tipo_re'] > 0) {
                $total += round($base * (float)$g['tipo_re'] / 100, 2);
            }
        }
        return number_format(round($total, 2), 2, '.', '');
    }
}
