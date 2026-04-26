<?php

namespace App\Console\Commands;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Services\OrderLogService;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkExpiredPayments extends Command
{
    protected $signature = 'orders:mark-expired-payments
                            {--days=3 : Edad mínima en días para considerar vencido}
                            {--max-days=30 : Edad máxima en días a revisar}
                            {--limit= : Límite opcional de pedidos a revisar}
                            {--dry-run : Simular sin actualizar pedidos}';

    protected $description = 'Marca como vencidos los pedidos pendientes cuyo pago no se concretó en Shopify';

    public function __construct(
        private ShopifyApiClient $apiClient,
        private OrderLogService $orderLogService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $maxDays = (int) $this->option('max-days');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($days <= 0 || $maxDays <= 0 || $maxDays < $days) {
            $this->error('El rango debe cumplir: --days > 0, --max-days > 0 y --max-days >= --days');
            return self::FAILURE;
        }

        if ($limit !== null && $limit <= 0) {
            $this->error('El valor de --limit debe ser mayor a 0');
            return self::FAILURE;
        }

        if ($this->apiClient->needsTokenRefresh()) {
            $this->apiClient->refreshAccessToken();
        }

        $query = Order::query()
            ->where('status', OrderStatusEnum::PENDING->value)
            ->where('created_at', '<=', now()->subDays($days))
            ->where('created_at', '>=', now()->subDays($maxDays))
            ->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn('No se encontraron pedidos pendientes para revisar.');
            return self::SUCCESS;
        }

        $this->info("Pedidos pendientes a revisar: {$orders->count()}");
        if ($dryRun) {
            $this->warn('Modo dry-run: no se actualizarán pedidos.');
        }

        $expired = 0;
        $paid = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $freshData = $this->apiClient->getOrderById($order->shopify_order_id);

                if (!$freshData) {
                    $failed++;
                    $this->warn("Pedido #{$order->shopify_order_number}: no se pudo obtener desde Shopify.");
                    continue;
                }

                $financialStatus = $freshData['financial_status'] ?? null;

                if ($financialStatus === 'paid') {
                    $paid++;

                    if (!$dryRun) {
                        $order->order_json = $freshData;
                        $order->save();

                        $this->orderLogService->logInfo($order, 'payment_expired_check_skipped_paid', [
                            'context' => 'mark_expired_payments_command',
                            'financial_status' => $financialStatus,
                        ]);
                    }

                    continue;
                }

                if (!$dryRun) {
                    $order->update([
                        'order_json' => $freshData,
                        'status' => OrderStatusEnum::PAYMENT_EXPIRED->value,
                        'error_message' => "Pago vencido o no confirmado en Shopify. Estado de pago: {$financialStatus}",
                        'processed_at' => null,
                    ]);

                    $this->orderLogService->logWarning($order, 'payment_expired_detected', [
                        'context' => 'mark_expired_payments_command',
                        'financial_status' => $financialStatus,
                        'days_threshold' => $days,
                        'max_days_window' => $maxDays,
                    ]);
                }

                $expired++;
            } catch (\Throwable $e) {
                $failed++;

                Log::error('Error marcando pago vencido', [
                    'order_id' => $order->id,
                    'shopify_order_id' => $order->shopify_order_id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Pedido #{$order->shopify_order_number}: {$e->getMessage()}");
            }
        }

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Pedidos revisados', $orders->count()],
                ['Marcados como vencidos', $expired],
                ['Pagados, no modificados', $paid],
                ['Fallos', $failed],
            ]
        );

        return self::SUCCESS;
    }
}
