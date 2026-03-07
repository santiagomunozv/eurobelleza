<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify Store Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de la tienda Shopify y credenciales OAuth.
    | El access_token se renueva automáticamente cada 24h usando client credentials.
    |
    */

    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),

    'client_id' => env('SHOPIFY_CLIENT_ID'),

    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),

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

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para sincronización de pedidos desde Shopify API.
    |
    */

    'sync_days_back' => env('SHOPIFY_SYNC_DAYS_BACK', 1),

    'sync_per_page' => env('SHOPIFY_SYNC_PER_PAGE', 250),

];
