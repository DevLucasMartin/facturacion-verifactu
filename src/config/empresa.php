<?php
/**
 * Datos de la empresa emisora de facturas.
 *
 * NO se editan aquí: todos los valores se leen del archivo `.env` de la raíz.
 * Para dar de alta un cliente nuevo basta con editar el `.env` (ver README).
 * Los segundos argumentos de env() son solo un respaldo por si falta la variable.
 */
require_once __DIR__ . '/env.php';

return [
    'razon_social'    => env('EMPRESA_RAZON_SOCIAL', ''),
    'nif'             => env('EMPRESA_NIF', ''),
    'telefono'        => env('EMPRESA_TELEFONO', ''),
    'email'           => env('EMPRESA_EMAIL', ''),
    'web'             => env('EMPRESA_WEB', ''),
    'timezone'        => env('EMPRESA_TIMEZONE', 'Europe/Madrid'),
    'datos_bancarios' => env('EMPRESA_DATOS_BANCARIOS', ''),

    'direccion' => [
        'direccion'     => env('EMPRESA_DIRECCION', ''),
        'codigo_postal' => env('EMPRESA_CODIGO_POSTAL', ''),
        'poblacion'     => env('EMPRESA_POBLACION', ''),
        'provincia'     => env('EMPRESA_PROVINCIA', ''),
    ],

    // Configuración SMTP para envío de emails
    'smtp' => [
        'host'       => env('SMTP_HOST', ''),
        'port'       => (int) env('SMTP_PORT', 587),
        'username'   => env('SMTP_USERNAME', ''),
        'password'   => env('SMTP_PASSWORD', ''),
        'secure'     => env('SMTP_SECURE', 'tls'),
        'from_email' => env('SMTP_FROM_EMAIL', ''),
        'from_name'  => env('SMTP_FROM_NAME', ''),
    ],
];
