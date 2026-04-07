<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use App\Services\Shopify\ShopifyApiClient;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use App\Jobs\ProcessShopifyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshOrderData extends Command
{
    protected $signature = 'orders:refresh
                            {--ids=* : IDs específicos de pedidos a actualizar}
                            {--pending-without-fulfillments : Actualizar todos los PENDING sin fulfillments}
                            {--non-completed : Actualizar todos los pedidos no completados}
                            {--days=30 : Ventana de días para --non-completed}
                            {--reprocess : Reprocesar automáticamente si ahora tiene configuración válida}';

    protected $description = 'Reconsulta pedidos desde Shopify API y actualiza el order_json en la base de datos';

    public function __construct(
        private ShopifyApiClient $apiClient,
        private OrderConfigurationValidator $configValidator,
        private OrderLogService $orderLogService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('❌ El valor de --days debe ser mayor a 0');
            return self::FAILURE;
        }

        Log::info('Inicio de orders:refresh', [
            'ids' => $this->option('ids'),
            'pending_without_fulfillments' => (bool) $this->option('pending-without-fulfillments'),
            'non_completed' => (bool) $this->option('non-completed'),
            'days' => $days,
            'reprocess' => (bool) $this->option('reprocess'),
        ]);

        $this->info('🔄 Actualizando datos de pedidos desde Shopify API...');
        $this->newLine();

        $orders = $this->getOrdersToRefresh();

        if ($orders->isEmpty()) {
            $this->warn('⚠️  No se encontraron pedidos para actualizar');
            return self::SUCCESS;
        }

        $this->info("📦 Pedidos a actualizar: {$orders->count()}");
        $this->newLine();

        $updated = 0;
        $failed = 0;
        $reprocessed = 0;
        $errors = [];

        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();

        foreach ($orders as $order) {
            try {
                $freshData = $this->fetchOrderFromShopify($order->shopify_order_id);

                if (!$freshData) {
                    $failed++;
                    $errors[] = "Pedido #{$order->shopify_order_number}: No se pudo obtener de Shopify API";
                    $this->orderLogService->logWarning($order, 'refresh_order_data_not_found_in_shopify', [
                        'context' => 'orders_refresh_command',
                    ]);
                    $progressBar->advance();
                    continue;
                }

                // Actualizar el JSON
                $order->order_json = $freshData;
                $order->save();
                $updated++;
                $this->orderLogService->logInfo($order, 'refresh_order_data_updated_from_shopify', [
                    'context' => 'orders_refresh_command',
                    'status_before' => $order->status->value,
                ]);

                // Si tiene opción de reprocesar y el pedido no está completado
                if ($this->option('reprocess') && !in_array($order->status, [
                    OrderStatusEnum::COMPLETED,
                    OrderStatusEnum::SENT_TO_SIESA,
                    OrderStatusEnum::RPA_PROCESSING,
                ], true)) {
                    // Validar si ahora tiene configuración completa
                    $validation = $this->configValidator->validate($freshData);

                    if ($validation['valid']) {
                        if ($order->status !== OrderStatusEnum::PENDING) {
                            $order->status = OrderStatusEnum::PENDING;
                            $order->error_message = null;
                            $order->processed_at = null;
                            $order->attempts = 0;
                            $order->save();
                        }

                        ProcessShopifyOrder::dispatch($order->fresh());
                        $this->orderLogService->logInfo($order, 'refresh_order_data_reprocess_dispatched', [
                            'context' => 'orders_refresh_command',
                            'status_after' => OrderStatusEnum::PENDING->value,
                        ]);
                        $reprocessed++;
                    } else {
                        $this->orderLogService->logWarning($order, 'refresh_order_data_reprocess_skipped_invalid_config', [
                            'context' => 'orders_refresh_command',
                            'errors' => $validation['errors'],
                            'details' => $validation['details'],
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Pedido #{$order->shopify_order_number}: {$e->getMessage()}";
                $this->orderLogService->logError($order, 'refresh_order_data_exception', [
                    'context' => 'orders_refresh_command',
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resultados
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Pedidos encontrados', $orders->count()],
                ['Pedidos actualizados', $updated],
                ['Jobs reprocesados', $reprocessed],
                ['Fallos', $failed],
            ]
        );

        // Mostrar errores
        if (!empty($errors)) {
            $this->newLine();
            $this->error("⚠️  Errores encontrados:");
            foreach ($errors as $error) {
                $this->error("   - {$error}");
            }
        }

        $this->newLine();
        if ($updated > 0) {
            $this->info("✅ {$updated} pedidos actualizados exitosamente");
            if ($reprocessed > 0) {
                $this->info("✅ {$reprocessed} pedidos reprocesados (ejecuta queue:work)");
            }
        }

        Log::info('Fin de orders:refresh', [
            'orders_found' => $orders->count(),
            'updated' => $updated,
            'reprocessed' => $reprocessed,
            'failed' => $failed,
            'errors_count' => count($errors),
        ]);

        return self::SUCCESS;
    }

    private function getOrdersToRefresh()
    {
        $days = (int) $this->option('days');
        $fromDate = now()->subDays($days);

        // Si se especificaron IDs
        if (!empty($this->option('ids'))) {
            return Order::whereIn('id', $this->option('ids'))->get();
        }

        // Si se especificó la opción para PENDING sin fulfillments
        if ($this->option('pending-without-fulfillments')) {
            return Order::where('status', OrderStatusEnum::PENDING->value)
                ->whereRaw("JSON_LENGTH(order_json->'$.fulfillments') = 0")
                ->get();
        }

        if ($this->option('non-completed')) {
            return Order::whereNotIn('status', [
                OrderStatusEnum::COMPLETED->value,
                OrderStatusEnum::SENT_TO_SIESA->value,
                OrderStatusEnum::RPA_PROCESSING->value,
            ])
                ->where('created_at', '>=', $fromDate)
                ->get();
        }

        $this->error('❌ Debes especificar --ids, --pending-without-fulfillments o --non-completed');
        return collect();
    }

    private function fetchOrderFromShopify(string $shopifyOrderId): ?array
    {
        if ($this->apiClient->needsTokenRefresh()) {
            $this->apiClient->refreshAccessToken();
        }

        return $this->apiClient->getOrderById($shopifyOrderId);
    }
}
