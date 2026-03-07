<?php

namespace App\Services\Shopify;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Jobs\ProcessShopifyOrder;
use App\Enums\OrderStatusEnum;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyOrderSyncService
{
  private ShopifyApiClient $apiClient;
  private OrderRepository $orderRepository;

  public function __construct(
    ShopifyApiClient $apiClient,
    OrderRepository $orderRepository
  ) {
    $this->apiClient = $apiClient;
    $this->orderRepository = $orderRepository;
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

      if (empty($missingOrders)) {
        return $stats;
      }

      // 3. Procesar pedidos faltantes
      if (!$dryRun) {
        $result = $this->processMissingOrders($missingOrders);
        $stats['orders_processed'] = $result['processed'];
        $stats['orders_failed'] = $result['failed'];
        $stats['errors'] = $result['errors'];
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
    $errors = [];

    foreach ($missingOrders as $orderData) {
      try {
        $shopifyOrderId = (string)($orderData['id'] ?? '');
        $shopifyOrderNumber = (string)($orderData['order_number'] ?? '');

        // Usar el mismo patrón que el webhook
        $order = $this->orderRepository->create([
          'shopify_order_id' => $shopifyOrderId,
          'shopify_order_number' => $shopifyOrderNumber,
          'order_json' => $orderData,
          'status' => OrderStatusEnum::PENDING->value,
          'attempts' => 0,
        ]);

        // Despachar job para procesar
        ProcessShopifyOrder::dispatch($order);

        $processed++;

        Log::info('Pedido sincronizado desde Shopify', [
          'shopify_order_id' => $order->shopify_order_id,
          'order_number' => $order->shopify_order_number,
        ]);
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
}
