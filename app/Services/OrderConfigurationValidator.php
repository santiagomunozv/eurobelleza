<?php

namespace App\Services;

use App\Models\SiesaGeneralConfiguration;
use App\Models\SiesaPaymentGatewayMapping;
use App\Repositories\SiesaWarehouseMappingRepository;

class OrderConfigurationValidator
{
    private const USE_FIXED_BARRANQUILLA_WAREHOUSE = true;
    private const FIXED_BARRANQUILLA_LOCATION_ID = 80414146731;

    public function __construct(
        private SiesaWarehouseMappingRepository $warehouseRepository
    ) {}

    /**
     * Valida que todas las configuraciones necesarias existan para procesar un pedido
     *
     * @param array $orderData JSON del pedido de Shopify
     * @return array ['valid' => bool, 'errors' => array, 'details' => array]
     */
    public function validate(array $orderData): array
    {
        $errors = [];
        $details = [];

        // 1. Validar configuración general de SIESA
        $generalConfigError = $this->validateGeneralConfiguration();
        if ($generalConfigError) {
            $errors[] = $generalConfigError;
            $details['general_configuration'] = 'missing';
        } else {
            $details['general_configuration'] = 'ok';
        }

        // 2. Validar payment gateway mapping
        $paymentGatewayError = $this->validatePaymentGatewayMapping($orderData);
        if ($paymentGatewayError) {
            $errors[] = $paymentGatewayError;
            $details['payment_gateway'] = $orderData['payment_gateway_names'][0] ?? 'undefined';
        } else {
            $details['payment_gateway'] = 'ok';
        }

        // 3. Validar warehouse mapping
        $warehouseError = $this->validateWarehouseMapping($orderData);
        if ($warehouseError) {
            $errors[] = $warehouseError;
            $details['warehouse_location_id'] = self::USE_FIXED_BARRANQUILLA_WAREHOUSE
                ? self::FIXED_BARRANQUILLA_LOCATION_ID
                : ($orderData['fulfillments'][0]['location_id'] ?? 'undefined');
        } else {
            $details['warehouse_location_id'] = 'ok';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * Valida que exista la configuración general de SIESA
     *
     * @return string|null Mensaje de error o null si es válido
     */
    private function validateGeneralConfiguration(): ?string
    {
        try {
            SiesaGeneralConfiguration::getConfig();
            return null;
        } catch (\Exception $e) {
            return 'Falta configuración general de SIESA. Configure el sistema en Config. SIESA.';
        }
    }

    /**
     * Valida que exista el mapping para el método de pago
     *
     * @param array $orderData JSON del pedido
     * @return string|null Mensaje de error o null si es válido
     */
    private function validatePaymentGatewayMapping(array $orderData): ?string
    {
        $paymentGateways = $orderData['payment_gateway_names'] ?? [];

        if (empty($paymentGateways)) {
            return 'El pedido no tiene método de pago (payment_gateway_names).';
        }

        $gatewayName = $paymentGateways[0];
        $mapping = SiesaPaymentGatewayMapping::findByGateway($gatewayName);

        if (!$mapping) {
            return "Falta configuración para el método de pago: {$gatewayName}. Configure en Métodos de Pago.";
        }

        return null;
    }

    /**
     * Valida que exista el mapping de bodega requerido.
     *
     * @param array $orderData JSON del pedido
     * @return string|null Mensaje de error o null si es válido
     */
    private function validateWarehouseMapping(array $orderData): ?string
    {
        if (!self::USE_FIXED_BARRANQUILLA_WAREHOUSE) {
            return $this->validateWarehouseMappingFromOrderData($orderData);
        }

        $mapping = $this->warehouseRepository->findByShopifyLocationId(self::FIXED_BARRANQUILLA_LOCATION_ID);

        if (!$mapping) {
            return 'Falta configuración de bodega fija BODEGA BARRANQUILLA para location_id: ' . self::FIXED_BARRANQUILLA_LOCATION_ID . '. Configure en Ubicaciones.';
        }

        return null;
    }

    private function validateWarehouseMappingFromOrderData(array $orderData): ?string
    {
        $fulfillments = $orderData['fulfillments'] ?? [];
        $locationId = $fulfillments[0]['location_id'] ?? null;

        if (!$locationId) {
            $mappings = $this->warehouseRepository->all();
            if ($mappings->count() === 1) {
                return null;
            }

            return "El pedido no tiene location_id en fulfillments";
        }

        $mapping = $this->warehouseRepository->findByShopifyLocationId($locationId);

        if (!$mapping) {
            return "Falta configuración de bodega para location_id: {$locationId}. Configure en Ubicaciones.";
        }

        return null;
    }
}
