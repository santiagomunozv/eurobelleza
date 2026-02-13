# Prompt Completo para GitHub Copilot - Proyecto Integración Shopify ↔ SIESA 8.5

## 🎯 CONTEXTO GENERAL DEL PROYECTO

Hola, vamos a trabajar juntos en un proyecto Laravel que actúa como **middleware de integración** entre dos sistemas: **Shopify** (plataforma de ecommerce) y **SIESA 8.5** (sistema ERP empresarial utilizado en Colombia).

El proyecto tiene un propósito muy específico: sincronizar pedidos e inventarios entre ambos sistemas, pero con una característica importante: **SIESA 8.5 NO tiene API REST para todo**, así que usaremos dos métodos diferentes:

1. **Para pedidos**: Generaremos archivos planos de texto con formato de posiciones fijas
2. **Para inventarios**: Consumiremos una API REST que SIESA sí tiene disponible

---

## 🔄 LOS DOS FLUJOS PRINCIPALES

### **FLUJO 1: Shopify → SIESA (Creación de Pedidos)**

**¿Qué debe pasar?**

Cuando un cliente hace un pedido en la tienda Shopify, el sistema dispara un webhook (una llamada HTTP POST) hacia nuestro proyecto Laravel. Nosotros debemos:

1. **Recibir** ese webhook con toda la información del pedido en formato JSON
2. **Validar** que efectivamente viene de Shopify (verificación de firma HMAC para seguridad)
3. **Guardar** el JSON completo del pedido en nuestra base de datos
4. **Procesar** el JSON para extraer los datos importantes: cliente, productos, cantidades, precios
5. **Generar un archivo de texto plano** con un formato muy específico que SIESA requiere
6. **Guardar** ese archivo en una ubicación específica del servidor para que SIESA lo pueda leer
7. **Registrar** todo lo que pasa en cada paso (logs) para poder rastrear problemas

**Detalles importantes de este flujo:**

- El archivo plano tiene un **formato de posiciones fijas**: cada dato va en una posición exacta del texto, con una longitud específica
- Si un número de pedido es "1234" y el campo debe tener 10 caracteres, se rellena con espacios o ceros: "1234 " o "0000001234"
- El archivo tiene una sección de **encabezado** (datos generales del pedido) y una sección de **detalle** (cada producto del pedido)
- Un pedido puede tener múltiples productos, pero todo va en UN solo archivo
- El nombre del archivo sigue un patrón: `PEDIDO_{numero_de_pedido}.txt`
- SIESA leerá este archivo desde su lado y creará el pedido en su sistema

**¿Por qué es complejo?**

El archivo plano requiere mucha precisión: si un solo carácter está en la posición incorrecta, SIESA no podrá leer el pedido. Necesitamos funciones helper que formateen correctamente cada tipo de dato (textos, números, fechas, cantidades, precios).

### **FLUJO 2: SIESA → Shopify (Sincronización de Inventarios)**

**¿Qué debe pasar?**

Periódicamente (cada X minutos u horas, configurable), nuestro sistema debe:

1. **Ejecutar** un comando programado (cron job de Laravel)
2. **Autenticarse** en la API de SIESA para obtener un token de acceso
3. **Consultar** el inventario actual de productos en SIESA (pueden tener acceso a base de datos o API)
4. **Por cada producto**, buscar su equivalente en Shopify usando el SKU como identificador común
5. **Actualizar** el inventario en Shopify usando su API REST
6. **Registrar** cada sincronización: qué producto, cuánto inventario tenía antes, cuánto tiene ahora, si fue exitoso o falló

**Detalles importantes de este flujo:**

- SIESA sí tiene una API REST para consultar inventarios (endpoints que nos proporcionarán)
- La autenticación requiere primero obtener un token JWT
- Shopify también tiene API REST para actualizar inventarios
- Debemos manejar "batches" o "lotes" de sincronización: agrupar todas las sincronizaciones de un mismo ciclo
- Si falla la sincronización de un producto, no debe detener el proceso de los demás
- El SKU es el campo común entre ambos sistemas (mismo código de producto)

---

## 📋 ARQUITECTURA Y PRINCIPIOS QUE DEBEMOS SEGUIR

Tenemos un **manual de arquitectura Laravel muy específico** que debemos seguir religiosamente. Los principios clave son:

### **Separación de Responsabilidades**

Cada componente tiene UN solo propósito:

- **Controladores**: Solo orquestan. Reciben la petición, llaman a servicios, manejan transacciones y devuelven respuestas. NO hacen lógica de negocio.
- **Servicios**: Contienen TODA la lógica de negocio. Procesan datos, toman decisiones, ejecutan algoritmos complejos.
- **Repositorios**: SOLO acceso a datos. Queries complejas a base de datos.
- **Jobs**: Tareas que se ejecutan en segundo plano (asíncronas, en cola)
- **Modelos**: Representan las entidades de la base de datos (Eloquent)

### **Reglas Estrictas para Controladores**

- **NO usar constructores** para inyección de dependencias
- **Inyectar servicios directamente en los métodos** (Laravel lo hace automático)
- **SIEMPRE usar transacciones** cuando se tocan múltiples entidades
- **`DB::beginTransaction()` debe estar FUERA del try-catch** (esto es crítico)
- **Solo UN catch por método**, capturando `Exception` genérica
- **Usar método `internalErrorResponse($e)` para manejar errores**
- **USAR ENUMs en lugar de strings hardcodeados** para estados y constantes
- Operaciones CRUD simples (find, delete, updates básicos) SÍ pueden ir en controladores
- Operaciones complejas DEBEN ir en servicios

### **Reglas para Servicios**

- **LANZAN excepciones**, NO las manejan (eso lo hace el controlador)
- Pueden tener métodos privados auxiliares
- Máximo 20 líneas por función
- Usar "return early" pattern (validar al inicio y retornar temprano)
- NO hacer queries directos a DB, usar repositorios

### **Reglas para Repositorios**

- SOLO acceso a datos
- Queries usando Eloquent o Query Builder
- NO lógica de negocio

### **Uso de ENUMs**

Para TODOS los estados y valores constantes debemos usar ENUMs de PHP 8.1+:

