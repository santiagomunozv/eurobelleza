<?php

namespace App\Services\Siesa;

use App\Models\Order;
use App\Models\SiesaGeneralConfiguration;
use App\Models\SiesaPaymentGatewayMapping;
use App\Repositories\SiesaWarehouseMappingRepository;
use App\Helpers\SiesaFileStructure;
use App\Services\OrderLogService;

class SiesaFlatFileGenerator
{
    private const USE_FIXED_BARRANQUILLA_WAREHOUSE = true;
    private const FIXED_BARRANQUILLA_LOCATION_ID = 80414146731;

    // Palabras frecuentes en tags de pedidos manuales que no identifican un método de pago
    // específico (p. ej. "link de pago Addi" no debe matchear por "pago" contra
    // "Checkout Mercado Pago"). Se excluyen del matching aunque tengan >= 3 caracteres.
    private const TAG_MATCH_STOPWORDS = [
        'pago', 'pagos', 'link', 'pedido', 'compra', 'transferencia',
        'cliente', 'envio', 'entrega', 'factura', 'orden', 'nota',
    ];

    private OrderLogService $orderLogService;
    private SiesaWarehouseMappingRepository $warehouseRepository;

    public function __construct(
        OrderLogService $orderLogService,
        SiesaWarehouseMappingRepository $warehouseRepository
    ) {
        $this->orderLogService = $orderLogService;
        $this->warehouseRepository = $warehouseRepository;
    }

    /**
     * Genera el contenido del archivo plano SIESA a partir de un pedido
     *
     * @param Order $order Pedido de Shopify
     * @return string Contenido del archivo (todas las líneas)
     */
    public function generate(Order $order): string
    {
        try {
            $config = SiesaGeneralConfiguration::getConfig();
        } catch (\Exception $e) {
            $this->orderLogService->logError($order, 'siesa_file_generation', [
                'error' => 'No se encontró la configuración general de SIESA',
                'message' => $e->getMessage(),
            ]);
            throw new \Exception("No se encontró la configuración general de SIESA. Por favor configure el sistema antes de generar archivos.");
        }

        $orderData = $order->order_json;

        $commonData = $this->prepareCommonData($order, $orderData, $config);

        $lineItems = $orderData['line_items'] ?? [];

        if (empty($lineItems)) {
            throw new \Exception("El pedido #{$order->shopify_order_number} no tiene productos (line_items)");
        }

        $lines = [];
        foreach ($lineItems as $lineItem) {
            // Detectar si es producto de obsequio (precio final = 0)
            $isGift = $this->isGiftLine($lineItem);

            $lines[] = $this->generateLine($commonData, $lineItem, $config, false, $isGift);
        }

        // Agregar línea de envío si el valor es mayor a 0
        $shippingAmount = floatval($orderData['total_shipping_price_set']['shop_money']['amount'] ?? 0);

        if ($shippingAmount > 0) {
            $shippingItem = [
                'sku' => '900010',
                'quantity' => 1,
                'price' => $shippingAmount,
                'discount_allocations' => [] // Sin descuentos en envío
            ];
            $lines[] = $this->generateLine($commonData, $shippingItem, $config, true, false);
        }

        return implode("\n", $lines);
    }

