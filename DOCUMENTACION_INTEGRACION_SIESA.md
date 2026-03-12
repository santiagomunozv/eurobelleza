# Documentación Técnica - Integración Shopify → SIESA

## Índice

1. [Resumen del Sistema](#resumen-del-sistema)
2. [Maestros y Configuraciones](#maestros-y-configuraciones)
3. [Flujo de Procesamiento](#flujo-de-procesamiento)
4. [Jobs y Colas](#jobs-y-colas)
5. [Comandos Artisan](#comandos-artisan)
6. [Sistema de Validación](#sistema-de-validación)
7. [Sincronización Automática](#sincronización-automática)
8. [Generación de Archivos SIESA](#generación-de-archivos-siesa)
9. [Logs y Trazabilidad](#logs-y-trazabilidad)
10. [Casos de Uso Comunes](#casos-de-uso-comunes)

---

## Resumen del Sistema

Sistema de integración automática entre Shopify y SIESA 8.5 que procesa pedidos pagados y genera archivos planos (.PE0 y .txt) con el formato requerido por SIESA.

### Características Principales

- 🔄 Recepción de webhooks de Shopify (orders/paid)
- ✅ Validación de configuraciones antes de procesar
- 📦 Sistema de colas para procesamiento asíncrono
- 🗺️ Mapeo de bodegas basado en ubicaciones de Shopify
- 💳 Mapeo de pasarelas de pago
- 📄 Generación de archivos planos SIESA 8.5
- 🔄 Sincronización diaria automática
- 📊 Sistema de logs completo

---

## Maestros y Configuraciones

### 1. Configuración General (SiesaGeneralConfiguration)

**Tabla:** `siesa_general_configurations`

**Propósito:** Almacena los códigos generales necesarios para generar archivos SIESA (tercero, vendedor, moneda, clase de venta).

**Campos:**

```php
- id (bigint)
- tercero_code (string, 20) - Código del tercero en SIESA
- vendedor_code (string, 20) - Código del vendedor
- moneda_code (string, 3) - Código de moneda (ej: COP)
- clase_venta_code (string, 20) - Código de clase de venta
- timestamps
```

**Validación:**

- Solo puede existir un registro activo
- Todos los campos son obligatorios

**Acceso Admin:**

- Ruta: `/admin/siesa/general-configuration`
- Vista: `resources/views/admin/siesa/general-configuration.blade.php`

**Uso en el código:**

```php
// Repository
$config = $this->siesaGeneralConfigRepository->getActiveConfiguration();

// Retorna: SiesaGeneralConfiguration | null
```

---

### 2. Mapeo de Pasarelas de Pago (SiesaPaymentGatewayMapping)

**Tabla:** `siesa_payment_gateway_mappings`

**Propósito:** Mapea las pasarelas de pago de Shopify (gateway) con los códigos de forma de pago de SIESA.

**Campos:**

```php
- id (bigint)
- shopify_gateway (string, 100, unique) - Identificador de la pasarela en Shopify
- forma_pago_code (string, 20) - Código de forma de pago en SIESA
- timestamps
```

**Ejemplos de gateways Shopify:**

- `bogota` (PSE)
- `manual` (Pago manual)
- `shopify_payments` (Tarjetas)
- `paypal`

**Acceso Admin:**

- Ruta: `/admin/siesa/payment-gateways`
- Vistas: `resources/views/admin/siesa/payment-gateways/{index,create,edit}.blade.php`

**Uso en el código:**

```php
// Repository
$mapping = $this->siesaPaymentGatewayRepository->findByShopifyGateway($gateway);

// Retorna: SiesaPaymentGatewayMapping | null
```

---

### 3. Mapeo de Ubicaciones/Bodegas (SiesaWarehouseMapping)

**Tabla:** `siesa_warehouse_mappings`

**Propósito:** Mapea las ubicaciones de Shopify (location_id de fulfillments) con códigos de bodega y ubicación de SIESA.

**Campos:**

```php
- id (bigint)
- shopify_location_id (bigint, unique) - ID de ubicación en Shopify
- shopify_location_name (string, 255) - Nombre descriptivo
- bodega_code (string, 3) - Código de bodega SIESA (posiciones 55-57)
- location_code (string, 2) - Código de ubicación SIESA (posiciones 58-59)
- timestamps
```

**Datos Seeder (5 ubicaciones):**

```
80414146731 → BODEGA BARRANQUILLA → 001/15
80414245035 → BODEGA BOGOTÁ → 001/15
80414113963 → BODEGA CALI → 001/15
80414081195 → BODEGA SABANETA → 001/15
63235489963 → Eurobelleza - Arroyo Hondo → 001/15
```

**Acceso Admin:**

- Ruta: `/admin/siesa/warehouses`
- Vistas: `resources/views/admin/siesa/warehouses/{index,create,edit}.blade.php`

**Uso en el código:**

```php
// Repository
$mapping = $this->warehouseRepository->findByShopifyLocationId($locationId);

// Retorna: SiesaWarehouseMapping | null
```

---

## Flujo de Procesamiento

### Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────────┐
│                    SHOPIFY (Order Paid)                         │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│           Webhook Controller (orders/paid)                       │
│  - Valida firma HMAC                                            │
│  - Extrae financial_status                                      │
│  - Guarda/actualiza en tabla orders                             │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
                    ¿financial_status = 'paid'?
                             │
                    ┌────────┴────────┐
                    │ NO              │ SÍ
                    ▼                 ▼
            [ORDEN PENDING]   ┌──────────────────┐
            (esperando pago)  │ VALIDAR CONFIG   │
                              │ (3 validaciones) │
                              └────────┬─────────┘
                                       │
                              ┌────────┴────────┐
                              │ ¿Válida?        │
                              └────────┬────────┘
                                       │
                              ┌────────┴────────┐
                              │ NO              │ SÍ
                              ▼                 ▼
                    [ORDEN PENDING]    ┌─────────────────┐
                    + Log error        │ ENCOLAR JOB     │
                                       │ (queue: default) │
                                       └────────┬────────┘
                                                │
                                                ▼
                                       ┌─────────────────┐
                                       │ ProcessShopify  │
                                       │ Order Job       │
                                       │ (3 reintentos)  │
                                       └────────┬────────┘
                                                │
                                                ▼
                                       ┌─────────────────┐
                                       │ Generar Archivos│
                                       │ .PE0 + .txt     │
                                       └────────┬────────┘
                                                │
                                                ▼
                                       [ORDEN COMPLETED]
                                       + Log success
                                       + Archivos en storage
```

### Flujo Detallado por Etapas

#### 1️⃣ Recepción del Webhook

**Archivo:** `app/Http/Controllers/API/ShopifyWebhookController.php`

```php
public function handleOrderPaid(Request $request)
{
    // 1. Validar firma HMAC
    if (!$this->validateHmac($request)) {
        return response()->json(['error' => 'Invalid HMAC'], 401);
    }

    // 2. Guardar/actualizar en DB
    $order = Order::updateOrCreate(
        ['shopify_order_id' => $orderData['id']],
        [
            'order_number' => $orderData['order_number'],
            'financial_status' => $orderData['financial_status'],
            'order_json' => $orderData,
            'status' => 'pending'
        ]
    );

    // 3. Si está pagado, validar configuración
    if ($financialStatus === 'paid') {
        $validation = $this->configValidator->validate($orderData);

        if ($validation['valid']) {
            // ✅ Encolar job
            ProcessShopifyOrder::dispatch($order);
        } else {
            // ❌ Guardar errores sin encolar
            $this->orderLogService->logError($order, 'configuration_validation_failed', $validation['errors']);
        }
    }
}
```

#### 2️⃣ Validación de Configuración

**Archivo:** `app/Services/OrderConfigurationValidator.php`

**3 Validaciones Obligatorias:**

```php
public function validate(array $orderData): array
{
    $errors = [];
    $details = [];

    // ✅ 1. Configuración General
    $generalConfig = $this->generalConfigRepo->getActiveConfiguration();
    if (!$generalConfig) {
        $errors[] = 'No existe configuración general activa';
    }

    // ✅ 2. Mapeo de Pasarela de Pago
    $gateway = $orderData['payment_gateway_names'][0] ?? null;
    $paymentMapping = $this->paymentGatewayRepo->findByShopifyGateway($gateway);
    if (!$paymentMapping) {
        $errors[] = "No existe mapeo para la pasarela: {$gateway}";
    }

    // ✅ 3. Mapeo de Bodega
    $fulfillments = $orderData['fulfillments'] ?? [];
    if (empty($fulfillments)) {
        $errors[] = 'El pedido no tiene información de fulfillments';
    } else {
        $locationId = $fulfillments[0]['location_id'] ?? null;
        if (!$locationId) {
            $errors[] = 'El fulfillment no tiene location_id';
        } else {
            $warehouseMapping = $this->warehouseMappingRepo->findByShopifyLocationId($locationId);
            if (!$warehouseMapping) {
                $errors[] = "No existe mapeo para la ubicación: {$locationId}";
            }
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'details' => $details
    ];
}
```

#### 3️⃣ Job de Procesamiento

**Archivo:** `app/Jobs/ProcessShopifyOrder.php`

```php
class ProcessShopifyOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;           // 3 intentos
    public $backoff = 60;        // 60 segundos entre reintentos
    public $timeout = 300;       // 5 minutos de timeout

    public function handle(
        SiesaFlatFileGenerator $generator,
        OrderLogService $logService
    ): void {
        try {
            // 1. Generar archivos
            $result = $generator->generateFromOrder($this->order);

            // 2. Actualizar estado
            $this->order->update(['status' => 'completed']);

            // 3. Log de éxito
            $logService->logSuccess($this->order, 'order_processed', 'Pedido procesado exitosamente');

        } catch (\Exception $e) {
            // Log de error
            $logService->logError($this->order, 'processing_failed', $e->getMessage());
            throw $e; // Re-lanzar para activar reintentos
        }
    }
}
```

#### 4️⃣ Generación de Archivos

**Archivo:** `app/Services/Siesa/SiesaFlatFileGenerator.php`

```php
public function generateFromOrder(Order $order): array
{
    $orderData = $order->order_json;

    // 1. Obtener configuración general
    $generalConfig = $this->generalConfigRepo->getActiveConfiguration();

    // 2. Obtener forma de pago
    $gateway = $orderData['payment_gateway_names'][0] ?? null;
    $paymentMapping = $this->paymentGatewayRepo->findByShopifyGateway($gateway);

    // 3. Obtener bodega y ubicación
    $warehouseMapping = $this->validateAndGetWarehouseMapping($orderData);

    // 4. Generar contenido de archivos
    $pe0Content = $this->generatePE0Content($orderData, $generalConfig, $paymentMapping, $warehouseMapping);
    $txtContent = $this->generateTXTContent($orderData, $generalConfig, $paymentMapping, $warehouseMapping);

    // 5. Guardar archivos
    $dateFolder = now()->format('Ymd');
    $fileName = str_pad($orderData['order_number'], 8, '0', STR_PAD_LEFT);

    Storage::disk('local')->put("siesa/pedidos/{$dateFolder}/{$fileName}.PE0", $pe0Content);
    Storage::disk('local')->put("siesa/pedidos/{$dateFolder}/{$fileName}.txt", $txtContent);

    return [
        'pe0_file' => "{$dateFolder}/{$fileName}.PE0",
        'txt_file' => "{$dateFolder}/{$fileName}.txt"
    ];
}
```

---

## Jobs y Colas

### Configuración de Colas

**Archivo:** `config/queue.php`

```php
'default' => env('QUEUE_CONNECTION', 'database'),

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],
```

### ProcessShopifyOrder Job

**Propiedades:**

- `$tries = 3` - Máximo 3 intentos
- `$backoff = 60` - 60 segundos entre reintentos
- `$timeout = 300` - Timeout de 5 minutos
- `$queue = 'default'` - Cola por defecto

**Estados posibles:**

1. **Encolado** → En tabla `jobs`
2. **Procesando** → Worker ejecutando handle()
3. **Completado** → Eliminado de `jobs`
4. **Fallido** → Movido a `failed_jobs` después de 3 intentos

### Ejecutar el Worker

```bash
# Procesar todos los jobs pendientes
docker exec eurobelleza-back php artisan queue:work --stop-when-empty

# Worker permanente (producción)
docker exec eurobelleza-back php artisan queue:work

# Worker con configuración específica
docker exec eurobelleza-back php artisan queue:work \
  --tries=3 \
  --timeout=300 \
  --sleep=3 \
  --max-jobs=100

# Ver jobs fallidos
docker exec eurobelleza-back php artisan queue:failed

# Reintentar job fallido específico
docker exec eurobelleza-back php artisan queue:retry {id}

# Reintentar todos los fallidos
docker exec eurobelleza-back php artisan queue:retry all

# Limpiar jobs fallidos
docker exec eurobelleza-back php artisan queue:flush
```

---

## Comandos Artisan

### Matriz Rápida de Uso (Actualizada)

| Escenario | Comando recomendado | Ejemplo | Cuándo usar |
|---|---|---|---|
| Sincronización diaria normal | `shopify:sync-orders` | `docker exec eurobelleza-back php artisan shopify:sync-orders` | Operación estándar diaria (también corre por schedule a las 02:00 America/Bogota). |
| Reproceso masivo de no completados | `orders:reprocess --status=all --validate` | `docker exec eurobelleza-back php artisan orders:reprocess --status=all --validate` | Reintentar pendientes/fallidos/procesando con validación previa. |
| Reproceso masivo solo fallidos | `orders:reprocess --status=failed --validate` | `docker exec eurobelleza-back php artisan orders:reprocess --status=failed --validate` | Incidentes donde quieres atacar solo fallidos. |
| Reproceso controlado por volumen | `orders:reprocess --status=all --limit=N --validate` | `docker exec eurobelleza-back php artisan orders:reprocess --status=all --limit=100 --validate` | Evitar saturar cola al reprocesar lotes grandes. |
| Refrescar pedidos puntuales desde Shopify | `orders:refresh --ids=...` | `docker exec eurobelleza-back php artisan orders:refresh --ids=120 --ids=121 --reprocess` | Soporte puntual/tickets específicos. |
| Refrescar todos no completados | `orders:refresh --non-completed --reprocess` | `docker exec eurobelleza-back php artisan orders:refresh --non-completed --reprocess` | Sospecha de JSON desactualizado/incompleto en muchos pedidos. |
| Refrescar pendientes sin fulfillments | `orders:refresh --pending-without-fulfillments --reprocess` | `docker exec eurobelleza-back php artisan orders:refresh --pending-without-fulfillments --reprocess` | Caso específico de pedidos sin `fulfillments/location_id`. |

> Nota: `orders:refresh` es principalmente una herramienta de soporte/diagnóstico; para operación diaria se recomienda `shopify:sync-orders` + `orders:reprocess`.

### 1. SyncShopifyOrders (Sincronización Diaria)

**Archivo:** `app/Console/Commands/SyncShopifyOrders.php`

**Propósito:** Sincroniza órdenes pendientes con Shopify y detecta órdenes nuevas/faltantes.

**Firma:**

```bash
php artisan shopify:sync-orders {--days=7}
```

**Opciones:**

- `--days=7` - Días hacia atrás para buscar órdenes (default: 7)

**Funcionalidades:**

1. **Actualizar órdenes pendientes:**
    - Consulta Shopify API por cada orden en estado `pending`
    - Actualiza `order_json` con datos frescos
    - Si `financial_status = 'paid'` y validación pasa → Encola job
    - **Auto-recuperación:** Órdenes que obtienen fulfillments después se procesan automáticamente

2. **Sincronizar órdenes nuevas:**
    - Busca órdenes en Shopify de últimos N días
    - Compara con DB local
    - Crea órdenes faltantes
    - Encola jobs si están pagadas y validadas

**Ejecución manual:**

```bash
# Sincronizar últimos 7 días
docker exec eurobelleza-back php artisan shopify:sync-orders

# Sincronizar últimos 30 días
docker exec eurobelleza-back php artisan shopify:sync-orders --days=30
```

**Cron Schedule (Automático):**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shopify:sync-orders --days=7')
             ->dailyAt('02:00')
             ->timezone('America/Bogota');
}
```

**Ejecutar cron manualmente:**

```bash
docker exec eurobelleza-back php artisan schedule:run
```

---

### 2. ReprocessOrders (Reprocesamiento Manual)

**Archivo:** `app/Console/Commands/ReprocessOrders.php`

**Propósito:** Reprocesar órdenes manualmente con validación previa.

**Firma:**

```bash
php artisan orders:reprocess {--limit=10} {--status=pending} {--validate}
```

**Opciones:**

- `--limit=10` - Cantidad de órdenes a procesar (default: 10)
- `--status=pending` - Filtrar por estado (pending, completed, failed)
- `--validate` - Solo encolar órdenes que pasen validación (recomendado)

**Características:**

- ✅ Pre-validación de configuraciones
- 📊 Barra de progreso
- 📈 Tabla de estadísticas final
- ⚠️ Omite órdenes sin configuración válida

**Ejemplos de uso:**

```bash
# Reprocesar 10 órdenes pending con validación
docker exec eurobelleza-back php artisan orders:reprocess --limit=10 --validate

# Reprocesar 50 órdenes sin validar
docker exec eurobelleza-back php artisan orders:reprocess --limit=50

# Reprocesar órdenes fallidas
docker exec eurobelleza-back php artisan orders:reprocess --status=failed --limit=20 --validate

# Reprocesar todas las pending
docker exec eurobelleza-back php artisan orders:reprocess --limit=1000 --validate
```

**Salida esperada:**

```
Reprocesando órdenes...
 10/10 [============================] 100%

Resumen:
+-------------------------+-------+
| Métrica                 | Valor |
+-------------------------+-------+
| Pedidos consultados     | 10    |
| Jobs encolados          | 8     |
| Omitidos (sin config)   | 2     |
+-------------------------+-------+
```

---

### 3. RefreshOrderData (Actualizar JSON desde Shopify)

**Archivo:** `app/Console/Commands/RefreshOrderData.php`

**Propósito:** Actualizar el JSON de órdenes consultando Shopify API directamente (soluciona datos desactualizados).

**Firma:**

```bash
php artisan orders:refresh {--ids=*} {--pending-without-fulfillments} {--reprocess}
```

**Opciones:**

- `--ids=*` - IDs específicos de órdenes a actualizar (repetible)
- `--pending-without-fulfillments` - Actualizar todas las órdenes pending sin fulfillments
- `--reprocess` - Validar y encolar jobs automáticamente si ahora son válidas

**Casos de uso:**

**1. Actualizar órdenes específicas:**

```bash
# Actualizar órdenes 6 y 7
docker exec eurobelleza-back php artisan orders:refresh --ids=6 --ids=7

# Actualizar y reprocesar automáticamente
docker exec eurobelleza-back php artisan orders:refresh --ids=6 --ids=7 --reprocess
```

**2. Actualizar todas las pending sin fulfillments:**

```bash
# Solo actualizar (no encolar)
docker exec eurobelleza-back php artisan orders:refresh --pending-without-fulfillments

# Actualizar y reprocesar las que ahora sean válidas
docker exec eurobelleza-back php artisan orders:refresh --pending-without-fulfillments --reprocess
```

**Flujo interno:**

1. Obtiene lista de órdenes según filtros
2. Por cada orden:
    - Consulta `/admin/api/2024-01/orders/{shopify_order_id}.json`
    - Actualiza `order_json` en DB
    - Si `--reprocess`: Valida y encola si ahora es válida
3. Muestra estadísticas finales

**Salida esperada:**

```
Actualizando datos desde Shopify...
 2/2 [============================] 100%

✅ 2 pedidos actualizados
✅ 2 jobs reprocesados
❌ 0 pedidos con errores
```

---

## Sistema de Validación

### OrderConfigurationValidator

**Archivo:** `app/Services/OrderConfigurationValidator.php`

**Puntos de validación:**

#### 1. Configuración General

```php
✅ Verifica: Existe registro en siesa_general_configurations
❌ Error: "No existe configuración general activa de SIESA"
💡 Solución: Crear configuración en /admin/siesa/general-configuration
```

#### 2. Pasarela de Pago

```php
✅ Verifica: Existe mapeo para payment_gateway_names[0]
❌ Error: "No existe mapeo para la pasarela de pago: {gateway}"
💡 Solución: Crear mapeo en /admin/siesa/payment-gateways
```

#### 3. Bodega/Ubicación

```php
✅ Verifica:
   - Existen fulfillments en el pedido
   - fulfillments[0] tiene location_id
   - Existe mapeo para ese location_id

❌ Errores posibles:
   - "El pedido no tiene información de fulfillments"
   - "El fulfillment no tiene location_id"
   - "No existe mapeo para la ubicación: {location_id}"

💡 Solución:
   - Esperar a que se genere fulfillment en Shopify
   - Crear mapeo en /admin/siesa/warehouses
```

### Uso en el código

```php
// Inyectar servicio
public function __construct(
    private OrderConfigurationValidator $configValidator
) {}

// Validar antes de encolar
$validation = $this->configValidator->validate($orderData);

if ($validation['valid']) {
    ProcessShopifyOrder::dispatch($order);
} else {
    // Guardar errores
    $this->orderLogService->logError(
        $order,
        'configuration_validation_failed',
        $validation['errors']
    );
}
```

---

## Sincronización Automática

### Lógica de Auto-Recuperación

**Archivo:** `app/Services/Shopify/ShopifyOrderSyncService.php`

**Método:** `updatePendingOrders()`

**Comportamiento:**

```php
public function updatePendingOrders(): void
{
    $pendingOrders = Order::where('status', 'pending')->get();

    foreach ($pendingOrders as $order) {
        // 1. Consultar datos frescos desde Shopify
        $newOrderData = $this->fetchOrderFromShopify($order->shopify_order_id);

        // 2. SIEMPRE actualizar JSON (datos pueden cambiar)
        $order->order_json = $newOrderData;
        $order->save();

        // 3. Si está pagado, validar configuración
        $financialStatus = $newOrderData['financial_status'] ?? 'pending';

        if ($financialStatus === 'paid') {
            $validation = $this->configValidator->validate($newOrderData);

            // 4. Si AHORA es válido → Encolar
            if ($validation['valid']) {
                ProcessShopifyOrder::dispatch($order);
            }
        }
    }
}
```

**Casos que cubre:**

1. **Orden creada sin fulfillment →** Fulfillment agregado después
    - Primera ejecución: Sin fulfillments → Queda pending
    - Segunda ejecución: Con fulfillments → Validación pasa → Se procesa ✅

2. **Orden con pasarela no mapeada →** Mapeo creado después
    - Primera ejecución: Sin mapeo → Queda pending
    - Segunda ejecución: Con mapeo → Validación pasa → Se procesa ✅

3. **Orden con location_id no mapeado →** Ubicación agregada después
    - Primera ejecución: Sin ubicación → Queda pending
    - Segunda ejecución: Con ubicación → Validación pasa → Se procesa ✅

4. **Orden pagada después →** financial_status cambia a 'paid'
    - Primera ejecución: Unpaid → Queda pending
    - Segunda ejecución: Paid + válido → Se procesa ✅

**Resultado:** Sistema auto-recuperable sin intervención manual.

---

## Generación de Archivos SIESA

### Formato de Archivos

**Formato:** SIESA 8.5 - Archivos de texto plano con ancho fijo (543 caracteres por línea)

**Archivos generados por pedido:**

1. **{numero_pedido}.PE0** - Encabezado del pedido
2. **{numero_pedido}.txt** - Listado de ítems

**Ejemplo:** Pedido #62394 genera:

- `00062394.PE0` (543 bytes)
- `00062394.txt` (543 bytes × cantidad de ítems)

### Estructura del Archivo .PE0

**Línea encabezado (543 caracteres):**

```
Posiciones | Longitud | Campo              | Ejemplo
-----------|----------|--------------------|---------
1-3        | 3        | Tipo registro      | 310
4-6        | 3        | Bodega             | 001
7-8        | 2        | Sucursal           | 01
9-28       | 20       | Comprobante        | 1PE
29-48      | 20       | Documento          | 00062394
49-54      | 6        | Fecha (AAMMDD)     | 170225
55-57      | 3        | Bodega destino     | 001
58-59      | 2        | Ubicación destino  | 15
60-79      | 20       | Tercero            | 890100222
80-99      | 20       | Vendedor           | 900
100-102    | 3        | Moneda             | COP
103-122    | 20       | Clase venta        | 1
... (hasta 543)
```

### Estructura del Archivo .txt

**Múltiples líneas (una por ítem, 543 caracteres cada una):**

```
Posiciones | Longitud | Campo              | Ejemplo
-----------|----------|--------------------|---------
1-3        | 3        | Tipo registro      | 341
4-6        | 3        | Bodega             | 001
7-8        | 2        | Sucursal           | 01
9-28       | 20       | Comprobante        | 1PE
29-48      | 20       | Documento          | 00062394
49-54      | 6        | Fecha (AAMMDD)     | 170225
55-73      | 19       | Código producto    | PROD-123
74-78      | 5        | Cantidad (entero)  | 00002
79-96      | 18       | Precio unitario    | 0000000000050000.00
... (hasta 543)
```

### Ubicación de Archivos

```
storage/app/siesa/pedidos/
├── 20260308/
│   ├── 00062394.PE0
│   ├── 00062394.txt
│   ├── 00062395.PE0
│   └── 00062395.txt
├── 20260307/
│   └── ...
└── 20260227/
    └── ...
```

**Patrón:** `storage/app/siesa/pedidos/{AAAAMMDD}/{numero_pedido}.{extension}`

---

## Logs y Trazabilidad

### OrderLogService

**Archivo:** `app/Services/OrderLogService.php`

**Tabla:** `order_logs`

**Tipos de eventos:**

```php
// Éxito
'order_received'         // Orden recibida por webhook
'order_processed'        // Archivos generados exitosamente

// Errores de configuración
'configuration_validation_failed'  // Falta configuración general/pago/bodega

// Errores de procesamiento
'processing_failed'      // Error al generar archivos
'job_failed'            // Job falló después de 3 reintentos

// Otros
'order_synced'          // Orden sincronizada desde Shopify
```

**Estructura del log:**

```php
order_logs:
- id
- order_id (FK → orders)
- event_type (string)
- message (text)
- details (json)  // Información adicional
- created_at
```

**Métodos disponibles:**

```php
// Registrar éxito
$this->orderLogService->logSuccess($order, 'order_processed', 'Archivos generados');

// Registrar error
$this->orderLogService->logError($order, 'processing_failed', 'Error en generador', [
    'exception' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);

// Registrar info
$this->orderLogService->logInfo($order, 'order_synced', 'Sincronización diaria');

// Registrar advertencia
$this->orderLogService->logWarning($order, 'webhook_retry', 'Segundo intento');
```

### Consultar Logs

```bash
# Ver logs de una orden específica
docker exec eurobelleza-back php artisan tinker
> Order::find(6)->logs;

# Ver últimos 10 logs
> OrderLog::latest()->limit(10)->get();

# Ver logs con errores
> OrderLog::where('event_type', 'LIKE', '%failed%')->get();
```

---

## Casos de Uso Comunes

### 1. Nueva Orden Llega por Webhook

**Escenario:** Cliente completa compra en Shopify y paga con PSE.

**Flujo:**

```
1. Shopify envía webhook → orders/paid
2. Sistema valida HMAC ✅
3. Crea registro en tabla orders (status: pending)
4. Detecta financial_status = 'paid'
5. Valida configuración:
   ✅ Configuración general existe
   ✅ Pasarela 'bogota' mapeada → forma_pago: '12'
   ✅ Fulfillment con location_id 80414146731 → bodega: '001', ubicación: '15'
6. Validación exitosa → Encola ProcessShopifyOrder job
7. Worker procesa job:
   - Genera 00062394.PE0
   - Genera 00062394.txt
   - Actualiza status → completed
   - Registra log de éxito
```

**Resultado:** Archivos disponibles en `storage/app/siesa/pedidos/20260308/`

---

### 2. Orden Sin Fulfillments (Auto-Recuperación)

**Escenario:** Orden creada el 17 de febrero sin asignación de bodega, bodega asignada el 8 de marzo.

**Flujo:**

```
17 Feb - 10:00 AM:
1. Webhook llega → orden pagada pero sin fulfillments
2. Validación falla: "El pedido no tiene información de fulfillments"
3. Orden queda en estado: pending
4. Log: configuration_validation_failed

18 Feb - 02:00 AM (cron diario):
5. Sync consulta orden desde Shopify
6. Actualiza order_json (aún sin fulfillments)
7. Validación falla → Sigue pending

... (mismo proceso diario hasta 8 de marzo)

8 Mar - 01:00 PM (bodega asignada en Shopify):
- Usuario asigna fulfilment en Shopify admin
- location_id: 80414146731

8 Mar - 02:00 AM siguiente día (cron diario):
8. Sync consulta orden desde Shopify
9. Actualiza order_json (AHORA con fulfillments)
10. Validación exitosa ✅
11. Encola ProcessShopifyOrder job
12. Worker procesa → Archivos generados
13. Status → completed
```

**Resultado:** Recuperación automática sin intervención manual.

---

### 3. Pasarela de Pago No Configurada

**Escenario:** Orden llega con nueva pasarela 'wompi' sin mapear.

**Problema identificado:**

```
Orden #62400
Gateway: 'wompi'
Error: "No existe mapeo para la pasarela de pago: wompi"
Status: pending
```

**Solución:**

```bash
# 1. Acceder al admin
URL: https://tu-dominio.com/admin/siesa/payment-gateways

# 2. Crear nuevo mapeo
Shopify Gateway: wompi
Forma Pago SIESA: 14  (ejemplo)
Guardar

# 3. Esperar al sync diario (02:00 AM) O forzar reprocesamiento manual:
docker exec eurobelleza-back php artisan orders:reprocess --limit=1 --validate
```

**Resultado:** En próximo sync, orden se procesará automáticamente.

---

### 4. Nueva Ubicación de Shopify

**Escenario:** Se abre nueva bodega en Shopify con location_id: 99988877766.

**Problema identificado:**

```
Orden #62401
Location ID: 99988877766
Error: "No existe mapeo para la ubicación: 99988877766"
Status: pending
```

**Solución:**

```bash
# 1. Acceder al admin
URL: https://tu-dominio.com/admin/siesa/warehouses

# 2. Crear nuevo mapeo
Shopify Location ID: 99988877766
Nombre: BODEGA MEDELLÍN
Código Bodega: 002
Código Ubicación: 20
Guardar

# 3. Esperar al sync diario O forzar:
docker exec eurobelleza-back php artisan orders:reprocess --limit=1 --validate
```

**Resultado:** Orden se procesará con bodega '002' y ubicación '20'.

---

### 5. Reprocesar Órdenes Fallidas

**Escenario:** 15 jobs fallaron por error temporal en storage.

```bash
# 1. Ver jobs fallidos
docker exec eurobelleza-back php artisan queue:failed

# 2. Analizar causa del error
# (revisar logs en order_logs table)

# 3. Corregir problema

# 4. Reintentar todos los fallidos
docker exec eurobelleza-back php artisan queue:retry all

# O reintentar uno específico
docker exec eurobelleza-back php artisan queue:retry {id}
```

---

### 6. Actualizar JSON Desactualizado Manualmente

**Escenario:** Detectas que 50 órdenes pending tienen JSON desactualizado.

```bash
# Opción 1: Actualizar órdenes específicas
docker exec eurobelleza-back php artisan orders:refresh --ids=6 --ids=7 --ids=8 --reprocess

# Opción 2: Actualizar todas las pending sin fulfillments
docker exec eurobelleza-back php artisan orders:refresh --pending-without-fulfillments --reprocess

# Opción 3: Dejar que el sync diario lo haga automáticamente
# (no hacer nada, esperar a las 02:00 AM)
```

---

## Checklist de Configuración Inicial

### ✅ 1. Configuración General

```bash
# Acceder a: /admin/siesa/general-configuration
- Código Tercero: 890100222
- Código Vendedor: 900
- Código Moneda: COP
- Código Clase Venta: 1
```

### ✅ 2. Mapear Pasarelas de Pago

```bash
# Acceder a: /admin/siesa/payment-gateways
# Crear mapeos para cada pasarela usada:
- bogota → 12 (PSE)
- manual → 01 (Manual)
- shopify_payments → 03 (Tarjeta)
```

### ✅ 3. Mapear Ubicaciones de Shopify

```bash
# Acceder a: /admin/siesa/warehouses
# Crear mapeo para cada ubicación:
- 80414146731 → BODEGA BARRANQUILLA → 001/15
- 80414245035 → BODEGA BOGOTÁ → 002/20
# etc...
```

### ✅ 4. Configurar Webhook en Shopify

```bash
# Shopify Admin → Settings → Notifications → Webhooks
URL: https://tu-dominio.com/api/shopify/webhooks/orders/paid
Formato: JSON
Evento: Order payment → Order paid
```

### ✅ 5. Configurar Cron para Sync

```bash
# Verificar en Kernel.php:
$schedule->command('shopify:sync-orders --days=7')
         ->dailyAt('02:00')
         ->timezone('America/Bogota');

# Activar cron en servidor:
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### ✅ 6. Iniciar Queue Worker (Producción)

```bash
# Usar Supervisor para mantener worker activo
[program:laravel-worker]
command=php /var/www/html/artisan queue:work --tries=3 --timeout=300
autostart=true
autorestart=true
```

---

## Troubleshooting

### Problema: Órdenes no se procesan automáticamente

**Diagnóstico:**

```bash
# 1. Verificar estado de órdenes
docker exec eurobelleza-back php artisan tinker
> Order::where('status', 'pending')->count();

# 2. Ver últimos logs
> OrderLog::latest()->limit(10)->get();

# 3. Verificar errores de validación
> OrderLog::where('event_type', 'configuration_validation_failed')->latest()->first();
```

**Soluciones:**

```bash
# Si falta configuración general
→ Crear en /admin/siesa/general-configuration

# Si falta mapeo de pasarela
→ Crear en /admin/siesa/payment-gateways

# Si falta mapeo de ubicación
→ Crear en /admin/siesa/warehouses
```

---

### Problema: Worker no procesa jobs

**Diagnóstico:**

```bash
# 1. Ver jobs pendientes
docker exec eurobelleza-back php artisan queue:monitor

# 2. Ver jobs fallidos
docker exec eurobelleza-back php artisan queue:failed

# 3. Verificar worker activo
ps aux | grep "queue:work"
```

**Soluciones:**

```bash
# Iniciar worker manualmente
docker exec eurobelleza-back php artisan queue:work --stop-when-empty

# Ver logs en tiempo real
docker exec eurobelleza-back php artisan queue:listen

# Reiniciar worker (si usa Supervisor)
supervisorctl restart laravel-worker
```

---

### Problema: Archivos no se generan

**Diagnóstico:**

```bash
# 1. Verificar permisos de storage
docker exec eurobelleza-back ls -la storage/app/siesa/pedidos/

# 2. Ver errores en log
docker exec eurobelleza-back tail -f storage/logs/laravel.log

# 3. Probar generación manual
docker exec eurobelleza-back php artisan tinker
> $order = Order::find(6);
> app(SiesaFlatFileGenerator::class)->generateFromOrder($order);
```

**Soluciones:**

```bash
# Corregir permisos
docker exec eurobelleza-back chmod -R 775 storage/app/siesa/

# Crear directorios si no existen
docker exec eurobelleza-back mkdir -p storage/app/siesa/pedidos/
```

---

## Comandos Útiles de Mantenimiento

```bash
# Ver estadísticas de órdenes
docker exec eurobelleza-back php artisan tinker
> Order::selectRaw('status, count(*) as total')->groupBy('status')->get();

# Limpiar jobs fallidos antiguos
docker exec eurobelleza-back php artisan queue:flush

# Ver últimos 20 logs con errores
> OrderLog::whereIn('event_type', ['processing_failed', 'configuration_validation_failed'])
    ->latest()
    ->limit(20)
    ->get(['order_id', 'event_type', 'message', 'created_at']);

# Buscar órdenes pendientes sin fulfillments
> Order::where('status', 'pending')
    ->whereRaw("JSON_LENGTH(order_json, '$.fulfillments') = 0")
    ->count();

# Contar archivos generados hoy
docker exec eurobelleza-back find storage/app/siesa/pedidos/$(date +%Y%m%d)/ -type f | wc -l

# Ver órdenes procesadas hoy
> Order::where('status', 'completed')
    ->whereDate('updated_at', today())
    ->count();
```

---

## Resumen de Comandos Principales

```bash
# SINCRONIZACIÓN
docker exec eurobelleza-back php artisan shopify:sync-orders --days=7

# REPROCESAMIENTO
docker exec eurobelleza-back php artisan orders:reprocess --limit=10 --validate

# ACTUALIZAR JSON
docker exec eurobelleza-back php artisan orders:refresh --pending-without-fulfillments --reprocess

# QUEUE WORKER
docker exec eurobelleza-back php artisan queue:work --stop-when-empty

# CRON MANUAL
docker exec eurobelleza-back php artisan schedule:run

# LOGS
docker exec eurobelleza-back tail -f storage/logs/laravel.log
```

---

## Contacto y Soporte

Para dudas o problemas técnicos, revisar:

- Logs en `storage/logs/laravel.log`
- Tabla `order_logs` para trazabilidad específica de órdenes
- Tabla `failed_jobs` para jobs que fallaron después de 3 reintentos

---

**Fecha de actualización:** 8 de marzo de 2026  
**Versión:** 1.0.0  
**Sistema:** Laravel 11.48.0 + SIESA 8.5
