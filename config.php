<?php
// Configuración: Si auth_user y auth_pass están vacíos, la web queda sin protección.
return [
    'auth_user' => $_ENV['AUTH_USER'] ?? '',
    'auth_pass' => $_ENV['AUTH_PASS'] ?? '',

    'block_private_hosts' => filter_var(
        $_ENV['BLOCK_PRIVATE_HOSTS'] ?? 'false',
        FILTER_VALIDATE_BOOLEAN
    ),

    'allowed_hosts' => array_values(array_filter(
        array_map(
            'trim',
            explode(',', $_ENV['ALLOWED_HOSTS'] ?? '')
        )
    )),

    'max_timeout' => (int) ($_ENV['MAX_TIMEOUT'] ?? 60),

    'max_response_bytes' => (int) (
        $_ENV['MAX_RESPONSE_BYTES'] ?? 1048576
    )
];