    /**
     * Prepara los datos comunes del encabezado que se repiten en cada línea
     *
     * @param Order $order Pedido de Shopify
     * @param array $orderData JSON del pedido de Shopify
     * @param SiesaGeneralConfiguration $config Configuración general de SIESA
     * @return array
     */
    private function prepareCommonData(Order $order, array $orderData, SiesaGeneralConfiguration $config): array
    {
        $shippingAddress = $orderData['shipping_address'] ?? [];
        $observations = SiesaFileStructure::formatObservations($shippingAddress);

        // Obtener payment gateway y buscar configuración
        $paymentGateways = $orderData['payment_gateway_names'] ?? [];

        if (empty($paymentGateways)) {
            $this->orderLogService->logError($order, 'payment_gateway_not_found', [
                'error' => 'El pedido no tiene payment_gateway_names definido'
            ]);
            throw new \Exception("El pedido #{$order->shopify_order_number} no tiene método de pago definido.");
        }

        $gatewayName = $paymentGateways[0]; // Tomar el primero

        // Si el gateway es "manual", buscar en tags
        if (strtolower($gatewayName) === 'manual') {
            $tags = $orderData['tags'] ?? '';
            $gatewayMapping = $this->findGatewayFromTags($order, $tags);
        } else {
            $gatewayMapping = SiesaPaymentGatewayMapping::findByGateway($gatewayName);
        }

        if (!$gatewayMapping) {
            $this->orderLogService->logError($order, 'payment_gateway_mapping_not_found', [
                'payment_gateway' => $gatewayName,
                'tags' => $orderData['tags'] ?? '',
                'error' => 'No existe configuración para este método de pago en SIESA'
            ]);
            throw new \Exception("No existe configuración para el método de pago: {$gatewayName}");
        }

        $orderNumber = (string)($orderData['order_number'] ?? '');
        $documentoAlterno = str_pad($orderNumber, 8, '0', STR_PAD_LEFT);

        // Por ahora se fuerza Barranquilla, manteniendo disponible la resolución por Shopify.
        $warehouseMapping = $this->validateAndGetWarehouseMapping($order, $orderData);

        return [
            'orden_compra' => $orderNumber,
            'tipo_cliente' => $config->tipo_cliente->value,
            'codigo_ean' => '',
            'codigo_cliente' => $config->codigo_cliente,
            'sucursal' => $gatewayMapping->sucursal,
            'fecha_pedido' => SiesaFileStructure::formatDate($orderData['created_at'] ?? now()),
            'bodega' => $warehouseMapping->bodega_code,
            'localizacion' => $warehouseMapping->location_code,
            'observacion1' => $observations['observacion1'],
            'observacion2' => $observations['observacion2'],
            'codigo_vendedor' => $config->codigo_vendedor,
            'motivo' => $config->motivo,
            'centro_costo' => $gatewayMapping->centro_costo,
            'proyecto' => '',
            'condicion_pago' => $gatewayMapping->condicion_pago,
            'documento_alterno' => $documentoAlterno,
        ];
    }

    /**
     * Valida y obtiene el mapping de bodega requerido.
     * Lanza excepción si no se encuentra configuración de bodega.
     *
     * @param Order $order Pedido de Shopify
     * @param array $orderData JSON del pedido de Shopify
     * @return \App\Models\SiesaWarehouseMapping
     * @throws \Exception
     */
    private function validateAndGetWarehouseMapping(Order $order, array $orderData): \App\Models\SiesaWarehouseMapping
    {
        if (!self::USE_FIXED_BARRANQUILLA_WAREHOUSE) {
            return $this->resolveWarehouseMappingFromOrderData($order, $orderData);
        }

        return $this->resolveFixedBarranquillaWarehouseMapping($order);
    }

    private function resolveFixedBarranquillaWarehouseMapping(Order $order): \App\Models\SiesaWarehouseMapping
    {
        $warehouseMapping = $this->warehouseRepository->findByShopifyLocationId(self::FIXED_BARRANQUILLA_LOCATION_ID);

        if (!$warehouseMapping) {
            $this->orderLogService->logError($order, 'warehouse_mapping_not_found', [
                'location_id' => self::FIXED_BARRANQUILLA_LOCATION_ID,
                'warehouse_name' => 'BODEGA BARRANQUILLA',
                'error' => 'No existe configuración de bodega fija para BODEGA BARRANQUILLA',
                'order_number' => $order->shopify_order_number
            ]);
            throw new \Exception('No existe configuración de bodega fija para BODEGA BARRANQUILLA (location_id: ' . self::FIXED_BARRANQUILLA_LOCATION_ID . '). Por favor configure esta ubicación en el sistema.');
        }

        return $warehouseMapping;
    }

    private function resolveWarehouseMappingFromOrderData(Order $order, array $orderData): \App\Models\SiesaWarehouseMapping
    {
        $fulfillments = $orderData['fulfillments'] ?? [];
        $locationId = $fulfillments[0]['location_id'] ?? null;

        if (!$locationId) {
            return $this->resolveFallbackWarehouseMapping($order, $fulfillments);
        }

        $warehouseMapping = $this->warehouseRepository->findByShopifyLocationId($locationId);

        if (!$warehouseMapping) {
            $this->orderLogService->logError($order, 'warehouse_mapping_not_found', [
                'location_id' => $locationId,
                'error' => 'No existe configuración de bodega para este location_id',
                'order_number' => $order->shopify_order_number
            ]);
            throw new \Exception("No existe configuración de bodega para la ubicación de Shopify: {$locationId}. Por favor configure esta ubicación en el sistema.");
        }

        return $warehouseMapping;
    }

