<?php
/**
 * Servicio de Email para facturas
 * Módulo de Facturación - Versión 3.2
 *
 * Envía facturas por email usando SMTP (sockets nativos) o mail().
 */

require_once __DIR__ . '/../config/empresa.php';

class EmailService
{
    private array $config;
    private array $empresa;

    public function __construct()
    {
        $this->empresa = require __DIR__ . '/../config/empresa.php';
        $smtp          = $this->empresa['smtp'] ?? [];

        $this->config = [
            'smtp_host'   => $smtp['host']       ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'smtp_port'   => $smtp['port']        ?? getenv('SMTP_PORT') ?: 587,
            'smtp_user'   => $smtp['username']    ?? getenv('SMTP_USER') ?: '',
            'smtp_pass'   => $smtp['password']    ?? getenv('SMTP_PASS') ?: '',
            'smtp_secure' => $smtp['secure']      ?? getenv('SMTP_SECURE') ?: 'tls',
            'from_email'  => $smtp['from_email']  ?? $this->empresa['email'] ?? getenv('FROM_EMAIL') ?: 'facturas@empresa.es',
            'from_name'   => $smtp['from_name']   ?? $this->empresa['razon_social'] ?? getenv('FROM_NAME') ?: 'Empresa',
        ];
    }

    /** Enviar factura por email. */
    public function enviarFactura(array $factura, array $cliente, string $emailDestino, ?string $rutaAdjunto = null): array
    {
        try {
            if (empty($emailDestino) || !filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Email inválido'];
            }

            if (!$rutaAdjunto) {
                require_once __DIR__ . '/PdfService.php';
                $pdfService = new PdfService();
                $resultado  = $pdfService->generarPdf($factura, $cliente, []);
                if (!$resultado['ok']) {
                    return ['ok' => false, 'error' => 'Error al generar PDF: ' . $resultado['error']];
                }
                $rutaAdjunto = $resultado['ruta'] ?? null;
            }

            $asunto = 'Factura ' . ($factura['Serie'] ?? '') . ($factura['Numero'] ?? '');
            $cuerpo = $this->generarCuerpo($factura, $cliente);

            return $this->enviar($emailDestino, $asunto, $cuerpo, $rutaAdjunto);
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Generar cuerpo HTML del email. */
    private function generarCuerpo(array $factura, array $cliente): string
    {
        $empresaNombre = $this->empresa['razon_social'] ?? 'Nuestra empresa';
        $numero        = ($factura['Serie'] ?? '') . ($factura['Numero'] ?? '');
        $rawFecha      = $factura['Fecha'] ?? null;

        if ($rawFecha instanceof \DateTimeInterface) {
            $fecha = $rawFecha->format('d/m/Y');
        } elseif (is_numeric($rawFecha)) {
            $fecha = date('d/m/Y', (int)$rawFecha);
        } elseif (is_string($rawFecha) && ($ts = strtotime($rawFecha)) !== false) {
            $fecha = date('d/m/Y', $ts);
        } else {
            $fecha = date('d/m/Y');
        }

        $total          = number_format($factura['Total'] ?? 0, 2, ',', '.') . ' €';
        $esProforma     = !empty($factura['_proforma']);
        $tipoDocumento  = strtoupper($factura['Tipo_Documento'] ?? 'FACTURA');
        $tituloDocumento = match (true) {
            $esProforma                    => 'FACTURA PROFORMA',
            $tipoDocumento === 'ALBARAN'   => 'ALBARÁN',
            $tipoDocumento === 'PROFORMA'  => 'FACTURA PROFORMA',
            default                        => 'FACTURA',
        };
        $textoDocumento = $esProforma ? 'la factura proforma' : 'la factura';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; }
        .header { background-color: #1F4E79; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background-color: #f2f2f2; padding: 15px; font-size: 12px; text-align: center; }
        .total { font-size: 18px; font-weight: bold; color: #1F4E79; }
    </style>
</head>
<body>
    <div class="header"><h1>{$tituloDocumento}</h1></div>
    <div class="content">
        <p>Estimado cliente,</p>
        <p>Le adjuntamos {$textoDocumento} <strong>{$numero}</strong> correspondiente a fecha <strong>{$fecha}</strong>.</p>
        <p class="total">Total: {$total}</p>
        <p>Si tiene cualquier consulta sobre esta factura, no dude en contactar con nosotros.</p>
        <p>Atentamente,<br>{$empresaNombre}</p>
    </div>
    <div class="footer">
        <p>{$empresaNombre}</p>
        <p>Este es un email automático, por favor no responda a este mensaje.</p>
    </div>
</body>
</html>
HTML;
    }

    /** Enviar email con PHPMailer (SMTP) o mail() como fallback. */
    public function enviar(string $to, string $subject, string $body, ?string $attachment = null): array
    {
        $host     = $this->config['smtp_host'];
        $username = $this->config['smtp_user'];

        if (empty($host) || $host === 'localhost' || empty($username)) {
            return $this->enviarMail($to, $subject, $body, $attachment);
        }

        try {
            require_once __DIR__ . '/../../vendor/autoload.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['smtp_user'];
            $mail->Password   = $this->config['smtp_pass'];
            $mail->SMTPSecure = $this->config['smtp_secure'] === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$this->config['smtp_port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $body;

            if ($attachment && is_file($attachment)) {
                $mail->addAttachment($attachment);
            }

            $mail->send();
            return ['ok' => true, 'mensaje' => 'Email enviado correctamente'];

        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Fallback con la función mail() de PHP. */
    private function enviarMail(string $to, string $subject, string $body, ?string $attachment = null): array
    {
        $from     = $this->config['from_email'];
        $fromName = $this->config['from_name'];
        $boundary = '=_Part_' . md5(uniqid((string)mt_rand(), true));

        $headers  = "From: {$fromName} <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";

        if ($attachment && is_file($attachment)) {
            $filename = basename($attachment);
            $content  = chunk_split(base64_encode(file_get_contents($attachment)));
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $message .= $content . "\r\n\r\n";
        }

        $message .= "--{$boundary}--\r\n";

        return mail($to, $subject, $message, $headers)
            ? ['ok' => true,  'mensaje' => 'Email enviado correctamente']
            : ['ok' => false, 'error'   => 'Error al enviar email'];
    }

    /** Obtener email del cliente. */
    public function obtenerEmailCliente(array $cliente): ?string
    {
        return $cliente['Email']
            ?? $cliente['email']
            ?? $cliente['Correo_Electronico']
            ?? $cliente['Email_Factura']
            ?? null;
    }
}
