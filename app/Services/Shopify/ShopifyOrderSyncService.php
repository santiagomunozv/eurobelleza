<?php

namespace App\Services\Shopify;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Jobs\ProcessShopifyOrder;
use App\Enums\OrderStatusEnum;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyOrderSyncService
{
    private ShopifyApiClient $apiClient;
    private OrderRepository $orderRepository;
    private OrderConfigurationValidator $configValidator;
    private OrderLogService $orderLogService;

    public function __construct(
        ShopifyApiClient $apiClient,
        OrderRepository $orderRepository,
        OrderConfigurationValidator $configValidator,
        OrderLogService $orderLogService
    ) {
        $this->apiClient = $apiClient;
        $this->orderRepository = $orderRepository;
        $this->configValidator = $configValidator;
        $this->orderLogService = $orderLogService;
    }

    /**
     * Sincroniza pedidos de Shopify en un rango de fechas
     *
     * @param Carbon $dateFrom Fecha desde
     * @param Carbon $dateTo Fecha hasta
     * @param bool $dryRun Si es true, no guarda ni despacha jobs
     * @return array Estadísticas de la sincronización
     */
    public function syncOrders(Carbon $dateFrom, Carbon $dateTo, bool $dryRun = false): array
    {
        $stats = [
            'date_from' => $dateFrom->toDateTimeString(),
            'date_to' => $dateTo->toDateTimeString(),
            'orders_found' => 0,
            'orders_existing' => 0,
            'orders_missing' => 0,
            'orders_processed' => 0,
            'orders_skipped' => 0,
            'orders_failed' => 0,
            'errors' => [],
        ];

        try {
            // 1. Obtener pedidos de Shopify
            $shopifyOrders = $this->apiClient->getOrders(
                $dateFrom->toIso8601String(),
                $dateTo->toIso8601String()
            );

            $stats['orders_found'] = count($shopifyOrders);

            if (empty($shopifyOrders)) {
                return $stats;
            }

            // 2. Encontrar pedidos faltantes
            $missingOrders = $this->findMissingOrders($shopifyOrders);
            $stats['orders_missing'] = count($missingOrders);
            $stats['orders_existing'] = $stats['orders_found'] - $stats['orders_missing'];

            // 3. Procesar pedidos faltantes
            if (!empty($missingOrders) && !$dryRun) {
                $result = $this->processMissingOrders($missingOrders);
                $stats['orders_processed'] = $result['processed'];
                $stats['orders_skipped'] = $result['skipped'] ?? 0;
                $stats['orders_failed'] = $result['failed'];
                $stats['errors'] = array_merge($stats['errors'], $result['errors']);
            }

            return $stats;
        } catch (\Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Error en sincronización de pedidos', [
                'error' => $e->getMessage(),
                'date_range' => "{$dateFrom} - {$dateTo}",
            ]);
            throw $e;
        }
    }

    /**
     * Encuentra pedidos que no existen en la base de datos
     */
    private function findMissingOrders(array $shopifyOrders): array
    {
        $missing = [];

        foreach ($shopifyOrders as $shopifyOrder) {
            $shopifyOrderId = $shopifyOrder['id'] ?? null;

            if (!$shopifyOrderId) {
                continue;
            }

            // Buscar si ya existe en BD
            $exists = Order::where('shopify_order_id', (string)$shopifyOrderId)->exists();

            if (!$exists) {
                $missing[] = $shopifyOrder;
            }
        }

        return $missing;
    }

    /**
     * Procesa y guarda pedidos faltantes
     */
    private function processMissingOrders(array $missingOrders): array
    {
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($missingOrders as $orderData) {
            try {
                $shopifyOrderId = (string)($orderData['id'] ?? '');
                $shopifyOrderNumber = (string)($orderData['order_number'] ?? '');
                $financialStatus = $orderData['financial_status'] ?? null;

                // Usar el mismo patrón que el webhook
                $order = $this->orderRepository->create([
                    'shopify_order_id' => $shopifyOrderId,
                    'shopify_order_number' => $shopifyOrderNumber,
                    'order_json' => $orderData,
                    'status' => OrderStatusEnum::PENDING->value,
                    'attempts' => 0,
                ]);

                // Los pedidos pagados y válidos se despachan para dejar el PE0 listo para el RPA.
                if ($financialStatus === 'paid') {
                    $validation = $this->configValidator->validate($orderData);

                    if ($validation['valid']) {
                        ProcessShopifyOrder::dispatch($order);
                        $processed++;
                    } else {
                        if (!$this->shouldKeepPending($validation['errors'])) {
                            $this->orderRepository->updateStatus(
                                $order,
                                OrderStatusEnum::FAILED,
                                implode(' | ', $validation['errors'])
                            );
                        }

                        $this->orderLogService->logError($order, 'configuration_validation_failed', [
                            'errors' => $validation['errors'],
                            'details' => $validation['details'],
                            'kept_pending' => $this->shouldKeepPending($validation['errors']),
                        ]);
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $failed++;
                $orderNumber = $orderData['order_number'] ?? $orderData['id'] ?? 'unknown';
                $errors[] = "Pedido {$orderNumber}: {$e->getMessage()}";

                Log::error('Error sincronizando pedido', [
                    'order_data' => $orderData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Obtiene el rango de fechas por defecto (ayer completo)
     */
    public static function getDefaultDateRange(): array
    {
        $yesterday = Carbon::yesterday();

        return [
            'from' => $yesterday->copy()->startOfDay(),
            'to' => $yesterday->copy()->endOfDay(),
        ];
    }

    private function shouldKeepPending(array $errors): bool
    {
        foreach ($errors as $error) {
            if (str_contains($error, 'no tiene location_id en fulfillments')) {
                return true;
            }
        }

        return false;
    }
}