    private function resolveFallbackWarehouseMapping(Order $order, array $fulfillments): \App\Models\SiesaWarehouseMapping
    {
        $mappings = $this->warehouseRepository->all();

        if ($mappings->count() === 1) {
            $defaultMapping = $mappings->first();
            $this->orderLogService->logWarning($order, 'warehouse_mapping_fallback_single_mapping', [
                'warning' => 'Pedido sin fulfillments/location_id, se usa la única bodega configurada',
                'shopify_location_id' => $defaultMapping->shopify_location_id,
                'bodega_code' => $defaultMapping->bodega_code,
                'location_code' => $defaultMapping->location_code,
            ]);

            return $defaultMapping;
        }

        $this->orderLogService->logError($order, 'warehouse_mapping_missing', [
            'error' => 'Pedido sin fulfillments/location_id y no hay una bodega única para fallback',
            'order_number' => $order->shopify_order_number,
            'configured_mappings' => $mappings->count(),
            'fulfillments' => $fulfillments,
        ]);

        throw new \Exception(
            "El pedido #{$order->shopify_order_number} no tiene location_id en fulfillments"
        );
    }

    /**
     * Busca configuración de payment gateway basándose en los tags del pedido.
     * Hace matching por palabra EXACTA (no substring) entre las palabras del tag
     * y las palabras del nombre del gateway configurado, ignorando palabras
     * "ruido" (ver TAG_MATCH_STOPWORDS) que son demasiado genéricas para
     * identificar un método de pago por sí solas.
     *
     * Si más de un gateway configurado queda como candidato para el mismo
     * conjunto de tags, se compara el código SIESA (sucursal/condición de
     * pago/centro de costo) de cada candidato: si todos representan el mismo
     * código, da igual cuál se use y se toma uno de forma determinística
     * (p. ej. "Checkout Mercado Pago" vs "Mercado Pago Tarjetas", que hoy
     * comparten código). Solo si los candidatos representan códigos distintos
     * se considera ambiguo de verdad: no se adivina, se registra como error y
     * el pedido queda pendiente de revisión manual, en vez de resolverse con
     * un método de pago que podría ser incorrecto (con impacto contable en SIESA).
     *
     * @param Order $order Pedido de Shopify
     * @param string $tags Tags del pedido (texto libre)
     * @return \App\Models\SiesaPaymentGatewayMapping|null
     */
    private function findGatewayFromTags(Order $order, string $tags): ?\App\Models\SiesaPaymentGatewayMapping
    {
        if (empty($tags)) {
            $this->orderLogService->logError($order, 'payment_gateway_tags_empty', [
                'error' => 'Pedido con payment gateway "manual" pero sin tags para identificar el método de pago'
            ]);
            return null;
        }

        $tagWords = collect(preg_split('/[\s,]+/', strtolower($tags)))
            ->filter(fn (string $word) => strlen($word) >= 3 && !in_array($word, self::TAG_MATCH_STOPWORDS, true))
            ->unique();

        if ($tagWords->isEmpty()) {
            $this->orderLogService->logError($order, 'payment_gateway_tags_no_usable_words', [
                'tags' => $tags,
                'error' => 'Los tags del pedido solo tienen palabras genéricas o muy cortas para identificar el método de pago'
            ]);
            return null;
        }

        $candidates = []; // indexado por mapping id, para deduplicar coincidencias por varias palabras
        foreach (SiesaPaymentGatewayMapping::all() as $mapping) {
            $gatewayWords = preg_split('/\s+/', strtolower($mapping->payment_gateway_name));

            if ($tagWords->intersect($gatewayWords)->isNotEmpty()) {
                $candidates[$mapping->id] = $mapping;
            }
        }

        if (count($candidates) === 1) {
            $mapping = reset($candidates);
            $this->orderLogService->logInfo($order, 'payment_gateway_found_from_tags', [
                'tags' => $tags,
                'matched_gateway' => $mapping->payment_gateway_name
            ]);
            return $mapping;
        }

        if (count($candidates) > 1) {
            $distinctCodes = collect($candidates)
                ->map(fn ($m) => "{$m->sucursal}|{$m->condicion_pago}|{$m->centro_costo}")
                ->unique();

            if ($distinctCodes->count() === 1) {
                // Varios nombres de gateway coinciden con los tags, pero todos
                // representan el mismo código en SIESA: no hay ambigüedad real.
                $mapping = collect($candidates)->sortBy('id')->first();
                $this->orderLogService->logInfo($order, 'payment_gateway_found_from_tags', [
                    'tags' => $tags,
                    'matched_gateway' => $mapping->payment_gateway_name,
                    'equivalent_candidates' => collect($candidates)->pluck('payment_gateway_name')->values()->all(),
                ]);
                return $mapping;
            }

            $this->orderLogService->logError($order, 'payment_gateway_ambiguous_match_from_tags', [
                'tags' => $tags,
                'candidates' => collect($candidates)->pluck('payment_gateway_name')->values()->all(),
                'error' => 'Varios métodos de pago configurados (con códigos SIESA distintos) coinciden con los tags del pedido; requiere revisión manual'
            ]);
            return null;
        }

        $this->orderLogService->logError($order, 'payment_gateway_not_found_in_tags', [
            'tags' => $tags,
            'error' => 'No se encontró ningún payment gateway configurado que coincida con los tags'
        ]);

        return null;
    }

