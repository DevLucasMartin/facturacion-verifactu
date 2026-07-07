<?php
/**
 * Configuración del sistema Verifactu (AEAT).
 *
 * Los valores que cambian por cliente (entorno, certificado y contraseña) se
 * leen del archivo `.env` de la raíz. El `.p12` del cliente se copia en
 * src/storage/certs/ y su nombre se indica en VERIFACTU_CERT_ARCHIVO.
 *
 * IMPORTANTE: el NIF/razón social de empresa.php deben coincidir con el
 * titular del certificado o la AEAT rechazará las facturas.
 */
require_once __DIR__ . '/env.php';

return [
    // 'pruebas' | 'produccion'
    'entorno' => env('VERIFACTU_ENTORNO', 'pruebas'),

    'urls' => [
        'pruebas' => [
            'factura'      => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'verificacion' => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
        ],
        'produccion' => [
            'factura'      => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            'verificacion' => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
        ],
    ],

    // Certificado digital (PKCS#12 / .p12 / .pfx)
    // Solo pon el nombre del archivo (en src/storage/certs/) y la contraseña en el .env
    'certificado' => (function () {
        $archivo  = env('VERIFACTU_CERT_ARCHIVO', '');
        $password = env('VERIFACTU_CERT_PASSWORD', '');
        return [
            'archivo'  => $archivo,
            'password' => $password,
            'ruta'     => $archivo !== '' ? __DIR__ . '/../storage/certs/' . $archivo : '',
        ];
    })(),

    // Comportamiento
    'auto_envio'        => (bool) env('VERIFACTU_AUTO_ENVIO', false),
    'max_reintentos'    => 5,
    'tiempo_espera_base'=> 60,   // segundos (backoff exponencial: 2^n * base)
    'timeout'           => 30,   // segundos conexión HTTP

    // Huella semilla (opcional — para encadenar tras migración/reset)
    'semilla' => [
        'huella' => null,
    ],
];
