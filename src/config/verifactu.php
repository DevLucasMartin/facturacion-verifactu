<?php
/**
 * Configuración del sistema Verifactu (AEAT).
 * Ajustar con los datos reales antes de usar en producción.
 */
return [
    // 'pruebas' | 'produccion'
    'entorno' => 'pruebas',

    'urls' => [
        'pruebas' => [
            'factura'      => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SuministroLRServicio',
            'verificacion' => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
        ],
        'produccion' => [
            'factura'      => 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SuministroLRServicio',
            'verificacion' => 'https://www2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
        ],
    ],

    // Certificado digital (PKCS#12 / .p12 / .pfx)
    'certificado' => [
        'ruta'     => '',   // Ruta absoluta al archivo .p12
        'password' => '',
    ],

    // Comportamiento
    'auto_envio'        => false,
    'max_reintentos'    => 5,
    'tiempo_espera_base'=> 60,   // segundos (backoff exponencial: 2^n * base)
    'timeout'           => 30,   // segundos conexión HTTP

    // Huella semilla (opcional — para encadenar tras migración/reset)
    'semilla' => [
        'huella' => null,
    ],
];