    /**
     * Genera una línea completa del archivo (543 caracteres) para un producto
     *
     * @param array $commonData Datos comunes del encabezado
     * @param array $lineItem Producto del pedido
     * @param SiesaGeneralConfiguration $config Configuración general de SIESA
     * @param bool $isShipping Si es línea de envío (usa lista_precio_flete y motivo general)
     * @param bool $isGift Si es producto de obsequio (usa lista_precio_obsequio y motivo_obsequio)
     * @return string Línea de 543 caracteres
     */
    private function generateLine(array $commonData, array $lineItem, SiesaGeneralConfiguration $config, bool $isShipping = false, bool $isGift = false): string
    {
        $line = '';

        // 1) Posiciones 1-10: Orden de compra
        $line .= SiesaFileStructure::padLeft($commonData['orden_compra'], SiesaFileStructure::ORDEN_COMPRA_LENGTH, '0');

        // 2) Posición 11: Tipo de cliente
        $line .= $commonData['tipo_cliente'];

        // 3) Posiciones 12-31: Código EAN (vacío)
        $line .= SiesaFileStructure::padRight($commonData['codigo_ean'], SiesaFileStructure::CODIGO_EAN_LENGTH);

        // 4) Posiciones 32-44: Código del cliente
        $line .= SiesaFileStructure::padRight($commonData['codigo_cliente'], SiesaFileStructure::CODIGO_CLIENTE_LENGTH);

        // 5) Posiciones 45-46: Sucursal
        $line .= $commonData['sucursal'];

        // 6) Posiciones 47-54: Fecha del pedido
        $line .= $commonData['fecha_pedido'];

        // 7) Posiciones 55-57: Bodega
        $line .= SiesaFileStructure::padRight($commonData['bodega'], SiesaFileStructure::BODEGA_LENGTH);

        // 8) Posiciones 58-59: Localización
        $line .= SiesaFileStructure::padRight($commonData['localizacion'], SiesaFileStructure::LOCALIZACION_LENGTH);

        // 9) Posición 60: Tipo de búsqueda del ítem
        $line .= $config->tipo_busqueda_item->value;

        // 10) Posiciones 61-75: Código de barras (vacío)
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::CODIGO_BARRAS_LENGTH);

        // 11) Posiciones 76-90: Código del ítem (SKU)
        $sku = $lineItem['sku'] ?? '';
        $line .= SiesaFileStructure::padRight($sku, SiesaFileStructure::CODIGO_ITEM_LENGTH);

