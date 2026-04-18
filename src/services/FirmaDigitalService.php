<?php
/**
 * Servicio de Firma Digital XAdES
 * Módulo de Facturación - Versión 3.2
 *
 * Genera firmas digitales XAdES para documentos XML de facturación electrónica.
 */

require_once __DIR__ . '/../config/verifactu.php';

class FirmaDigitalService {
    private array  $config;
    private        $privateKey  = null;
    private        $certificate = null;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/verifactu.php';
    }

    /** Cargar certificado y clave privada desde PKCS#12. */
    private function cargarCertificado(): bool {
        $certPath = $this->config['certificado']['ruta']     ?? '';
        $certPass = $this->config['certificado']['password'] ?? '';

        if (empty($certPath) || !file_exists($certPath)) return false;

        $certData = file_get_contents($certPath);
        if (!$certData) return false;

        if (!openssl_pkcs12_read($certData, $certs, $certPass)) return false;

        $this->privateKey  = $certs['pkey'] ?? null;
        $this->certificate = $certs['cert']  ?? null;

        return $this->privateKey !== null && $this->certificate !== null;
    }

    /** Firmar XML con XAdES-BES. */
    public function firmarXml(string $xmlContent): array {
        try {
            if (!$this->cargarCertificado()) {
                return ['ok' => false, 'error' => 'No se pudo cargar el certificado'];
            }

            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput       = false;

            if (!$dom->loadXML($xmlContent)) {
                return ['ok' => false, 'error' => 'XML inválido'];
            }

            $digestValue = base64_encode(hash('sha256', $xmlContent, true));
            $signatureId = 'Signature-' . uniqid();
            $root        = $dom->documentElement;

            $sigNs   = 'http://www.w3.org/2000/09/xmldsig#';
            $xadesNs = 'http://uri.etsi.org/01903/v1.3.2#';

            $signature   = $dom->createElementNS($sigNs, 'ds:Signature');
            $signature->setAttribute('Id', $signatureId);

            $signedInfo = $dom->createElementNS($sigNs, 'ds:SignedInfo');

            $canonMethod = $dom->createElementNS($sigNs, 'ds:CanonicalizationMethod');
            $canonMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
            $signedInfo->appendChild($canonMethod);

            $sigMethod = $dom->createElementNS($sigNs, 'ds:SignatureMethod');
            $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
            $signedInfo->appendChild($sigMethod);

            $reference = $dom->createElementNS($sigNs, 'ds:Reference');
            $reference->setAttribute('Id',  $signatureId . '-Ref');
            $reference->setAttribute('URI', '');

            $digestMethod = $dom->createElementNS($sigNs, 'ds:DigestMethod');
            $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
            $reference->appendChild($digestMethod);

            $digestValueEl = $dom->createElementNS($sigNs, 'ds:DigestValue', $digestValue);
            $reference->appendChild($digestValueEl);
            $signedInfo->appendChild($reference);
            $signature->appendChild($signedInfo);

            $signatureValue = $dom->createElementNS($sigNs, 'ds:SignatureValue');
            $signatureValue->setAttribute('Id', $signatureId . '-SigValue');
            $signatureBytes = '';
            openssl_sign($dom->saveXML($signedInfo), $signatureBytes, $this->privateKey, OPENSSL_ALGO_SHA256);
            $signatureValue->nodeValue = base64_encode($signatureBytes);
            $signature->appendChild($signatureValue);

            $keyInfo  = $dom->createElementNS($sigNs, 'ds:KeyInfo');
            $x509Data = $dom->createElementNS($sigNs, 'ds:X509Data');
            $x509Cert = $dom->createElementNS($sigNs, 'ds:X509Certificate', base64_encode($this->certificate));
            $x509Data->appendChild($x509Cert);
            $keyInfo->appendChild($x509Data);
            $signature->appendChild($keyInfo);

            // XAdES qualifying properties
            $object               = $dom->createElementNS($xadesNs, 'xades:Object');
            $qualifyingProperties = $dom->createElementNS($xadesNs, 'xades:QualifyingProperties');
            $qualifyingProperties->setAttribute('Target', '#' . $signatureId);

            $signedProperties           = $dom->createElementNS($xadesNs, 'xades:SignedProperties');
            $signedProperties->setAttribute('Id', $signatureId . '-SignedProps');
            $signedSignatureProperties  = $dom->createElementNS($xadesNs, 'xades:SignedSignatureProperties');

            $signingTime = $dom->createElementNS($xadesNs, 'xades:SigningTime', date('c'));
            $signedSignatureProperties->appendChild($signingTime);

            $signingCertificate = $dom->createElementNS($xadesNs, 'xades:SigningCertificate');
            $cert               = $dom->createElementNS($xadesNs, 'xades:Cert');
            $certDigest         = $dom->createElementNS($xadesNs, 'xades:CertDigest');

            $cdDigestMethod = $dom->createElementNS($sigNs, 'ds:DigestMethod');
            $cdDigestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
            $certDigest->appendChild($cdDigestMethod);

            $certDigestValue = $dom->createElementNS($sigNs, 'ds:DigestValue',
                base64_encode(hash('sha256', $this->certificate, true)));
            $certDigest->appendChild($certDigestValue);

            $issuerSerial = $dom->createElementNS($xadesNs, 'xades:IssuerSerial');
            $issuerSerial->nodeValue = $this->obtenerIssuerSerial();
            $certDigest->appendChild($issuerSerial);

            $cert->appendChild($certDigest);
            $signingCertificate->appendChild($cert);
            $signedSignatureProperties->appendChild($signingCertificate);
            $signedProperties->appendChild($signedSignatureProperties);
            $qualifyingProperties->appendChild($signedProperties);
            $object->appendChild($qualifyingProperties);
            $signature->appendChild($object);

            $root->appendChild($signature);

            return [
                'ok'     => true,
                'xml'    => $dom->saveXML(),
                'huella' => $digestValue,
            ];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function obtenerIssuerSerial(): string {
        $certInfo = openssl_x509_parse($this->certificate);
        if (!$certInfo) return '';

        $serial    = $certInfo['serialNumber'] ?? $certInfo['serial'] ?? '';
        $issuerStr = '';
        foreach ($certInfo['issuer'] ?? [] as $k => $v) {
            $issuerStr .= "{$k}={$v},";
        }
        return '1,' . base64_encode(pack('a*', $serial . $issuerStr));
    }

    public function tieneCertificado(): bool {
        return $this->cargarCertificado();
    }

    public function getInfoCertificado(): ?array {
        if (!$this->cargarCertificado()) return null;
        $certInfo = openssl_x509_parse($this->certificate);
        if (!$certInfo) return null;
        return [
            'sujeto'       => $certInfo['subject']    ?? [],
            'emisor'       => $certInfo['issuer']     ?? [],
            'valido_desde' => date('Y-m-d', $certInfo['validFrom_time_t']  ?? 0),
            'valido_hasta' => date('Y-m-d', $certInfo['validTo_time_t']    ?? 0),
            'serial'       => $certInfo['serialNumber'] ?? '',
        ];
    }
}
