<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SIESA API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de la API REST de SIESA para consultas de inventario.
    |
    */

    'api_url' => env('SIESA_API_URL', 'http://localhost:8000'),

    'username' => env('SIESA_USERNAME'),

    'password' => env('SIESA_PASSWORD'),

    'token_endpoint' => env('SIESA_TOKEN_ENDPOINT', '/token'),

    'inventory_endpoint' => env('SIESA_INVENTORY_ENDPOINT', '/api/CONSINV1'),

    /*
    |--------------------------------------------------------------------------
    | Flat Files Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para generación de archivos planos de pedidos.
    | SIESA leerá estos archivos para importar pedidos.
    |
    */

    'flat_files_path' => env('SIESA_FLAT_FILES_PATH', 'siesa/pedidos'),

    'file_prefix' => env('SIESA_FILE_PREFIX', 'PEDIDO_'),

    /*
    |--------------------------------------------------------------------------
    | API Request Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de límites y timeouts para requests a la API de SIESA.
    |
    */

    'api_timeout' => env('SIESA_API_TIMEOUT', 30),

    'max_retries' => env('SIESA_MAX_RETRIES', 3),

    'token_cache_ttl' => env('SIESA_TOKEN_CACHE_TTL', 3600), // segundos

];