        // 12) Posiciones 91-93: Extensión del ítem (vacío)
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::EXTENSION_ITEM_LENGTH);

        // 13) Posiciones 94-101: Fecha de entrega (misma que fecha pedido)
        $line .= $commonData['fecha_pedido'];

        // 14) Posiciones 102-104: Unidad de captura
        $line .= SiesaFileStructure::padRight($config->unidad_captura, SiesaFileStructure::UNIDAD_CAPTURA_LENGTH);

        // 15) Posiciones 105-117: Cantidad
        $quantity = intval($lineItem['quantity'] ?? 0);
        $line .= SiesaFileStructure::formatQuantity($quantity);

        // 16) Posiciones 118-130: Cantidad unidad 2 (ceros)
        $line .= SiesaFileStructure::formatQuantity(0);

        // 17) Posición 131: Unidad del precio
        $line .= $config->unidad_precio->value;

        // 18) Posiciones 132-134: Lista de precio
        if ($isShipping) {
            $listaPrecio = $config->lista_precio_flete;
        } elseif ($isGift) {
            $listaPrecio = $config->lista_precio_obsequio;
        } else {
            $listaPrecio = $config->lista_precio;
        }
        $line .= SiesaFileStructure::padRight($listaPrecio, SiesaFileStructure::LISTA_PRECIO_LENGTH);

        // 19) Posiciones 135-136: Lista de descuento
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::LISTA_DESCUENTO_LENGTH);

        // 20) Posiciones 137-148: Precio unitario
        // Shopify entrega discount_allocations.amount como descuento total de la línea.
        $line .= SiesaFileStructure::formatPrice($this->calculateFinalUnitPrice($lineItem));

        // 21) Posiciones 149-152: Descuento línea 1
        $line .= SiesaFileStructure::formatPercentage(0);

        // 22) Posiciones 153-156: Descuento línea 2
        $line .= SiesaFileStructure::formatPercentage(0);

        // 23) Posiciones 157-176: Detalle del movimiento
        $line .= SiesaFileStructure::padRight($config->detalle_movimiento, SiesaFileStructure::DETALLE_MOVIMIENTO_LENGTH);

        // 24) Posiciones 177-216: Descripción del ítem (vacío porque tipo = R)
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::DESCRIPCION_ITEM_LENGTH);

        // 25) Posiciones 217-220: Punto de envío (vacío)
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::PUNTO_ENVIO_LENGTH);

        // 26) Posiciones 221-280: Observación 1
        $line .= SiesaFileStructure::truncate($commonData['observacion1'], SiesaFileStructure::OBSERVACION1_LENGTH);

        // 27) Posiciones 281-340: Observación 2 (vacío)
        $line .= SiesaFileStructure::truncate($commonData['observacion2'], SiesaFileStructure::OBSERVACION2_LENGTH);

        // 28-31) Posiciones 341-500: Descripción variable (vacío)
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::DESC_VARIABLE1_LENGTH);
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::DESC_VARIABLE2_LENGTH);
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::DESC_VARIABLE3_LENGTH);
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::DESC_VARIABLE4_LENGTH);

        // 32) Posiciones 501-513: Código vendedor
        $line .= SiesaFileStructure::padRight($commonData['codigo_vendedor'], SiesaFileStructure::CODIGO_VENDEDOR_LENGTH);

        // 33) Posiciones 514-515: Motivo
        $motivo = $isGift ? $config->motivo_obsequio : $commonData['motivo'];
        $line .= SiesaFileStructure::padRight($motivo, SiesaFileStructure::MOTIVO_LENGTH);

        // 34) Posiciones 516-523: Centro de costo
        $line .= SiesaFileStructure::padRight($commonData['centro_costo'], SiesaFileStructure::CENTRO_COSTO_LENGTH);

        // 35) Posiciones 524-533: Proyecto (vacío)
        $line .= SiesaFileStructure::padRight($commonData['proyecto'], SiesaFileStructure::PROYECTO_LENGTH);

        // 36) Posiciones 534-535: Condición de pago
        $line .= SiesaFileStructure::padRight($commonData['condicion_pago'], SiesaFileStructure::CONDICION_PAGO_LENGTH);

        // 37) Posiciones 536-543: Documento alterno
        $line .= SiesaFileStructure::padRight($commonData['documento_alterno'], SiesaFileStructure::DOCUMENTO_ALTERNO_LENGTH);

        return $line;
    }

    private function isGiftLine(array $lineItem): bool
    {
        $quantity = intval($lineItem['quantity'] ?? 0);

        if ($quantity <= 0) {
            return false;
        }

        $basePrice = floatval($lineItem['price'] ?? 0);
        $lineTotal = ($basePrice * $quantity) - $this->calculateLineDiscountAmount($lineItem);

        return $lineTotal == 0.0;
    }

    private function calculateFinalUnitPrice(array $lineItem): float
    {
        $quantity = intval($lineItem['quantity'] ?? 0);
        $basePrice = floatval($lineItem['price'] ?? 0);

        if ($quantity <= 0) {
            return $basePrice;
        }

        $lineTotal = ($basePrice * $quantity) - $this->calculateLineDiscountAmount($lineItem);

        return $lineTotal / $quantity;
    }

    private function calculateLineDiscountAmount(array $lineItem): float
    {
        return collect($lineItem['discount_allocations'] ?? [])
            ->sum(fn($allocation) => floatval($allocation['amount'] ?? 0));
    }
}
