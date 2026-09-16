<?php

namespace App\Services\Siesa;

use App\Services\Shopify\ShopifyApiClient;

class SiesaPaymentGatewayResolver
{
    public function __construct(
        private ShopifyApiClient $shopifyApiClient
    ) {}

    /**
     * Resuelve el nombre del gateway que realmente cobró el pedido.
     *
     * Shopify puede reportar más de un valor en payment_gateway_names cuando
     * hubo un intento de pago rechazado seguido de uno exitoso con otro
     * método (p. ej. Addi rechazado por riesgo, luego pagado con Mercado
     * Pago). En ese caso no se puede asumir que el primero del arreglo es el
     * correcto: hay que consultar las transacciones del pedido y usar el
     * gateway de la transacción kind=sale con status=success.
     *
     * Si solo hay un gateway reportado, se devuelve directo sin llamar a la
     * API (caso normal, evita una llamada extra por pedido).
     *
     * @param array $paymentGatewayNames orderData['payment_gateway_names']
     * @param string $shopifyOrderId orderData['id'] (id numérico de Shopify)
     * @return array{gateway: ?string, error: ?string}
     */
    public function resolve(array $paymentGatewayNames, string $shopifyOrderId): array
    {
        if (count($paymentGatewayNames) <= 1) {
            return ['gateway' => $paymentGatewayNames[0] ?? null, 'error' => null];
        }

        try {
            $transactions = $this->shopifyApiClient->getOrderTransactions($shopifyOrderId);
        } catch (\Exception $e) {
            return [
                'gateway' => null,
                'error' => "No se pudo consultar las transacciones del pedido en Shopify: {$e->getMessage()}",
            ];
        }

        $successfulSale = collect($transactions)
            ->filter(fn ($t) => ($t['kind'] ?? null) === 'sale' && ($t['status'] ?? null) === 'success')
            ->sortByDesc('created_at')
            ->first();

        if (!$successfulSale) {
            return [
                'gateway' => null,
                'error' => 'El pedido reporta varios métodos de pago (' . implode(', ', $paymentGatewayNames)
                    . ') pero ninguna transacción exitosa (kind=sale, status=success) en Shopify',
            ];
        }

        return ['gateway' => $successfulSale['gateway'] ?? null, 'error' => null];
    }
}
