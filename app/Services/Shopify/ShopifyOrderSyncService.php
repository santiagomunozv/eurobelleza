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
            'pending_updated' => 0,
            'pending_reprocessed' => 0,
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

            // 4. Actualizar pedidos PENDING (pueden haber cambiado su financial_status)
            if (!$dryRun) {
                $pendingResult = $this->updatePendingOrders($shopifyOrders);
                $stats['pending_updated'] = $pendingResult['updated'];
                $stats['pending_reprocessed'] = $pendingResult['reprocessed'];
                $stats['errors'] = array_merge($stats['errors'], $pendingResult['errors']);
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

                // Solo despachar si está pagado y tiene configuración válida
                if ($financialStatus === 'paid') {
                    $validation = $this->configValidator->validate($orderData);

                    if ($validation['valid']) {
                        ProcessShopifyOrder::dispatch($order);
                        $processed++;
                    } else {
                        // Configuración incompleta, mantener en PENDING
                        $this->orderLogService->logError($order, 'configuration_validation_failed', [
                            'errors' => $validation['errors'],
                            'details' => $validation['details'],
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
     * Actualiza pedidos PENDING con datos frescos de Shopify y los reprocesa si cumplen con la configuración
     *
     * Casos que cubre:
     * - Pedidos que llegaron sin pagar y ahora están pagados
     * - Pedidos que llegaron sin fulfillments y ahora los tienen
     * - Pedidos que llegaron sin payment gateway mapping configurado y ahora existe
     * - Cualquier actualización en el JSON que permita completar la configuración
     */
    private function updatePendingOrders(array $shopifyOrders): array
    {
        $updated = 0;
        $reprocessed = 0;
        $errors = [];

        // Crear un mapa de shopify_order_id => order_data para búsqueda rápida
        $shopifyOrdersMap = [];
        foreach ($shopifyOrders as $shopifyOrder) {
            $shopifyOrderId = (string)($shopifyOrder['id'] ?? '');
            if ($shopifyOrderId) {
                $shopifyOrdersMap[$shopifyOrderId] = $shopifyOrder;
            }
        }

        // Obtener pedidos PENDING de la BD que coincidan con los IDs de Shopify
        $pendingOrders = Order::where('status', OrderStatusEnum::PENDING->value)
            ->whereIn('shopify_order_id', array_keys($shopifyOrdersMap))
            ->get();

        foreach ($pendingOrders as $order) {
            try {
                $shopifyOrderId = $order->shopify_order_id;
                $newOrderData = $shopifyOrdersMap[$shopifyOrderId] ?? null;

                if (!$newOrderData) {
                    continue;
                }

                // Actualizar el JSON con datos frescos de Shopify
                $order->order_json = $newOrderData;
                $order->save();

                $updated++;

                // Si el pedido está pagado, validar configuración y intentar reprocesar
                $financialStatus = $newOrderData['financial_status'] ?? null;

                if ($financialStatus === 'paid') {
                    $validation = $this->configValidator->validate($newOrderData);

                    if ($validation['valid']) {
                        // Ahora tiene configuración completa, reprocesar
                        ProcessShopifyOrder::dispatch($order);
                        $reprocessed++;
                    } else {
                        // Aún falta configuración, mantener en PENDING
                        $this->orderLogService->logError($order, 'configuration_validation_failed', [
                            'errors' => $validation['errors'],
                            'details' => $validation['details'],
                            'context' => 'pending_order_update',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Pedido {$order->shopify_order_number}: {$e->getMessage()}";

                Log::error('Error actualizando pedido PENDING', [
                    'order_id' => $order->id,
                    'shopify_order_id' => $order->shopify_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'updated' => $updated,
            'reprocessed' => $reprocessed,
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
}
