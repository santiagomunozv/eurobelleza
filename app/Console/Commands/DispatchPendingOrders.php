<?php

namespace App\Console\Commands;

use App\Enums\OrderStatusEnum;
use App\Jobs\ProcessShopifyOrder;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use Illuminate\Console\Command;

class DispatchPendingOrders extends Command
{
    protected $signature = 'orders:dispatch-pending
                            {--limit= : Cantidad máxima de pedidos a despachar}
                            {--validate : Validar configuración antes de despachar}';

    protected $description = 'Despacha jobs para procesar pedidos en estado pending';

    public function __construct(
        private OrderConfigurationValidator $configValidator,
        private OrderLogService $orderLogService,
        private OrderRepository $orderRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $shouldValidate = (bool) $this->option('validate');

        if ($limit !== null && $limit <= 0) {
            $this->error('El valor de --limit debe ser mayor a 0');
            return self::FAILURE;
        }

        $query = Order::query()
            ->where('status', OrderStatusEnum::PENDING->value)
            ->orderBy('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn('No se encontraron pedidos pending para despachar.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;
        $errors = [];

        foreach ($orders as $order) {
            $financialStatus = $order->order_json['financial_status'] ?? null;
            if ($financialStatus !== 'paid') {
                $this->orderLogService->logInfo($order, 'dispatch_pending_skipped_unpaid', [
                    'context' => 'orders_dispatch_pending_command',
                    'financial_status' => $financialStatus,
                ]);

                $skipped++;
                continue;
            }

            if ($shouldValidate) {
                $validation = $this->configValidator->validate($order->order_json);

                if (!$validation['valid']) {
                    $errorMessage = implode(' | ', $validation['errors']);
                    $this->orderRepository->updateStatus($order, OrderStatusEnum::FAILED, $errorMessage);

                    $this->orderLogService->logWarning($order, 'dispatch_pending_skipped_invalid_config', [
                        'context' => 'orders_dispatch_pending_command',
                        'errors' => $validation['errors'],
                        'details' => $validation['details'],
                    ]);

                    $skipped++;
                    $errors[] = "Pedido #{$order->shopify_order_number}: {$errorMessage}";
                    continue;
                }
            }

            ProcessShopifyOrder::dispatch($order);
            $this->orderLogService->logInfo($order, 'dispatch_pending_job_dispatched', [
                'context' => 'orders_dispatch_pending_command',
            ]);
            $dispatched++;
        }

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Pedidos encontrados', $orders->count()],
                ['Jobs despachados', $dispatched],
                ['Pedidos omitidos', $skipped],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->error('Errores encontrados:');
            foreach ($errors as $error) {
                $this->error(" - {$error}");
            }
        }

        return self::SUCCESS;
    }
}
