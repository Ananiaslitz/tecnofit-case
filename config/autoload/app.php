<?php
return [
    'debug' => (bool) getenv('APP_DEBUG', false),
    'timezone' => getenv('APP_TIMEZONE', 'America/Sao_Paulo')
];
