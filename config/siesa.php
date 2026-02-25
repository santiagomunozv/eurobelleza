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
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Valores por defecto para campos de los archivos planos.
    |
    */

    'default_warehouse' => env('SIESA_DEFAULT_WAREHOUSE', '001'),

    'default_location' => env('SIESA_DEFAULT_LOCATION', '15'),

    'default_unit_code' => env('SIESA_DEFAULT_UNIT_CODE', 'UND'),

    'default_currency' => env('SIESA_DEFAULT_CURRENCY', 'COP'),

    'default_price_list' => env('SIESA_DEFAULT_PRICE_LIST', '999'),

    'default_seller_code' => env('SIESA_DEFAULT_SELLER_CODE', ''),

    'default_movement_reason' => env('SIESA_DEFAULT_MOVEMENT_REASON', ''),

    'default_cost_center' => env('SIESA_DEFAULT_COST_CENTER', ''),

    'default_payment_condition' => env('SIESA_DEFAULT_PAYMENT_CONDITION', ''),

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
