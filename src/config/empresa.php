<?php
/**
 * Datos de la empresa emisora de facturas.
 * Ajustar con los datos reales antes de usar en producción.
 */
return [
    'razon_social'   => 'Mi Empresa S.L.',
    'nif'            => 'B12345678',
    'telefono'       => '',
    'email'          => 'facturas@miempresa.es',
    'timezone'       => 'Europe/Madrid',
    'datos_bancarios'=> '',

    'direccion' => [
        'direccion'      => '',
        'codigo_postal'  => '',
        'poblacion'      => '',
        'provincia'      => '',
    ],

    // Configuración SMTP para envío de emails
    'smtp' => [
        'host'       => '',          // p.ej. 'smtp.gmail.com'
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'secure'     => 'tls',       // 'tls' o 'ssl'
        'from_email' => 'facturas@miempresa.es',
        'from_name'  => 'Mi Empresa S.L.',
    ],
];