- Estados de pedidos: `OrderStatusEnum::PENDING`, `OrderStatusEnum::COMPLETED`, etc.
- Niveles de logs: `OrderLogLevelEnum::INFO`, `OrderLogLevelEnum::ERROR`, etc.
- NUNCA strings como `'pending'`, `'completed'`, etc.

---

## 🗄️ ESTRUCTURA DE BASE DE DATOS

### **Decisión Importante: Base de Datos Simplificada**

Después de analizar las necesidades reales del proyecto, decidimos simplificar la estructura de base de datos para evitar redundancia y mantener el proyecto más limpio.

**Filosofía:**

- El JSON del webhook de Shopify tiene TODA la información (cliente, productos, totales, shipping, etc.)
- NO vamos a duplicar esa información en columnas individuales
- Cuando necesitemos consultar detalles, parseamos el JSON
- Solo guardamos lo esencial para el flujo del sistema

### **TABLAS PARA PEDIDOS (Flujo 1)**

#### **Tabla `orders` - SIMPLIFICADA**

Esta tabla sirve para:

- Auditoría de qué pedidos se procesaron
- Debugging cuando algo falle
- Reintentos de pedidos fallidos
- Panel administrativo básico
- Trazabilidad del proceso

**Campos:**

- `id` - Primary key
- `shopify_order_id` - VARCHAR(255), UNIQUE, INDEXED - ID único de Shopify para evitar duplicados
- `shopify_order_number` - VARCHAR(50) - Número del pedido para display (#1001, #1002, etc.)
- `order_json` - LONGTEXT - JSON COMPLETO del webhook (aquí está TODA la info: cliente, items, totales, etc.)
- `flat_file_name` - VARCHAR(255) - Nombre del archivo generado (ej: PEDIDO_1001.txt)
- `flat_file_path` - VARCHAR(500) - Ruta completa donde se guardó el archivo
- `status` - ENUM('pending', 'processing', 'completed', 'failed')
- `error_message` - TEXT - Mensaje de error si algo falló
- `attempts` - INT DEFAULT 0 - Contador de intentos de procesamiento
- `processed_at` - TIMESTAMP NULL - Cuándo se completó exitosamente
- `created_at` - TIMESTAMP
- `updated_at` - TIMESTAMP

**Índices importantes:**

- Index en `shopify_order_id`
- Index en `status`
- Index en `created_at`

#### **Tabla `order_logs`**

Para trazabilidad detallada del proceso:

**Campos:**

- `id` - Primary key
- `order_id` - BIGINT UNSIGNED - Foreign key a orders
- `level` - ENUM('info', 'warning', 'error')
- `message` - TEXT - Mensaje descriptivo del log
- `context` - JSON - Información adicional si es necesario
- `created_at` - TIMESTAMP

**Índices:**

- Foreign key a orders con ON DELETE CASCADE
- Index en `order_id`
- Index en `level`

### **TABLAS PARA INVENTARIOS (Flujo 2)**

Estas tablas SÍ tienen sentido completas porque:

- La data viene de SIESA, no de Shopify
- Necesitamos comparar valores "antes vs después"
- Los batches agrupan sincronizaciones
- Es información que generamos nosotros, no viene en un webhook

#### **Tabla `inventory_sync_batches`**

Agrupa todas las sincronizaciones de un mismo ciclo:

**Campos:**

- `id` - Primary key
- `started_at` - TIMESTAMP - Cuándo inició el batch
- `finished_at` - TIMESTAMP NULL - Cuándo terminó
- `total_products` - INT DEFAULT 0 - Total de productos procesados
- `successful_syncs` - INT DEFAULT 0 - Cuántos se sincronizaron exitosamente
- `failed_syncs` - INT DEFAULT 0 - Cuántos fallaron
- `skipped_syncs` - INT DEFAULT 0 - Cuántos se saltaron
- `status` - ENUM('running', 'completed', 'failed', 'partial')
- `error_message` - TEXT
- `created_at` - TIMESTAMP
- `updated_at` - TIMESTAMP

**Índices:**

- Index en `status`
- Index en `started_at`

#### **Tabla `inventory_syncs`**

Detalle de cada producto sincronizado:

**Campos:**

- `id` - Primary key
- `sync_batch_id` - BIGINT UNSIGNED - Foreign key a inventory_sync_batches
- `sku` - VARCHAR(100) - Código del producto
- `product_name` - VARCHAR(500)
- `shopify_product_id` - VARCHAR(255)
- `shopify_variant_id` - VARCHAR(255)
- `shopify_inventory_item_id` - VARCHAR(255)
- `shopify_location_id` - VARCHAR(255)
- `siesa_quantity` - INT - Cantidad consultada de SIESA
- `shopify_quantity_before` - INT - Inventario anterior en Shopify
- `shopify_quantity_after` - INT - Inventario actualizado en Shopify
- `status` - ENUM('pending', 'success', 'failed', 'skipped')
- `error_message` - TEXT
- `synced_at` - TIMESTAMP NULL
- `created_at` - TIMESTAMP

**Índices:**

- Foreign key a inventory_sync_batches con ON DELETE CASCADE
- Index en `sync_batch_id`
- Index en `sku`
- Index en `status`

---

## 📁 ORGANIZACIÓN DEL CÓDIGO

El proyecto debe tener una estructura muy organizada siguiendo las convenciones de Laravel y nuestro manual de arquitectura:

### **ENUMs** (app/Enums/)

Todos los estados y constantes deben ser ENUMs:

- `OrderStatusEnum.php` - Estados de pedidos (PENDING, PROCESSING, COMPLETED, FAILED)
- `OrderLogLevelEnum.php` - Niveles de logs (INFO, WARNING, ERROR)
- `InventorySyncStatusEnum.php` - Estados de sincronización individual (PENDING, SUCCESS, FAILED, SKIPPED)
- `SyncBatchStatusEnum.php` - Estados de batch (RUNNING, COMPLETED, FAILED, PARTIAL)

### **Controladores** (app/Http/Controllers/)

**API** (para webhooks y endpoints externos):

- `ShopifyWebhookController.php` - Recibe webhooks de Shopify
  - Método `ordersCreate()` - Webhook de creación de pedidos
  - SOLO orquesta: valida, crea registro, despacha job, retorna respuesta
  - NO contiene lógica de negocio

**Admin** (para panel administrativo):

- `OrderController.php` - CRUD y gestión de pedidos
  - index() - Lista de pedidos con filtros
  - show() - Detalle de un pedido
  - retry() - Reintentar pedido fallido
- `InventorySyncController.php` - Gestión de sincronizaciones
  - index() - Lista de batches
  - show() - Detalle de un batch

### **Middleware** (app/Http/Middleware/)

- `VerifyShopifyWebhook.php` - Valida firma HMAC de webhooks de Shopify
  - Verifica que el request venga realmente de Shopify
  - Rechaza requests no autorizados
  - Registra intentos de acceso no autorizado

### **Requests** (app/Http/Requests/)

- `RetryOrderRequest.php` - Validación para reintentar pedidos
  - Valida que el pedido exista
  - Valida que esté en estado failed
  - Valida límite de reintentos

### **Servicios** (app/Services/)

**Shopify/**:

- `ShopifyOrderProcessor.php` - Servicio principal de procesamiento de pedidos
  - Extrae datos del JSON
  - Coordina generación de archivo
  - Actualiza estado del pedido
  - Registra logs
  - LANZA excepciones si algo falla
- `ShopifyInventoryUpdater.php` - Actualiza inventarios en Shopify
  - Busca producto por SKU
  - Obtiene inventory_item_id
  - Actualiza cantidad vía API
  - Maneja rate limits
- `ShopifyApiClient.php` - Cliente HTTP para API de Shopify
  - Configuración de autenticación
  - Métodos para GET, POST, PUT
  - Manejo de rate limits
  - Retry con backoff exponencial

**Siesa/**:

- `SiesaFlatFileGenerator.php` - **SERVICIO MÁS CRÍTICO DEL PROYECTO**
  - Genera el archivo plano con formato de posiciones fijas
  - Usa SiesaFileStructure helper para formatear cada campo
  - Construye encabezado del pedido
  - Construye líneas de detalle (una por producto)
  - Valida longitudes y formatos
  - Retorna string con contenido completo del archivo
- `SiesaInventoryClient.php` - Consulta inventarios desde SIESA
  - Autentica y obtiene token
  - Consulta API de inventarios (masiva)
  - Parsea respuestas
  - Maneja errores de API
- `SiesaAuthService.php` - Autenticación con SIESA
  - Obtiene token JWT
  - Cachea token
  - Renueva token antes de expiración
  - Maneja errores de autenticación

**Otros:**

- `OrderLogService.php` - Servicio para registrar logs
  - Método log() para crear registros en order_logs
  - Formatea contexto en JSON
  - Facilita debugging y auditoría

### **Repositorios** (app/Repositories/)

SOLO acceso a datos, NO lógica de negocio:

- `OrderRepository.php` - Queries de pedidos
  - findById()
  - findByShopifyOrderId()
  - getFailedOrders()
  - getPendingOrders()
  - getOrdersByDateRange()
- `OrderLogRepository.php` - Queries de logs
  - getLogsByOrder()
  - getErrorLogs()
- `InventorySyncRepository.php` - Queries de sincronizaciones
  - getSyncsByBatch()
  - getFailedSyncs()
- `SyncBatchRepository.php` - Queries de batches
  - getRecentBatches()
  - getRunningBatches()

### **Jobs** (app/Jobs/)

Tareas asíncronas en cola:

- `ProcessShopifyOrder.php` - Procesa un pedido de Shopify
  - Recibe order_id en constructor
  - Llama a ShopifyOrderProcessor
  - Registra logs
  - Actualiza estado del pedido
  - Si falla, incrementa attempts y registra error
  - Puede reintentar automáticamente (configuración de Laravel)
- `SyncInventoryFromSiesa.php` - Sincroniza inventario desde SIESA
  - Crea batch de sincronización
  - Consulta inventarios en SIESA
  - Por cada producto, actualiza en Shopify
  - Registra cada sincronización
  - Actualiza totales del batch
  - Si falla un producto, continúa con los demás

### **Modelos** (app/Models/)

Eloquent models:

- `Order.php`
  - Relación hasMany con OrderLog
  - Cast de order_json a array
  - Scopes para filtros (completed, failed, pending)
  - Accessor para obtener datos del JSON fácilmente
- `OrderLog.php`
  - Relación belongsTo con Order
  - Cast de context a array
- `InventorySyncBatch.php`
  - Relación hasMany con InventorySync
- `InventorySync.php`
  - Relación belongsTo con InventorySyncBatch

### **Helpers** (app/Helpers/)

**SiesaFileStructure.php** - **MUY IMPORTANTE**

Este helper define las constantes de posiciones del archivo plano y funciones de formateo:

**Constantes de posiciones** (se definirán según documentación exacta de SIESA):

- HEADER_ORDER_NUMBER_START, HEADER_ORDER_NUMBER_LENGTH
- HEADER_NIT_START, HEADER_NIT_LENGTH
- HEADER_DATE_START, HEADER_DATE_LENGTH
- DETAIL_ITEM_CODE_START, DETAIL_ITEM_CODE_LENGTH
- DETAIL_UNIT_START, DETAIL_UNIT_LENGTH
- DETAIL_QUANTITY_START, DETAIL_QUANTITY_LENGTH
- DETAIL_PRICE_START, DETAIL_PRICE_LENGTH
- Etc.

**Funciones helper:**

- `padRight(string $value, int $length): string` - Rellena con espacios a la derecha (para campos alfanuméricos)
- `padLeft(string $value, int $length, string $char = '0'): string` - Rellena con ceros a la izquierda (para campos numéricos)
- `formatDate(?string $date = null): string` - Formatea fecha a AAAAMMDD
- `formatQuantity(float $quantity, int $length = 15): string` - Formatea cantidad con decimales implícitos (ej: 5.0 → "000000005000")
- `formatPrice(float $price, int $length = 20): string` - Formatea precio con decimales implícitos

**ShopifyHelper.php**

Funciones auxiliares para trabajar con datos de Shopify:

- Extraer metafields
- Formatear datos de cliente
- Calcular totales
- Etc.

### **Comandos Artisan** (app/Console/Commands/)

- `SyncInventoryCommand.php` - Comando para ejecutar sincronización manual
  - Signature: `inventory:sync`
  - Puede recibir opciones (--sku para producto específico)
  - Despacha job SyncInventoryFromSiesa
  - Muestra progreso en consola
- `RetryFailedOrdersCommand.php` - Reintentar pedidos fallidos automáticamente
  - Signature: `orders:retry-failed`
  - Busca pedidos en estado failed con attempts < 3
  - Despacha jobs para reprocesar
  - Configurable en cron para ejecución periódica

### **Configuración** (config/)

**config/shopify.php** - Todas las configuraciones de Shopify:

- `shop_domain` - Dominio de la tienda (ej: mitienda.myshopify.com)
- `api_key` - API Key de la app
- `api_secret` - API Secret
- `access_token` - Access Token
- `api_version` - Versión del API (ej: 2024-01)
- `webhook_secret` - Secret para validar webhooks
- `inventory_location_id` - ID de la ubicación de inventario
- `customer_nit_metafield` - Metafield donde está el NIT del cliente (ej: custom.nit)

**config/siesa.php** - Todas las configuraciones de SIESA:

- `api_url` - URL base del API (ej: http://localhost:8000)
- `username` - Usuario para autenticación
- `password` - Contraseña (ya viene encriptada)
- `token_endpoint` - Endpoint para obtener token (/token)
- `inventory_endpoint` - Endpoint para consultar inventarios (/api/CONSINV1)
- `flat_files_path` - Path donde se guardan archivos planos (siesa/pedidos)
- `file_prefix` - Prefijo de archivos (PEDIDO\_)
- `default_warehouse` - Bodega por defecto
- `default_unit_code` - Código de unidad por defecto (UND)
- `api_timeout` - Timeout para requests (30 segundos)
- `max_retries` - Máximo de reintentos (3)

### **Migraciones** (database/migrations/)

Orden cronológico importante por las foreign keys:

- `2026_02_12_000001_create_orders_table.php`
- `2026_02_12_000002_create_order_logs_table.php`
- `2026_02_12_000003_create_inventory_sync_batches_table.php`
- `2026_02_12_000004_create_inventory_syncs_table.php`

### **Vistas Blade** (resources/views/)

**Layouts:**

- `admin/layouts/app.blade.php` - Layout principal con navegación

**Pedidos:**

- `admin/orders/index.blade.php` - Lista de pedidos:
  - Tabla con: número, fecha, cliente, total, estado
  - Filtros por estado y rango de fechas
  - Búsqueda por número o Shopify ID
  - Botón "Ver detalle"
  - Botón "Reintentar" para fallidos
  - Paginación
- `admin/orders/show.blade.php` - Detalle de pedido:
  - Info general (número, fecha, estado, intentos)
  - Info del cliente (extraída del JSON)
  - Tabla de productos (parseada del JSON)
  - JSON original en collapsible (formateado)
  - Contenido del archivo plano (textarea read-only, monospace)
  - Timeline de logs
  - Botón "Reintentar" si está en failed
  - Botón "Descargar archivo"

**Inventario:**

- `admin/inventory/index.blade.php` - Lista de batches:
  - Tabla con: fecha, total productos, exitosos, fallidos, duración, estado
  - Filtros por fecha y estado
  - Link a detalle de cada batch
- `admin/inventory/batch-detail.blade.php` - Detalle de batch:
  - Info del batch
  - Tabla de productos: SKU, nombre, cantidad SIESA, cantidad Shopify antes/después, estado
  - Mensajes de error para fallidos
  - Opción de reintento manual

### **Rutas** (routes/)

**routes/api.php** - Webhooks y API pública:

```php
// Webhook de Shopify (sin autenticación web, solo HMAC)
Route::post('/webhooks/shopify/orders/create', [ShopifyWebhookController::class, 'ordersCreate'])
    ->middleware('verify.shopify.webhook');
```

**routes/web.php** - Panel administrativo:

```php
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/{id}/retry', [OrderController::class, 'retry']);
    });

    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventorySyncController::class, 'index']);
        Route::get('/{batchId}', [InventorySyncController::class, 'show']);
    });
});
```

### **Storage**

- `storage/app/siesa/pedidos/` - Directorio donde se guardan los archivos planos generados
  - PEDIDO_1001.txt
  - PEDIDO_1002.txt
  - etc.

### **Tests**

**Unit Tests:**

- `tests/Unit/Services/SiesaFlatFileGeneratorTest.php` - Tests exhaustivos del generador de archivos
  - Test de formateo de cada campo
  - Test de longitudes correctas
  - Test de posiciones exactas
  - Test con datos de ejemplo
- `tests/Unit/Helpers/SiesaFileStructureTest.php` - Tests de funciones helper
  - Test de padRight
  - Test de padLeft
  - Test de formatDate
  - Test de formatQuantity
  - Test de formatPrice

**Feature Tests:**

- `tests/Feature/ShopifyWebhookTest.php` - Tests de integración del webhook
  - Test de webhook válido
  - Test de webhook con firma inválida
  - Test de pedido duplicado
  - Test de job despachado correctamente
- `tests/Feature/OrderProcessingTest.php` - Test del flujo completo
  - Test de procesamiento exitoso
  - Test de manejo de errores
  - Test de reintentos

---

## 🔄 FLUJO DETALLADO DEL PROCESAMIENTO DE PEDIDOS

Te explico paso a paso qué debe pasar cuando llega un pedido:

### **1. Shopify dispara el webhook**

Cuando se crea un pedido en Shopify, se envía:

- **Método**: POST
- **URL**: `https://tudominio.com/api/webhooks/shopify/orders/create`
- **Headers**:
  - `X-Shopify-Hmac-Sha256`: Firma HMAC del contenido
  - `X-Shopify-Shop-Domain`: Dominio de la tienda
  - `X-Shopify-Topic`: orders/create
- **Body**: JSON completo del pedido con toda la información

### **2. Middleware VerifyShopifyWebhook**

Antes de que llegue al controlador:

- Calcula HMAC del body usando el secret
- Compara con el HMAC del header
- Si NO coincide: rechaza con 401 Unauthorized
- Si coincide: permite continuar
- Registra intento de acceso en logs

### **3. ShopifyWebhookController::ordersCreate()**

El controlador recibe el request validado y:

1. Llama a `DB::beginTransaction()` FUERA del try-catch
2. Dentro del try:
   - Crea registro en tabla `orders` con:
     - shopify_order_id del JSON
     - shopify_order_number del JSON
     - order_json = JSON completo encoded
     - status = OrderStatusEnum::PENDING
   - Hace commit de la transacción
   - Despacha job `ProcessShopifyOrder::dispatch($order->id)`
   - Retorna `response()->json(['success' => true], 200)`
3. Si falla, hace rollback y retorna error

**Importante**: El controlador NO hace nada más. La lógica está en el Job y los Services.

### **4. Worker de Laravel ejecuta ProcessShopifyOrder Job**

El job se ejecuta en background:

1. Recibe order_id en el constructor
2. En el método handle():
   - Inyecta ShopifyOrderProcessor, OrderRepository, OrderLogService
   - Busca el pedido: `$order = $orderRepository->find($this->orderId)`
   - Registra log de inicio
   - Llama a `$processor->process($order)`
   - Registra log de éxito
3. Si falla:
   - Captura la excepción
   - Registra log de error
   - Actualiza order:
     - status = OrderStatusEnum::FAILED
     - error_message = $e->getMessage()
     - attempts++
   - Re-lanza la excepción para que Laravel maneje el retry

### **5. ShopifyOrderProcessor::process()**

El servicio principal que contiene la lógica:

1. **Decodifica el JSON**:

   ```php
   $orderData = json_decode($order->order_json, true);
   ```

2. **Extrae información del cliente**:
   - Busca el NIT en metafields del customer
   - Extrae nombre, email, teléfono
   - Guarda referencias para el archivo

3. **Extrae información de productos**:
   - Itera sobre `$orderData['line_items']`
   - Por cada producto extrae: SKU, nombre, cantidad, precio

4. **Llama al generador de archivo**:

   ```php
   $flatFileContent = $this->siesaFileGenerator->generate($order, $orderData);
   ```

5. **Guarda el archivo físico**:

   ```php
   $fileName = "PEDIDO_{$order->shopify_order_number}.txt";
   $filePath = config('siesa.flat_files_path') . '/' . $fileName;
   Storage::put($filePath, $flatFileContent);
   ```

6. **Actualiza el pedido**:

   ```php
   $order->update([
       'flat_file_name' => $fileName,
       'flat_file_path' => Storage::path($filePath),
       'status' => OrderStatusEnum::COMPLETED,
       'processed_at' => now(),
   ]);
   ```

7. **Registra logs en cada paso**:
   ```php
   $this->logService->log($order, OrderLogLevelEnum::INFO, 'Archivo generado exitosamente');
   ```

Si algo falla en cualquier punto, LANZA una excepción que el Job capturará.

### **6. SiesaFlatFileGenerator::generate()**

**El servicio MÁS CRÍTICO del proyecto**. Genera el archivo con formato exacto:

1. **Inicializa el contenido**:

   ```php
   $content = '';
   ```

2. **Genera encabezado**:

   ```php
   $content .= $this->generateHeader($orderData);
   ```

   - Usa SiesaFileStructure::padRight() para campos alfanuméricos
   - Usa SiesaFileStructure::padLeft() para campos numéricos
   - Usa SiesaFileStructure::formatDate() para fechas
   - CADA campo va en su posición exacta con su longitud exacta

3. **Genera líneas de detalle (una por producto)**:

   ```php
   foreach ($orderData['line_items'] as $item) {
       $content .= $this->generateDetailLine($item);
   }
   ```

   - Por cada producto, formatea: SKU, cantidad, precio, unidad
   - Usa las funciones helper para formatear correctamente
   - Asegura que cada línea tenga la longitud exacta

4. **Retorna el contenido completo**:
   ```php
   return $content;
   ```

**Ejemplo de cómo se usa SiesaFileStructure**:

```php
private function generateHeader(array $orderData): string
{
    $header = '';

    // Número de pedido (posición 0, longitud 30)
    $orderNumber = $orderData['order_number'];
    $header .= SiesaFileStructure::padRight($orderNumber, 30);

    // NIT (posición 30, longitud 20)
    $nit = $orderData['customer']['metafields']['nit'] ?? '';
    $header .= SiesaFileStructure::padRight($nit, 20);

    // Fecha (posición 50, longitud 8)
    $date = SiesaFileStructure::formatDate($orderData['created_at']);
    $header .= $date;

    // ... continúa con todos los campos del encabezado

    return $header;
}
```

### **7. OrderLogService::log()**

Servicio simple para crear logs:

```php
public function log(Order $order, OrderLogLevelEnum $level, string $message, array $context = []): void
{
    OrderLog::create([
        'order_id' => $order->id,
        'level' => $level,
        'message' => $message,
        'context' => $context,
    ]);
}
```

---

## 🔐 AUTENTICACIÓN CON SIESA (Para Fase 2)

Para consultar inventarios en SIESA necesitamos:

### **1. Obtener Token JWT**

**SiesaAuthService::getToken()**:

1. Verifica si hay token en cache y no ha expirado
2. Si no hay token o expiró:
   - Hace POST a `{siesa_api_url}/token`
   - Body: `application/x-www-form-urlencoded`
   - Datos: `username={usuario}&password={contraseña_encriptada}`
   - Recibe JWT en respuesta
   - Guarda en cache con TTL
3. Retorna el token

### **2. Consultar Inventarios**

**SiesaInventoryClient::getInventory()**:

1. Obtiene token de autenticación
2. Hace POST a `{siesa_api_url}/api/CONSINV1`
3. Headers:
   - `Authorization: Bearer {token}`
   - `Content-Type: text/plain`
4. Body: Puede ser JSON con filtros (productos específicos o todos)
5. Recibe respuesta en JSON con array de productos:
   ```json
   {
     "respuesta_exitosa": [
       {
         "bodega": "001",
         "item": "12345",
         "ditem": "Producto XYZ",
         "unimed1": "UND",
         "dispon1": "100",
         "preciovig": "50000"
       }
     ]
   }
   ```
6. Parsea y retorna array estructurado

### **3. Manejo de Errores**

- Si recibe 401: Token expiró, renovar y reintentar
- Si recibe 403: Token inválido, obtener nuevo
- Si recibe 500: Error de servidor SIESA, registrar y fallar
- Si recibe 404: Endpoint no válido

---

## 📊 INTEGRACIÓN CON SHOPIFY (Para Fase 2)

### **1. ShopifyApiClient**

Cliente HTTP para comunicarse con Shopify:

**Configuración:**

- Base URL: `https://{shop_domain}/admin/api/{version}/`
- Headers:
  - `X-Shopify-Access-Token: {access_token}`
  - `Content-Type: application/json`

**Métodos:**

- `get(string $endpoint): array`
- `post(string $endpoint, array $data): array`
- `put(string $endpoint, array $data): array`

**Manejo de Rate Limits:**

- Shopify tiene límite de 2 requests por segundo
- Si recibe 429 (Too Many Requests):
  - Leer header `Retry-After`
  - Esperar ese tiempo
  - Reintentar
- Usar backoff exponencial para reintentos

### **2. ShopifyInventoryUpdater::updateInventory()**

Actualiza inventario de un producto:

1. **Buscar producto por SKU**:
   - GET `/products.json?fields=id,variants&limit=250`
   - Filtrar por SKU en los variants
   - Obtener `inventory_item_id` del variant

2. **Actualizar inventario**:
   - POST `/inventory_levels/set.json`
   - Body:
     ```json
     {
       "location_id": "{location_id}",
       "inventory_item_id": "{inventory_item_id}",
       "available": 100
     }
     ```
   - Recibe confirmación

3. **Registrar sincronización**:
   - Crear registro en `inventory_syncs`
   - Estado SUCCESS o FAILED
   - Cantidades antes/después

---

## 📝 CASOS DE USO CON LA ESTRUCTURA SIMPLIFICADA

### **En el Procesamiento (Service/Job)**

```php
// Obtener datos del JSON
$orderData = json_decode($order->order_json, true);

// Extraer cliente
$customer = $orderData['customer'];
$customerNit = $customer['metafields']['nit'] ?? 'N/A';
$customerName = $customer['first_name'] . ' ' . $customer['last_name'];
$customerEmail = $customer['email'];

// Extraer productos
$lineItems = $orderData['line_items'];
foreach ($lineItems as $item) {
    $sku = $item['sku'];
    $name = $item['name'];
    $quantity = $item['quantity'];
    $price = $item['price'];
    // Usar estos datos para generar el archivo
}

// Extraer totales
$subtotal = $orderData['subtotal_price'];
$tax = $orderData['total_tax'];
$total = $orderData['total_price'];
```

### **En el Panel Admin (Controller/Blade)**

```php
// En el controlador
public function show(int $id): View
{
    $order = Order::with('logs')->findOrFail($id);
    $orderData = json_decode($order->order_json, true);

    return view('admin.orders.show', [
        'order' => $order,
        'customer' => [
            'name' => $orderData['customer']['first_name'] . ' ' . $orderData['customer']['last_name'],
            'email' => $orderData['customer']['email'],
            'phone' => $orderData['customer']['phone'] ?? 'N/A',
            'nit' => $orderData['customer']['metafields']['nit'] ?? 'N/A',
        ],
        'lineItems' => $orderData['line_items'],
        'shipping' => $orderData['shipping_address'],
        'totals' => [
            'subtotal' => $orderData['subtotal_price'],
            'tax' => $orderData['total_tax'],
            'shipping' => $orderData['shipping_lines'][0]['price'] ?? 0,
            'total' => $orderData['total_price'],
        ],
        'logs' => $order->logs,
    ]);
}
```

### **En el Modelo Order**

```php
// Accessor para facilitar acceso a datos del JSON
public function getCustomerNameAttribute(): string
{
    $data = json_decode($this->order_json, true);
    $customer = $data['customer'] ?? [];
    return ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
}

public function getLineItemsAttribute(): array
{
    $data = json_decode($this->order_json, true);
    return $data['line_items'] ?? [];
}

public function getTotalPriceAttribute(): float
{
    $data = json_decode($this->order_json, true);
    return (float) ($data['total_price'] ?? 0);
}

// Scopes para filtros
public function scopeCompleted($query)
{
    return $query->where('status', OrderStatusEnum::COMPLETED);
}

public function scopeFailed($query)
{
    return $query->where('status', OrderStatusEnum::FAILED);
}

public function scopePending($query)
{
    return $query->where('status', OrderStatusEnum::PENDING);
}
```

---

## ⚠️ PUNTOS CRÍTICOS Y CONSIDERACIONES

### **1. SiesaFlatFileGenerator es LA PIEZA CLAVE**

Este servicio es el corazón del proyecto. Un solo error en el formato y SIESA no podrá leer el pedido.

**Requisitos:**

- Documentación EXACTA de posiciones de cada campo (viene en RECEPCION_PEDIDOS_8_5C.doc)
- Tests exhaustivos para verificar formato byte por byte
- Ejemplos de archivos válidos para comparar (tenemos PEDIDO046135.txt)
- Validación rigurosa de longitudes

**¿Por qué es crítico?**

- Formato de posiciones fijas: cada carácter cuenta
- Si el campo "cantidad" está en posición 31 con longitud 15, debe ser exactamente ahí
- El padding incorrecto rompe todo
- SIESA no da feedback si está mal, simplemente no procesa

### **2. Manejo Robusto de Errores**

**En Servicios:**

- SIEMPRE lanzar excepciones específicas
- NO capturar excepciones en servicios
- Mensajes de error descriptivos

**En Jobs:**

- Capturar excepciones
- Registrar en order_logs
- Actualizar estado del pedido
- Permitir reintentos automáticos de Laravel

**En Controladores:**

- Un solo catch(Exception $e)
- Usar internalErrorResponse($e)
- DB::beginTransaction() FUERA del try

### **3. Extracción del NIT del Cliente**

El NIT es crítico para SIESA. Necesitamos definir de dónde extraerlo en Shopify:

**Opciones:**

1. **Metafields del customer**: `$customer['metafields']['nit']`
2. **Note attributes del order**: `$order['note_attributes']` (campo nit)
3. **Tags del order**: parsear tags buscando "NIT:123456"
4. **Custom field en checkout**: campo adicional en el checkout

**Recomendación**: Usar metafields del customer porque es más estructurado y persistente.

**Fallback**: Si no hay NIT, usar un valor por defecto o marcar el pedido como pendiente de revisión.

### **4. Performance y Escalabilidad**

**Jobs en Cola:**

- Usar Redis para queue driver (más rápido que database)
- Configurar workers suficientes
- Monitorear cola para evitar saturación

**Sincronización de Inventario:**

- NO consultar productos uno por uno
- Usar endpoint masivo de SIESA (CONSINV1)
- Batch updates en Shopify cuando sea posible
- Configurar frecuencia apropiada (cada 30 min o 1 hora)

**Cache:**

- Cachear token de SIESA (dura X minutos)
- Cachear datos de productos si son estables
- Usar tags para invalidar cache selectivamente

**Índices en BD:**

- shopify_order_id (para búsquedas y evitar duplicados)
- status (para filtros en panel)
- created_at (para queries por fecha)
- SKU en inventory_syncs (para búsquedas)

### **5. Seguridad**

**Validación de Webhooks:**

- SIEMPRE validar HMAC de Shopify
- Rechazar requests sin firma válida
- Registrar intentos no autorizados

**Datos Sensibles:**

- NO exponer JSON completo en logs públicos
- Sanitizar datos antes de mostrar en panel
- Proteger archivos planos (solo lectura para SIESA)

**Autenticación del Panel:**

- Usar autenticación de Laravel
- Middleware 'auth' en todas las rutas admin
- Roles si hay múltiples usuarios

### **6. Datos del Archivo Plano**

**Consideraciones:**

- Un pedido = UN archivo
- Múltiples productos = múltiples líneas de detalle en el mismo archivo
- Nombre del archivo: `PEDIDO_{shopify_order_number}.txt`
- Ubicación: `storage/app/siesa/pedidos/`
- SIESA debe tener acceso de lectura a ese directorio
- Después de procesar, SIESA debe mover/eliminar el archivo (coordinación con ellos)

### **7. Reintentos**

**Estrategia de Reintentos:**

- Máximo 3 intentos automáticos (configurable en SIESA config)
- Backoff exponencial: 1 min, 5 min, 15 min
- Si falla 3 veces, marcar como FAILED y requerir intervención manual
- Panel admin permite reintento manual

**¿Cuándo reintentar?**

- Error de conexión con SIESA
- Error temporal de Shopify API
- Timeout
- NO reintentar si error es de validación o datos faltantes

### **8. Logs y Auditoría**

**Qué registrar:**

- Cada paso del procesamiento
- Errores con stack trace
- Cambios de estado
- Tiempos de ejecución
- Reintentos

**Dónde:**

- order_logs para logs de pedidos
- Laravel log para errores de sistema
- Considerar log rotation para no llenar disco

### **9. Monitoreo (Opcional pero Recomendado)**

- Alertas si la tasa de fallos supera X%
- Notificaciones por email/Slack cuando hay errores críticos
- Monitorear tamaño de la cola
- Monitorear tiempos de respuesta de APIs

---

## 🚀 ORDEN DE DESARROLLO RECOMENDADO

Te sugiero construir el proyecto en este orden para minimizar dependencias:

### **Fase 1A: Foundation (Setup Inicial)**

1. **Crear proyecto Laravel**
2. **Configurar .env con credenciales** (Shopify, SIESA)
3. **Migraciones**:
   - orders
   - order_logs
4. **Modelos** básicos (Order, OrderLog)
5. **ENUMs** (OrderStatusEnum, OrderLogLevelEnum)
6. **Archivos de configuración** (config/shopify.php, config/siesa.php)

### **Fase 1B: Generador de Archivos (CRÍTICO)**

7. **SiesaFileStructure helper**:
   - Definir TODAS las constantes de posiciones (según doc)
   - Implementar funciones de formateo
   - Tests unitarios exhaustivos
8. **SiesaFlatFileGenerator service**:
   - Método generate()
   - Método generateHeader()
   - Método generateDetailLine()
   - Tests con archivo de ejemplo PEDIDO046135.txt

### **Fase 1C: Procesamiento de Pedidos**

9. **OrderLogService** (simple, para registrar logs)
10. **OrderRepository** (queries básicas)
11. **ShopifyOrderProcessor service**:
    - Método process()
    - Extracción de datos del JSON
    - Integración con SiesaFlatFileGenerator
    - Guardado de archivo
    - Registro de logs
12. **ProcessShopifyOrder job**:
    - Handle method
    - Manejo de errores
    - Actualización de estados

### **Fase 1D: Webhook**

13. **VerifyShopifyWebhook middleware**:
    - Validación HMAC
    - Tests de validación
14. **ShopifyWebhookController**:
    - Método ordersCreate()
    - Transacciones
    - Despacho de job
15. **Ruta API** para el webhook
16. **Test de integración** del flujo completo

### **Fase 1E: Panel Administrativo**

17. **OrderController**:
    - index() - lista de pedidos
    - show() - detalle
    - retry() - reintentar
18. **Vistas Blade**:
    - Layout principal
    - Lista de pedidos
    - Detalle de pedido
19. **Rutas web** con autenticación

### **Fase 2A: Inventarios - Setup**

20. **Migraciones**:
    - inventory_sync_batches
    - inventory_syncs
21. **Modelos** (InventorySyncBatch, InventorySync)
22. **ENUMs** para inventarios
23. **Repositorios** de inventario

### **Fase 2B: Integración SIESA Inventarios**

24. **SiesaAuthService**:
    - Obtener token
    - Cache de token
    - Renovación
25. **SiesaInventoryClient**:
    - Consultar inventarios
    - Parsear respuestas
    - Manejo de errores

### **Fase 2C: Integración Shopify Inventarios**

26. **ShopifyApiClient**:
    - Métodos GET, POST, PUT
    - Manejo de rate limits
    - Reintentos con backoff
27. **ShopifyInventoryUpdater**:
    - Buscar producto por SKU
    - Actualizar inventario
    - Registro de sincronización

### **Fase 2D: Sincronización**

28. **SyncInventoryFromSiesa job**:
    - Crear batch
    - Consultar SIESA
    - Actualizar Shopify
    - Registrar resultados
29. **SyncInventoryCommand**:
    - Comando artisan
    - Configuración en cron
30. **Vistas de inventario**:
    - Lista de batches
    - Detalle de batch
31. **InventorySyncController**

### **Fase 3: Extras y Optimizaciones**

32. **RetryFailedOrdersCommand** (reintentos automáticos)
33. **Tests de integración completos**
34. **Optimizaciones de performance**
35. **Documentación**
36. **Deployment**

---

## 💡 LO QUE NECESITO DE TI, COPILOT

Cuando te pida generar código o ayuda con algo específico:

### **1. SIGUE ESTRICTAMENTE LAS CONVENCIONES DEL MANUAL**

- Controladores SIN lógica de negocio
- Inyección de dependencias en MÉTODOS, no en constructor
- DB::beginTransaction() FUERA del try-catch
- Un solo catch(Exception $e) por método
- ENUMs SIEMPRE, nunca strings hardcodeados
- Servicios lanzan excepciones, controladores las manejan

### **2. CÓDIGO LIMPIO Y DOCUMENTADO**

- Docblocks en métodos públicos
- Type hints SIEMPRE
- Comentarios inline solo donde algo no sea obvio
- Nombres descriptivos (no abreviaturas raras)
- Máximo 20 líneas por función

### **3. CONVENCIONES DE NOMBRES**

- **Clases**: PascalCase (OrderController, SiesaFlatFileGenerator)
- **Métodos y variables**: camelCase (generateHeader, orderData)
- **Tablas y columnas de BD**: snake_case (order_id, shopify_order_number)
- **Constantes**: SCREAMING_SNAKE_CASE (HEADER_ORDER_NUMBER_START)
- **ENUMs**: PascalCase para clase, SCREAMING_SNAKE_CASE para valores

### **4. VALIDACIÓN Y SEGURIDAD**

- Form Requests para validación de input
- Lanzar excepciones cuando algo no cumple reglas
- Return early para validaciones
- Sanitizar datos antes de guardar

### **5. PREGUNTA SI ALGO NO ESTÁ CLARO**

- Si necesitas más contexto, pregunta
- Si ves algo que podría mejorarse, sugiere
- Si algo rompe convenciones, alerta
- Si hay ambigüedad, pide clarificación

### **6. MANEJO DE CASOS EDGE**

- ¿Qué pasa si Shopify envía datos inesperados?
- ¿Qué pasa si SIESA no responde?
- ¿Qué pasa si un pedido ya fue procesado?
- ¿Qué pasa si falta el NIT del cliente?
- Piensa en estos escenarios

---

## 📚 ARCHIVOS DE REFERENCIA QUE TENDRÉ

Te voy a compartir estos archivos para que entiendas exactamente qué necesitamos:

1. **PEDIDO046135.txt**: Ejemplo de archivo plano VÁLIDO generado por SIESA
   - Úsalo como referencia para tests
   - Compara output de tu generador con este
   - Valida longitudes y posiciones

2. **RECEPCION_PEDIDOS_8_5C.doc**: Documentación oficial de SIESA
   - Estructura EXACTA del archivo plano
   - Posiciones y longitudes de cada campo
   - Formatos requeridos
   - **ESTA ES LA BIBLIA para SiesaFlatFileGenerator**

3. **API*CONSULTA_DE_INVENTARIOS_8_5*-\_JSON.doc**: API de SIESA para inventario individual
   - Request y response
   - Campos disponibles

4. **API*CONSULTA_DE_INVENTARIOS_8_5*-_MASIVA_-\_JSON.doc**: API masiva de SIESA
   - Para consultar múltiples productos
   - **Usar esta para sincronización**

5. **manual-arquitectura-codigo-laravel.md**: Nuestro manual de arquitectura
   - **SEGUIR AL PIE DE LA LETRA**
   - Convenciones no negociables
   - Ejemplos de código correcto e incorrecto

## Todos estos archivos están en .github/documentacion

---

## 🎯 ENFOQUE DE TRABAJO

Vamos a trabajar **iterativamente** y **paso por paso**:

- **Una cosa a la vez**: No me generes todo de golpe
- **Pregunta antes de asumir**: Si algo no está claro, pregunta
- **Sugiere mejoras**: Si ves una mejor forma, dímelo
- **Alerta problemas**: Si algo rompe las reglas, avisa
- **Piensa en producción**: Este código va a manejar pedidos REALES

**Calidad sobre velocidad**: Prefiero código bien hecho que código rápido.

---

## ✅ CHECKLIST DE REVISIÓN

Antes de considerar algo "terminado", verifica:

- [ ] ¿Sigue las convenciones del manual de arquitectura?
- [ ] ¿Controladores solo orquestan?
- [ ] ¿Servicios contienen la lógica de negocio?
- [ ] ¿Se usan ENUMs en lugar de strings?
- [ ] ¿DB::beginTransaction() está fuera del try-catch?
- [ ] ¿Hay un solo catch por método?
- [ ] ¿Las dependencias se inyectan en métodos?
- [ ] ¿Los nombres son descriptivos?
- [ ] ¿Hay type hints?
- [ ] ¿Hay docblocks en métodos públicos?
- [ ] ¿El código es testeable?
- [ ] ¿Se manejan casos edge?
- [ ] ¿Se validan los datos de entrada?
- [ ] ¿Las excepciones tienen mensajes descriptivos?

---

## 🎬 ¿LISTO PARA EMPEZAR?

Cuando compartamos estos archivos y empecemos a trabajar:

1. Primero te pediré que revises los documentos
2. Luego iremos paso por paso según el orden de desarrollo
3. Empezaremos por las migraciones y estructura base
4. Luego el generador de archivos (lo MÁS crítico)
5. Después el flujo completo de procesamiento
6. Finalmente el panel admin y la sincronización de inventarios

**Recuerda**: Estamos construyendo algo que va a producción, que va a manejar pedidos reales de clientes reales, y que debe ser robusto, mantenible y escalable.

¿Estás listo para construir este proyecto juntos?
