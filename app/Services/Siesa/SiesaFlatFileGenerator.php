<?php

namespace App\Services\Siesa;

use App\Models\Order;
use App\Models\SiesaGeneralConfiguration;
use App\Helpers\SiesaFileStructure;
use App\Services\OrderLogService;

class SiesaFlatFileGenerator
{
    private OrderLogService $orderLogService;

    public function __construct(OrderLogService $orderLogService)
    {
        $this->orderLogService = $orderLogService;
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

        $commonData = $this->prepareCommonData($orderData, $config);

        $lineItems = $orderData['line_items'] ?? [];

        if (empty($lineItems)) {
            throw new \Exception("El pedido #{$order->shopify_order_number} no tiene productos (line_items)");
        }

        $lines = [];
        foreach ($lineItems as $lineItem) {
            $lines[] = $this->generateLine($commonData, $lineItem, $config);
        }

        return implode("\n", $lines);
    }

    /**
     * Prepara los datos comunes del encabezado que se repiten en cada línea
     *
     * @param array $orderData JSON del pedido de Shopify
     * @param SiesaGeneralConfiguration $config Configuración general de SIESA
     * @return array
     */
    private function prepareCommonData(array $orderData, SiesaGeneralConfiguration $config): array
    {
        $shippingAddress = $orderData['shipping_address'] ?? [];
        $observations = SiesaFileStructure::formatObservations($shippingAddress);

        $paymentGateways = $orderData['payment_gateway_names'] ?? [];
        $sucursal = in_array('Addi Payment', $paymentGateways) ? '02' : '01';

        $orderNumber = (string)($orderData['order_number'] ?? '');
        $documentoAlterno = str_pad($orderNumber, 8, '0', STR_PAD_LEFT);

        return [
            'orden_compra' => $orderNumber,
            'tipo_cliente' => $config->tipo_cliente->value,
            'codigo_ean' => '',
            'codigo_cliente' => $config->codigo_cliente,
            'sucursal' => $sucursal,
            'fecha_pedido' => SiesaFileStructure::formatDate($orderData['created_at'] ?? now()),
            'bodega' => config('siesa.default_warehouse', '001'),
            'localizacion' => config('siesa.default_location', '15'),
            'observacion1' => $observations['observacion1'],
            'observacion2' => $observations['observacion2'],
            'codigo_vendedor' => $config->codigo_vendedor,
            'motivo' => $config->motivo,
            'centro_costo' => config('siesa.default_cost_center', ''),
            'proyecto' => '',
            'condicion_pago' => config('siesa.default_payment_condition', ''),
            'documento_alterno' => $documentoAlterno,
        ];
    }

    /**
     * Genera una línea completa del archivo (543 caracteres) para un producto
     *
     * @param array $commonData Datos comunes del encabezado
     * @param array $lineItem Producto del pedido
     * @param SiesaGeneralConfiguration $config Configuración general de SIESA
     * @return string Línea de 543 caracteres
     */
    private function generateLine(array $commonData, array $lineItem, SiesaGeneralConfiguration $config): string
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
        $line .= SiesaFileStructure::padRight($config->lista_precio, SiesaFileStructure::LISTA_PRECIO_LENGTH);

        // 19) Posiciones 135-136: Lista de descuento
        $line .= SiesaFileStructure::padRight('', SiesaFileStructure::LISTA_DESCUENTO_LENGTH);

        // 20) Posiciones 137-148: Precio unitario
        $price = $lineItem['price'] ?? '0.00';
        $line .= SiesaFileStructure::formatPrice($price);

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
        $line .= SiesaFileStructure::padRight($commonData['motivo'], SiesaFileStructure::MOTIVO_LENGTH);

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
}
