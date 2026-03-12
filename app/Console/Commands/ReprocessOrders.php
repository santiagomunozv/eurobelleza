<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use App\Jobs\ProcessShopifyOrder;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use Illuminate\Console\Command;

class ReprocessOrders extends Command
{
    protected $signature = 'orders:reprocess
                            {--limit= : Cantidad de pedidos a procesar (si no se envía, procesa todos)}
                            {--status=pending : Estado de los pedidos (pending, processing, failed, completed, all)}
                            {--validate : Validar configuración antes de despachar}';

    protected $description = 'Reprocesa pedidos existentes despachando jobs a la cola';

    public function __construct(
        private OrderConfigurationValidator $configValidator,
        private OrderLogService $orderLogService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $status = $this->option('status');
        $shouldValidate = $this->option('validate');

        $this->info("🔄 Reprocesando pedidos...");
        $this->newLine();

        if ($limit !== null && $limit <= 0) {
            $this->error("❌ El valor de --limit debe ser mayor a 0");
            return self::FAILURE;
        }

        $query = Order::query()->orderBy('id');

        if ($status === 'all') {
            $query->where('status', '!=', OrderStatusEnum::COMPLETED->value);
        } else {
            try {
                $statusEnum = OrderStatusEnum::from($status);
                $query->where('status', $statusEnum->value);
            } catch (\ValueError $e) {
                $this->error("❌ Estado inválido: {$status}");
                $this->error("   Estados válidos: pending, processing, completed, failed, all");
                return self::FAILURE;
            }
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->warn("⚠️  No se encontraron pedidos con estado: {$status}");
            return self::SUCCESS;
        }

        $this->info("📦 Pedidos encontrados: {$orders->count()}");
        $this->newLine();

        $dispatched = 0;
        $skipped = 0;
        $errors = [];

        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();

        foreach ($orders as $order) {
            try {
                // Validar configuración si está habilitado
                if ($shouldValidate) {
                    $validation = $this->configValidator->validate($order->order_json);

                    if (!$validation['valid']) {
                        $this->orderLogService->logError($order, 'configuration_validation_failed', [
                            'errors' => $validation['errors'],
                            'details' => $validation['details'],
                            'context' => 'manual_reprocess',
                        ]);

                        $skipped++;
                        $errors[] = "Pedido #{$order->shopify_order_number}: " . implode(', ', $validation['errors']);
                        $progressBar->advance();
                        continue;
                    }
                }

                if ($order->status->value === OrderStatusEnum::COMPLETED->value) {
                    $skipped++;
                    $errors[] = "Pedido #{$order->shopify_order_number}: está COMPLETED y no se reprocesa";
                    $progressBar->advance();
                    continue;
                }

                if ($order->status->value !== OrderStatusEnum::PENDING->value) {
                    $previousStatus = $order->status->value;

                    $order->update([
                        'status' => OrderStatusEnum::PENDING->value,
                        'error_message' => null,
                        'processed_at' => null,
                        'attempts' => 0,
                    ]);

                    $this->orderLogService->logInfo($order, 'Pedido movido a PENDING para reproceso manual', [
                        'previous_status' => $previousStatus,
                    ]);
                }

                ProcessShopifyOrder::dispatch($order->fresh());
                $dispatched++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Pedido #{$order->shopify_order_number}: {$e->getMessage()}";
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
                ['Jobs despachados', $dispatched],
                ['Pedidos omitidos', $skipped],
            ]
        );

        // Mostrar errores
        if (!empty($errors)) {
            $this->newLine();
            $this->error("⚠️  Errores encontrados ({$skipped} pedidos omitidos):");
            foreach ($errors as $error) {
                $this->error("   - {$error}");
            }
        }

        $this->newLine();
        if ($dispatched > 0) {
            $this->info("✅ {$dispatched} jobs despachados a la cola");
            $this->info("   Ejecuta: php artisan queue:work para procesarlos");
        }

        return self::SUCCESS;
    }
}
