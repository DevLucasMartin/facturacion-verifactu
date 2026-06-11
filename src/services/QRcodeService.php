<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/VerifactuRegistro.php';

use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;

header_remove('X-Powered-By');

function getVerifiedLink(string $Id_Factura): ?string
{
    $model    = new VerifactuRegistro();
    $registro = $model->findByDocumento(VerifactuRegistro::TIPO_FACTURA, $Id_Factura);

    if (!$registro || empty($registro['URL_Verificacion'])) {
        return null;
    }
    return (string)$registro['URL_Verificacion'];
}

function buildQrFallbackImage(string $path, int $size = 300): string
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('La extensión GD de PHP es necesaria para generar el QR de reserva.');
    }

    $img    = imagecreatetruecolor($size, $size);
    $blanco = imagecolorallocate($img, 255, 255, 255);
    $gris   = imagecolorallocate($img, 180, 180, 180);
    $rojo   = imagecolorallocate($img, 180,  30,  30);
    $oscuro = imagecolorallocate($img,  60,  60,  60);

    imagefill($img, 0, 0, $blanco);
    imagerectangle($img, 2, 2, $size - 3, $size - 3, $gris);
    imagerectangle($img, 3, 3, $size - 4, $size - 4, $gris);

    $lines = [
        ['text' => '! SIN QR !',          'color' => $rojo,   'y' => 50],
        ['text' => 'Factura no enviada',   'color' => $oscuro, 'y' => 80],
        ['text' => 'a la Agencia',         'color' => $oscuro, 'y' => 96],
        ['text' => 'Tributaria.',          'color' => $oscuro, 'y' => 112],
        ['text' => 'Pendiente de',         'color' => $gris,   'y' => 140],
        ['text' => 'tramitacion.',         'color' => $gris,   'y' => 156],
    ];
    foreach ($lines as $line) {
        $textWidth = imagefontwidth(3) * strlen($line['text']);
        imagestring($img, 3, (int)(($size - $textWidth) / 2), $line['y'], $line['text'], $line['color']);
    }

    imagepng($img, $path);
    imagedestroy($img);
    return $path;
}

function buildQrToTempFile(string $Id_Factura, int $size = 200, int $margin = 10): ?string
{
    if ($Id_Factura === '') {
        return null;
    }

    $tmp = sys_get_temp_dir();
    if (!is_dir($tmp) || !is_writable($tmp)) {
        return null;
    }

    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $Id_Factura);
    $base   = rtrim($tmp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeId;
    $qrPath = $base . '_QRVerfi.png';
    $fbPath = $base . '_QRVerfi_pendiente.png';

    $VerifiedLink = getVerifiedLink($Id_Factura);

    if ($VerifiedLink === null) {
        if (!extension_loaded('gd')) {
            return null;
        }
        return buildQrFallbackImage($fbPath, $size);
    }

    if (is_file($qrPath) && filesize($qrPath) > 0) {
        return $qrPath;
    }

    if (strlen($VerifiedLink) > 2000) {
        throw new InvalidArgumentException('URL de verificación demasiado larga para QR.');
    }

    $writer = new PngWriter();
    $result = null;

    $builderClass  = 'Endroid\\QrCode\\Builder\\Builder';
    $encodingClass = 'Endroid\\QrCode\\Encoding\\Encoding';

    try {
        if (class_exists($builderClass) && method_exists($builderClass, 'create') && class_exists($encodingClass)) {
            $result = $builderClass::create()
                ->writer($writer)
                ->data($VerifiedLink)
                ->encoding(new $encodingClass('UTF-8'))
                ->size($size)
                ->margin($margin)
                ->build();
        } elseif (class_exists($builderClass) && class_exists($encodingClass)) {
            $builder = new $builderClass(
                writer:          $writer,
                writerOptions:   [],
                validateResult:  false,
                data:            $VerifiedLink,
                encoding:        new $encodingClass('UTF-8'),
                size:            $size,
                margin:          $margin,
            );
            $result = $builder->build();
        } else {
            throw new RuntimeException(
                'Clases Endroid QR Code no encontradas. Ejecute: composer require endroid/qr-code'
            );
        }

        $result->saveToFile($qrPath);

    } catch (Throwable $e) {
        if (is_file($qrPath) && filesize($qrPath) === 0) {
            @unlink($qrPath);
        }
        Logger::exception('qrcode', $e, ['accion' => 'generarQr', 'archivo' => $qrPath]);
        return null;
    }

    if (!is_file($qrPath) || filesize($qrPath) === 0) {
        return null;
    }

    return $qrPath;
}
