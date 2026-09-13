# Documentación Técnica - Integración Shopify → SIESA

## Índice

1. [Resumen del Sistema](#resumen-del-sistema)
2. [Estados del Pedido](#estados-del-pedido)
3. [Maestros y Configuraciones](#maestros-y-configuraciones)
4. [Flujo de Procesamiento](#flujo-de-procesamiento)
5. [Jobs y Colas](#jobs-y-colas)
6. [Comandos Artisan](#comandos-artisan)
7. [Sistema de Validación](#sistema-de-validación)
8. [Sincronización Automática](#sincronización-automática)
9. [Generación de Archivos SIESA](#generación-de-archivos-siesa)
10. [Logs y Trazabilidad](#logs-y-trazabilidad)
11. [Casos de Uso Comunes](#casos-de-uso-comunes)

---

## Resumen del Sistema

Sistema de integración automática entre Shopify y SIESA 8.5. Laravel (`eurobelleza`) procesa pedidos pagados, genera un archivo plano `.PE0` con el formato requerido por SIESA y lo publica en Amazon S3. Un bot Windows (`eurobelleza_rpa`) lee ese archivo desde S3, lo carga en SIESA 8.5 mediante automatización de escritorio y reporta el resultado de vuelta a S3. Laravel consume ese resultado y, más tarde, concilia el reporte oficial `.P97` que el mismo bot genera en SIESA para confirmar de forma definitiva qué pedidos quedaron creados.

Este documento cubre en detalle los maestros, el formato de archivo y la lógica de validación del lado Laravel. Para el flujo end-to-end completo (incluyendo el bot RPA, S3 y la conciliación P97) ver `MANUAL_TECNICO_INTEGRACION_SIESA_RPA.md`; para la operación diaria orientada a usuario final ver `MANUAL_USUARIO_OPERATIVO_SIESA_RPA.md`.

### Características Principales

- 🔄 Recepción del webhook de Shopify `orders/create`, con chequeo interno de `financial_status`
- ✅ Validación de configuraciones antes de procesar (configuración general, pasarela de pago, bodega)
- 📦 Sistema de colas para procesamiento asíncrono
- 🏭 Bodega fija de Barranquilla para todos los pedidos (temporalmente, ver sección de validación)
- 💳 Mapeo de pasarelas de pago
- 📄 Generación de un archivo plano SIESA 8.5 por pedido (`.PE0`, un registro de 543 caracteres por línea)
- ☁️ Publicación del `.PE0` en S3 para que el bot RPA lo procese
- 🤖 Carga automática en SIESA 8.5 vía bot RPA (Windows + `pyautogui`)
- 📋 Conciliación final contra el reporte `.P97` de SIESA
- 🔄 Sincronización periódica automática de pedidos faltantes o desactualizados
- 📊 Sistema de logs completo (`order_logs`)

---

## Estados del Pedido

Definidos en `app/Enums/OrderStatusEnum.php` y usados como cast del campo `status` en `app/Models/Order.php`:

| Estado | Significado |
|---|---|
| `pending` | Recibido, esperando pago, configuración válida, o reabierto para reproceso |
| `processing` | Laravel está generando el archivo internamente |
| `sent_to_siesa` | `.PE0` generado y subido a S3, esperando que el bot RPA lo tome |
| `rpa_processing` | El bot RPA ya tomó el archivo (con o sin éxito, con o sin advertencias); esperando confirmación por P97 |
| `completed` | Confirmado por el reporte `.P97` de SIESA (`siesa:reconcile-p97`) — única forma de llegar a este estado |
| `failed` | Falló la generación/subida del archivo en Laravel, antes de llegar al RPA |
| `siesa_error` | SIESA rechazó el pedido con un error real (no una simple advertencia) |
| `payment_expired` | Shopify sigue reportando el pago sin confirmar tras el período de revisión configurado |

Detalle completo de cada estado y de las transiciones entre ellos: ver `MANUAL_TECNICO_INTEGRACION_SIESA_RPA.md`, sección 4.

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

**Propósito:** Mapea las ubicaciones de Shopify con códigos de bodega y ubicación de SIESA.

**Configuración vigente:** Por solicitud operativa, la integración usa temporalmente una bodega fija para todos los pedidos: `80414146731 → BODEGA BARRANQUILLA → 001/17`. El `location_id` de `fulfillments` de Shopify no determina la bodega mientras esta regla esté activa.

**Campos:**

```php
- id (bigint)
- shopify_location_id (bigint, unique) - ID de ubicación en Shopify
- shopify_location_name (string, 255) - Nombre descriptivo
- bodega_code (string, 3) - Código de bodega SIESA (posiciones 55-57)
- location_code (string, 2) - Código de ubicación SIESA (posiciones 58-59)
- timestamps
```

**Datos vigentes:**

```
80414146731 → BODEGA BARRANQUILLA → 001/17
```

**Datos históricos/otros mappings:**

```
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
$mapping = $this->warehouseRepository->findByShopifyLocationId(80414146731);

// Retorna: SiesaWarehouseMapping | null
```

---

## Flujo de Procesamiento

### Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────────┐
│              SHOPIFY (webhook orders/create)                     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│           Webhook Controller (orders/create)                     │
│  - Valida firma HMAC                                            │
│  - Crea/actualiza el pedido en tabla orders, status = PENDING   │
│  - Lee financial_status del payload                              │
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
                                       │ Generar .PE0     │
                                       │ + subir a S3      │
                                       │ (pedidos/)         │
                                       └────────┬────────┘
                                                │
                                                ▼
                                       [ORDEN SENT_TO_SIESA]
                                                │
                                                ▼
                              ┌─────────────────────────────────┐
                              │ Bot RPA (Windows) descarga,       │
                              │ importa en SIESA 8.5 y sube el     │
                              │ resultado de la corrida a S3       │
                              │ (resultados/, errores/)             │
                              └────────────────┬────────────────┘
                                               │
                                               ▼
                    php artisan siesa:process-rpa-results (cada 30 min)
                                               │
                              ┌────────────────┴────────────────┐
                              │ SIESA_ERROR                        │ RPA_PROCESSING
                              ▼                                    (sin error, con
                    (requiere corrección                            advertencia o
                     y reproceso manual)                            no resuelto)
                                                                         │
                                                                         ▼
                                                     Bot RPA genera y sube el
                                                     reporte .P97 (confirmaciones/)
                                                                         │
                                                                         ▼
                                          php artisan siesa:reconcile-p97 (cada 30 min)
                                                                         │
                                                                         ▼
                                                              [ORDEN COMPLETED]
                                                      (o vuelve a PENDING si el pedido
                                                       no aparece en el P97 del rango)
```

Nota importante: `siesa:process-rpa-results` nunca deja un pedido en `completed` por sí solo. La única confirmación final de que el pedido quedó creado en SIESA es el reporte `.P97`, conciliado por `siesa:reconcile-p97`.

### Flujo Detallado por Etapas

#### 1️⃣ Recepción del Webhook

**Archivo:** `app/Http/Controllers/API/ShopifyWebhookController.php`

**Ruta:** `POST /api/webhooks/shopify/orders/create` (`routes/api.php`), topic de Shopify `orders/create` — no existe un webhook separado para `orders/paid`; el pago se evalúa dentro del propio controlador.

Flujo real (simplificado, con nombres de método ilustrativos):

```php
public function ordersCreate(Request $request)
{
    // 1. Validar firma HMAC (middleware shopify.webhook)

    // 2. Crear o actualizar el pedido, siempre en PENDING al inicio
    $order = Order::updateOrCreate(
        ['shopify_order_id' => $orderData['id']],
        [
            'shopify_order_number' => $orderData['order_number'],
            'order_json' => $orderData,
            'status' => OrderStatusEnum::PENDING,
        ]
    );

    // 3. Solo si ya está pagado se valida y se encola
    if (($orderData['financial_status'] ?? null) === 'paid') {
        $validation = $this->configValidator->validate($orderData);

        if ($validation['valid']) {
            ProcessShopifyOrder::dispatch($order);
        } elseif (!$this->shouldKeepPending($validation['errors'])) {
            // errores reales de configuración -> se registran, el pedido queda pending
            $this->orderLogService->logError($order, 'configuration_validation_failed', $validation['errors']);
        }
        // si el único problema es falta de fulfillment/location_id, el pedido se
        // deja en pending a propósito para reintentarlo cuando llegue ese dato
    }

    // si no está pagado, el pedido simplemente queda en PENDING y será tomado
    // más tarde por shopify:sync-missing-orders / orders:dispatch-pending
}
```

#### 2️⃣ Validación de Configuración

**Archivo:** `app/Services/OrderConfigurationValidator.php`

**3 Validaciones Obligatorias:**

```php
public function validate(array $orderData): array
{
    $errors = [];

    // 1. Configuración General
    if (!SiesaGeneralConfiguration::getConfig()) {
        $errors[] = 'No existe configuración general activa';
    }

    // 2. Mapeo de Pasarela de Pago (o de tags, si el gateway es "manual")
    $gateway = $orderData['payment_gateway_names'][0] ?? null;
    if (!$this->resolvePaymentGatewayMapping($gateway, $orderData['tags'] ?? '')) {
        $errors[] = "No existe mapeo para la pasarela: {$gateway}";
    }

    // 3. Mapeo de Bodega — actualmente FIJO a Barranquilla, no depende del
    //    fulfillment del pedido (ver USE_FIXED_BARRANQUILLA_WAREHOUSE más abajo)
    if (!$this->warehouseMappingRepo->findByShopifyLocationId(80414146731)) {
        $errors[] = 'No existe configuración de bodega fija para BODEGA BARRANQUILLA (location_id: 80414146731)';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}
```

> La lógica para resolver la bodega dinámicamente por `fulfillments[0].location_id` sigue existiendo en el código (`OrderConfigurationValidator` y `SiesaFlatFileGenerator` conservan los métodos), pero está deshabilitada por una constante interna (`USE_FIXED_BARRANQUILLA_WAREHOUSE = true`, sin toggle por variable de entorno). Mientras esa constante sea `true`, el `location_id` real del pedido nunca se lee para elegir bodega.

#### 3️⃣ Job de Procesamiento

**Archivo:** `app/Jobs/ProcessShopifyOrder.php`

```php
class ProcessShopifyOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;      // 3 intentos
    public int $backoff = 60;   // 60 segundos entre reintentos
    public int $timeout = 120;  // 2 minutos de timeout

    public function handle(): void
    {
        try {
            ShopifyOrderProcessor::process($this->order);
        } catch (\Exception $e) {
            Log::error(...);
            if ($this->attempts() >= $this->tries) {
                Log::critical(...);
            }
            throw $e; // re-lanza para activar reintentos
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical(...); // se ejecuta tras agotar los 3 intentos
    }
}
```

El job delega todo el trabajo en `ShopifyOrderProcessor::process()` — no llama directamente a `SiesaFlatFileGenerator`.

#### 4️⃣ Generación y Publicación del Archivo

**Archivos:** `app/Services/ShopifyOrderProcessor.php` (orquesta) y `app/Services/Siesa/SiesaFlatFileGenerator.php` (genera el contenido).

```php
// ShopifyOrderProcessor::process($order) — flujo real, simplificado
public function process(Order $order): bool
{
    if ($order->status !== OrderStatusEnum::PENDING) {
        return false;
    }
    if (($order->order_json['financial_status'] ?? null) !== 'paid') {
        return false;
    }

    $order->update(['status' => OrderStatusEnum::PROCESSING]);

    try {
        // 1. Generar el CONTENIDO del archivo (una sola cadena, 543 chars por línea)
        $content = $this->generator->generate($order); // SiesaFlatFileGenerator::generate()

        // 2. Guardar copia local (histórico), con carpeta por fecha
        $orderNumber = str_pad($order->shopify_order_number, 8, '0', STR_PAD_LEFT);
        $dateFolder = now()->format('Ymd');
        Storage::disk('local')->put("siesa/pedidos/{$dateFolder}/{$orderNumber}.PE0", $content);
        Storage::disk('local')->put("siesa/pedidos/{$dateFolder}/{$orderNumber}.txt", $content); // copia local duplicada, mismo contenido

        // 3. Subir SOLO el .PE0 a S3, en estructura plana (sin carpeta de fecha)
        //    El histórico por fecha vive solo en local; S3 es únicamente canal
        //    de transferencia hacia el bot RPA.
        Storage::disk('siesa_pedidos')->put("{$orderNumber}.PE0", $content); // -> s3://.../pedidos/{orderNumber}.PE0

        $order->update(['status' => OrderStatusEnum::SENT_TO_SIESA]);
        return true;
    } catch (\Exception $e) {
        $order->increment('attempts');
        $order->update(['status' => OrderStatusEnum::FAILED]);
        throw $e;
    }
}
```

Puntos a tener en cuenta:

- `SiesaFlatFileGenerator::generate()` devuelve **un solo string** con todas las líneas del pedido (una por ítem, más una línea de envío si aplica) — no arma por separado un "encabezado" y un "detalle".
- El disco local sigue escribiendo tanto `.PE0` como `.txt` con el **mismo contenido**, pero a **S3 solo sube el `.PE0`**. El `.txt` es una copia local histórica sin más uso funcional.
- El pedido no queda `completed` aquí: queda en `sent_to_siesa`, esperando que el bot RPA lo tome, y más adelante la conciliación P97.

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

| Escenario                                 | Comando recomendado                                    | Ejemplo                                                                                         | Cuándo usar                                                             |
| ----------------------------------------- | ------------------------------------------------------ | ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Sincronización de pedidos faltantes       | `shopify:sync-missing-orders`                          | `docker exec eurobelleza-back php artisan shopify:sync-missing-orders`                          | Recupera pedidos que no llegaron por webhook antes de cada ventana RPA. |
| Refrescar datos desde Shopify             | `orders:refresh-shopify-data --non-completed --days=5` | `docker exec eurobelleza-back php artisan orders:refresh-shopify-data --non-completed --days=5` | Actualiza JSON reciente sin despachar reprocesos directamente.          |
| Despachar pendientes pagados              | `orders:dispatch-pending --validate`                   | `docker exec eurobelleza-back php artisan orders:dispatch-pending --validate`                   | Encola pedidos `pending` válidos antes de cada corrida RPA.             |
| Reproceso masivo de no completados        | `orders:reprocess --status=all --validate`             | `docker exec eurobelleza-back php artisan orders:reprocess --status=all --validate`             | Reintentar pendientes/fallidos/procesando con validación previa.        |
| Reproceso masivo solo fallidos            | `orders:reprocess --status=failed --validate`          | `docker exec eurobelleza-back php artisan orders:reprocess --status=failed --validate`          | Incidentes donde quieres atacar solo fallidos.                          |
| Reproceso controlado por volumen          | `orders:reprocess --status=all --limit=N --validate`   | `docker exec eurobelleza-back php artisan orders:reprocess --status=all --limit=100 --validate` | Evitar saturar cola al reprocesar lotes grandes.                        |
| Refrescar pedidos puntuales desde Shopify | `orders:refresh-shopify-data --ids=...`                | `docker exec eurobelleza-back php artisan orders:refresh-shopify-data --ids=120 --ids=121`      | Soporte puntual/tickets específicos.                                    |
| Procesar resultados RPA                   | `siesa:process-rpa-results`                            | `docker exec eurobelleza-back php artisan siesa:process-rpa-results`                            | Consume JSON de corrida y `.P99` reportados por el bot.                 |
| Confirmar pedidos en Siesa                | `siesa:reconcile-p97`                                  | `docker exec eurobelleza-back php artisan siesa:reconcile-p97`                                  | Usa P97 como confirmación final de pedidos creados en Siesa.            |

> Nota: para operación diaria se recomienda el ciclo programado `shopify:sync-missing-orders` + `orders:refresh-shopify-data` + `orders:dispatch-pending` antes de cada ventana RPA.

### 1. SyncShopifyOrders (Sincronización Diaria)

**Archivo:** `app/Console/Commands/SyncShopifyOrders.php`

**Propósito:** Detecta y crea en base de datos órdenes de Shopify que no llegaron por webhook.

**Firma:**

```bash
php artisan shopify:sync-missing-orders {--from=} {--to=} {--dry-run}
```

**Opciones:**

- `--from=` - Fecha inicial (`Y-m-d` o `yesterday`)
- `--to=` - Fecha final (`Y-m-d`)
- `--dry-run` - Simula sin guardar cambios

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
# Sincronizar rango por defecto
docker exec eurobelleza-back php artisan shopify:sync-missing-orders

# Sincronizar una fecha específica
docker exec eurobelleza-back php artisan shopify:sync-missing-orders --from=2026-03-06 --to=2026-03-06
```

**Cron Schedule (Automático):**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('shopify:sync-missing-orders')
             ->cron('15 6,12,18 * * *')
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
php artisan orders:refresh-shopify-data {--ids=*} {--pending-without-fulfillments} {--non-completed} {--days=30}
```

**Opciones:**

- `--ids=*` - IDs específicos de órdenes a actualizar (repetible)
- `--pending-without-fulfillments` - Actualizar todas las órdenes pending sin fulfillments
- `--non-completed` - Actualizar pedidos no completados dentro de la ventana de días
- `--days=30` - Ventana de días para `--non-completed`

**Casos de uso:**

**1. Actualizar órdenes específicas:**

```bash
# Actualizar órdenes 6 y 7
docker exec eurobelleza-back php artisan orders:refresh-shopify-data --ids=6 --ids=7
```

**2. Actualizar todas las pending sin fulfillments:**

```bash
# Solo actualizar (no encolar)
docker exec eurobelleza-back php artisan orders:refresh-shopify-data --pending-without-fulfillments
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
    - Existe el mapping fijo de Barranquilla
    - Shopify Location ID: 80414146731
    - Código bodega/localización: 001/17

❌ Errores posibles:
    - "Falta configuración de bodega fija BODEGA BARRANQUILLA para location_id: 80414146731"

💡 Solución:
    - Crear o corregir el mapeo 80414146731 → BODEGA BARRANQUILLA → 001/17 en /admin/siesa/warehouses
```

> Nota técnica: el código conserva la resolución anterior por `fulfillments[0].location_id` y fallback a única bodega configurada, pero por ahora está desactivada mediante la bandera interna que fuerza Barranquilla.

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

1. **Orden creada sin fulfillment →** JSON actualizado después
    - Con la regla vigente de bodega fija, la falta de fulfillment ya no bloquea la bodega
    - El refresh sigue actualizando datos frescos de Shopify para otros campos operativos

2. **Orden con pasarela no mapeada →** Mapeo creado después
    - Primera ejecución: Sin mapeo → Queda pending
    - Segunda ejecución: Con mapeo → Validación pasa → Se procesa ✅

3. **Bodega fija de Barranquilla no configurada →** Mapping creado después
    - Primera ejecución: Falta `80414146731` → Queda failed o pendiente según punto de entrada
    - Segunda ejecución: Con mapping `80414146731 → 001/17` → Validación pasa → Se procesa ✅

4. **Orden pagada después →** financial_status cambia a 'paid'
    - Primera ejecución: Unpaid → Queda pending
    - Segunda ejecución: Paid + válido → Se procesa ✅

**Resultado:** Sistema auto-recuperable sin intervención manual.

---

## Generación de Archivos SIESA

### Formato de Archivos

**Formato:** SIESA 8.5 - Archivo de texto plano con ancho fijo, **543 caracteres por línea**. Cada línea es un registro completo (no hay una línea de "encabezado" separada de las líneas de "detalle": el pedido, el cliente, la bodega, etc. se repiten en cada línea de ítem).

**Archivos generados por pedido** (`app/Services/ShopifyOrderProcessor.php`):

1. **`{numero_pedido}.PE0`** — el único que se sube a S3 (`pedidos/{numero_pedido}.PE0`) para que el bot RPA lo importe en SIESA.
2. **`{numero_pedido}.txt`** — copia local con **exactamente el mismo contenido** que el `.PE0`, guardada solo como histórico local; no se sube a S3 ni cumple una función distinta.

**Líneas por pedido:** una línea de 543 caracteres por cada `line_item` del pedido, más una línea adicional de envío (SKU fijo `900010`) si `total_shipping_price_set.shop_money.amount > 0`. Los ítems con precio final de línea igual a cero (después de descuentos) se tratan como **obsequio** y usan la lista de precio y el motivo de obsequio configurados.

**Ejemplo:** Pedido #62394 con 2 ítems y envío genera `00062394.PE0` (y su copia `00062394.txt`) con **3 líneas** de 543 caracteres cada una.

### Estructura de cada línea (543 caracteres)

Definida en `app/Helpers/SiesaFileStructure.php` y usada por `SiesaFlatFileGenerator::generateLine()`:

```
Posiciones | Long. | Campo                          | Relleno/formato
-----------|-------|--------------------------------|------------------------------
1-10       | 10    | Orden de compra (nro. pedido)  | ceros a la izquierda
11         | 1     | Tipo de identificación cliente | config.tipo_cliente
12-31      | 20    | Código EAN                     | vacío
32-44      | 13    | Código del cliente             | config.codigo_cliente
45-46      | 2     | Sucursal                       | del mapeo de pasarela de pago
47-54      | 8     | Fecha del pedido (AAAAMMDD)    | order_json.created_at
55-57      | 3     | Bodega                         | mapeo de bodega (fija Barranquilla: 001)
58-59      | 2     | Localización                   | mapeo de bodega (fija Barranquilla: 17)
60         | 1     | Tipo de búsqueda del ítem      | config.tipo_busqueda_item
61-75      | 15    | Código de barras               | vacío
76-90      | 15    | Código del ítem (SKU)          | line_item.sku
91-93      | 3     | Extensión del ítem             | vacío
94-101     | 8     | Fecha de entrega (AAAAMMDD)    | igual a fecha del pedido
102-104    | 3     | Unidad de captura              | config.unidad_captura
105-117    | 13    | Cantidad                       | 9 enteros + 3 decimales + signo
118-130    | 13    | Cantidad unidad 2              | ceros
131        | 1     | Unidad del precio              | config.unidad_precio
132-134    | 3     | Lista de precio                | normal / flete / obsequio según línea
135-136    | 2     | Lista de descuento             | vacío
137-148    | 12    | Precio unitario final          | 9 enteros + 2 decimales + signo
149-152    | 4     | Descuento línea 1               | 0 (el descuento ya se aplicó al precio)
153-156    | 4     | Descuento línea 2               | 0
157-176    | 20    | Detalle del movimiento          | config.detalle_movimiento
177-216    | 40    | Descripción del ítem            | vacío
217-220    | 4     | Punto de envío                  | vacío
221-280    | 60    | Observación 1                   | nombre, cédula/NIT, teléfono del shipping_address
281-340    | 60    | Observación 2                   | dirección, ciudad/departamento
341-380    | 40    | Descripción variable 1          | vacío
381-420    | 40    | Descripción variable 2          | vacío
421-460    | 40    | Descripción variable 3          | vacío
461-500    | 40    | Descripción variable 4          | vacío
501-513    | 13    | Código vendedor                 | config.codigo_vendedor
514-515    | 2     | Motivo                          | motivo normal u obsequio
516-523    | 8     | Centro de costo                 | del mapeo de pasarela de pago
524-533    | 10    | Proyecto                        | vacío
534-535    | 2     | Condición de pago               | del mapeo de pasarela de pago
536-543    | 8     | Documento alterno               | mismo número de pedido, ceros a la izquierda
```

Notas de formato:

- Cantidades y precios llevan signo (`+`/`-`) al final, no al inicio: p.ej. `formatPrice(48000.00)` → `"00000480000+"` (11 dígitos + signo = 12 caracteres).
- Las observaciones 1 y 2 se generan a partir de `shipping_address`, sin tildes (se convierten a ASCII con `removeAccents()`) y truncadas/rellenadas a 60 caracteres exactos.
- El descuento por línea (`discount_allocations`) ya se resta dentro del precio unitario final (posiciones 137-148); los campos de porcentaje de descuento (149-156) siempre van en cero.

### Ubicación de Archivos

**Copia local (histórico, con carpeta por fecha):**

```
storage/app/siesa/pedidos/
├── 20260308/
│   ├── 00062394.PE0
│   ├── 00062394.txt
│   ├── 00062395.PE0
│   └── 00062395.txt
└── ...
```

**Patrón local:** `storage/app/siesa/pedidos/{AAAAMMDD}/{numero_pedido}.{PE0|txt}`

**S3 (canal de transferencia hacia el bot RPA, estructura plana sin carpeta de fecha):**

```
s3://eurobelleza-siesa/pedidos/00062394.PE0
```

**Patrón S3:** `pedidos/{numero_pedido}.PE0` (disco `siesa_pedidos`, con `root => 'pedidos'` en `config/filesystems.php`). Laravel elimina este objeto de S3 una vez que `siesa:process-rpa-results` recibe evidencia de que el bot RPA intentó ese archivo (con éxito, advertencia, error o resultado no resuelto).

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
1. Shopify envía webhook → orders/create
2. Sistema valida HMAC ✅
3. Crea registro en tabla orders (status: pending)
4. Detecta financial_status = 'paid' en el mismo payload
5. Valida configuración:
   ✅ Configuración general existe
   ✅ Pasarela 'bogota' mapeada → forma_pago: '12'
   ✅ Bodega fija BARRANQUILLA configurada → location_id 80414146731 → bodega: '001', ubicación: '17'
6. Validación exitosa → Encola ProcessShopifyOrder job
7. Worker procesa job (ShopifyOrderProcessor):
   - Genera el contenido del .PE0 (SiesaFlatFileGenerator::generate())
   - Guarda copia local 00062394.PE0 y 00062394.txt
   - Sube 00062394.PE0 a s3://eurobelleza-siesa/pedidos/
   - Actualiza status → sent_to_siesa
   - Registra log de éxito
8. El bot RPA toma el archivo en la siguiente corrida, lo importa en SIESA,
   y sube el resultado → status → rpa_processing
9. La siguiente conciliación P97 confirma el pedido → status → completed
```

**Resultado:** Archivos disponibles en `storage/app/siesa/pedidos/20260308/` (local) y, mientras esté pendiente de procesar por el RPA, en `s3://eurobelleza-siesa/pedidos/00062394.PE0`.

---

### 2. Orden Sin Fulfillments

**Escenario:** Orden creada sin fulfillment o sin bodega asignada en Shopify.

**Flujo:**

```
17 Feb - 10:00 AM:
1. Webhook llega → orden pagada pero sin fulfillments
2. Validación usa la bodega fija de Barranquilla, no el fulfillment del pedido
3. Si existe el mapping 80414146731 → 001/17, la orden puede procesarse
4. El archivo queda en S3 y el pedido pasa a sent_to_siesa
```

**Resultado:** Mientras la bodega fija esté activa, la falta de fulfillment no bloquea la selección de bodega. La lógica anterior por fulfillment sigue conservada en código para reactivarla cuando se requiera.

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

**Estado vigente:** No afecta la generación del archivo plano mientras la regla temporal de Barranquilla fija esté activa. Todos los pedidos usan `80414146731 → BODEGA BARRANQUILLA → 001/17`.

**Cuando se reactive la resolución dinámica por fulfillment, aplicará este flujo:**

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
docker exec eurobelleza-back php artisan orders:refresh-shopify-data --ids=6 --ids=7 --ids=8

# Opción 2: Actualizar todas las pending sin fulfillments
docker exec eurobelleza-back php artisan orders:refresh-shopify-data --pending-without-fulfillments

# Opción 3: Despachar pendientes después de refrescar
docker exec eurobelleza-back php artisan orders:dispatch-pending --validate
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

### ✅ 3. Mapear Bodega Fija de Barranquilla

```bash
# Acceder a: /admin/siesa/warehouses
# Crear o verificar el mapeo activo:
- 80414146731 → BODEGA BARRANQUILLA → 001/17
```

> La lógica para mapear múltiples ubicaciones de Shopify se conserva en código, pero temporalmente no se usa para seleccionar la bodega del archivo Siesa.

### ✅ 4. Configurar Webhook en Shopify

```bash
# Shopify Admin → Settings → Notifications → Webhooks
URL: https://tu-dominio.com/api/webhooks/shopify/orders/create
Formato: JSON
Evento: Order creation → Order create
```

No se usa el evento "Order payment"; el pago se evalúa dentro del controlador a partir de `financial_status` en el mismo payload de `orders/create`.

### ✅ 5. Configurar Cron para Sync

```bash
# Verificar en Kernel.php:
$schedule->command('shopify:sync-missing-orders')
         ->cron('15 6,12,18 * * *')
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

# 3. Probar generación manual (contenido del archivo, sin guardar ni subir)
docker exec eurobelleza-back php artisan tinker
> $order = Order::find(6);
> app(\App\Services\Siesa\SiesaFlatFileGenerator::class)->generate($order);

# 4. Probar el flujo completo (genera, guarda local y sube a S3)
> app(\App\Services\ShopifyOrderProcessor::class)->process($order);
```

**Soluciones:**

```bash
# Corregir permisos
docker exec eurobelleza-back chmod -R 775 storage/app/siesa/

# Crear directorios si no existen
docker exec eurobelleza-back mkdir -p storage/app/siesa/pedidos/

# Si el archivo se genera y guarda local pero no aparece en pedidos/ de S3,
# revisar credenciales/permisos del disco `siesa_pedidos` (config/filesystems.php)
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
docker exec eurobelleza-back php artisan shopify:sync-missing-orders

# REFRESCAR JSON SHOPIFY
docker exec eurobelleza-back php artisan orders:refresh-shopify-data --non-completed --days=5

# DESPACHAR PENDIENTES
docker exec eurobelleza-back php artisan orders:dispatch-pending --validate

# REPROCESAMIENTO
docker exec eurobelleza-back php artisan orders:reprocess --limit=10 --validate

# RESULTADOS RPA
docker exec eurobelleza-back php artisan siesa:process-rpa-results

# CONFIRMACIÓN P97
docker exec eurobelleza-back php artisan siesa:reconcile-p97

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

**Fecha de actualización:** 13 de septiembre de 2026  
**Versión:** 2.0.0 — actualizado para reflejar la integración con S3, el bot RPA (`eurobelleza_rpa`) y la conciliación P97; ver también `MANUAL_TECNICO_INTEGRACION_SIESA_RPA.md` y `MANUAL_USUARIO_OPERATIVO_SIESA_RPA.md`  
**Sistema:** Laravel 11.48.0 + SIESA 8.5
