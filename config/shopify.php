<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de la tienda Shopify y credenciales de API.
    |
    */

    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),

    'api_key' => env('SHOPIFY_API_KEY'),

    'api_secret' => env('SHOPIFY_API_SECRET'),

    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),

    'api_version' => env('SHOPIFY_API_VERSION', '2024-01'),

    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Inventory Location
    |--------------------------------------------------------------------------
    |
    | ID de la ubicación de inventario en Shopify donde se actualizarán
    | las cantidades de stock.
    |
    */

    'inventory_location_id' => env('SHOPIFY_INVENTORY_LOCATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Customer Metafields
    |--------------------------------------------------------------------------
    |
    | Configuración de metafields personalizados del cliente.
    | Formato: namespace.key (ej: 'custom.nit')
    |
    */

    'customer_nit_metafield' => env('SHOPIFY_CUSTOMER_NIT_METAFIELD', 'custom.nit'),

    /*
    |--------------------------------------------------------------------------
    | API Request Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de límites y timeouts para requests a la API de Shopify.
    |
    */

    'api_timeout' => env('SHOPIFY_API_TIMEOUT', 30),

    'rate_limit_delay' => env('SHOPIFY_RATE_LIMIT_DELAY', 500), // milisegundos

    'max_retries' => env('SHOPIFY_MAX_RETRIES', 3),

];
