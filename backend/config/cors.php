<?php

// L'interface React appelle l'API depuis une autre adresse en développement (ports 5173/5174).
// En production, elles partagent le même domaine : seules les origines listées dans CORS_ORIGINES sont acceptées.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('CORS_ORIGINES', env('FRONTEND_URL', 'http://localhost:5173'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
