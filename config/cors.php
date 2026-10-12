<?php

/*
 * CORS para los clientes que consumen la API desde el navegador
 * (sistema web en Vue). La app móvil no pasa por CORS.
 *
 * Los orígenes vienen de CORS_ALLOWED_ORIGINS en .env, separados por coma.
 * En producción se pone solo el dominio real del sistema web.
 */

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173'
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Se autentica con token Bearer, no con cookies
    'supports_credentials' => false,

];
