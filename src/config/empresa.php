<?php
/**
 * Datos de la empresa emisora de facturas.
 * Ajustar con los datos reales antes de usar en producción.
 */
return [
    'razon_social'   => 'Solventia Tecnología S.L.',
    'nif'            => '89890001K',
    'telefono'       => '+34 91 847 3200',
    'email'          => 'comercial@solventia.es',
    'web'            => 'www.solventia.es',
    'timezone'       => 'Europe/Madrid',
    'datos_bancarios'=> 'IBAN: ES45 2100 3062 1022 0135 8764  BIC: CAIXESBBXXX',

    'direccion' => [
        'direccion'      => 'Calle Princesa, 31',
        'codigo_postal'  => '28008',
        'poblacion'      => 'Madrid',
        'provincia'      => 'Madrid',
    ],

    // Configuración SMTP para envío de emails
    'smtp' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 587,
        'username'   => 'solventiasl@gmail.com',
        'password'   => 'mkqfyvwrloerohpj',
        'secure'     => 'tls',
        'from_email' => 'solventiasl@gmail.com',
        'from_name'  => 'Solventia Tecnología S.L.',
    ],
];
