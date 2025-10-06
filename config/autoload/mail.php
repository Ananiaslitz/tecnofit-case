<?php
return [
    'default' => getenv('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport'  => 'smtp',
            'host'       => getenv('MAIL_HOST', 'mailhog'),
            'port'       => (int) getenv('MAIL_PORT', 1025),
            'encryption' => getenv('MAIL_ENCRYPTION'),
            'username'   => getenv('MAIL_USERNAME'),
            'password'   => getenv('MAIL_PASSWORD'),
            'timeout'    => null,
        ],
    ],
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS', 'noreply@tecnofit.local'),
        'name'    => getenv('MAIL_FROM_NAME', 'Tecnofit Case'),
    ],
];
