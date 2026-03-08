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
                            {--limit=10 : Cantidad de pedidos a procesar}
                            {--status=pending : Estado de los pedidos (pending, failed, completed)}
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
        $limit = (int) $this->option('limit');
        $status = $this->option('status');
        $shouldValidate = $this->option('validate');

        $this->info("🔄 Reprocesando pedidos...");
        $this->newLine();

        try {
            $statusEnum = OrderStatusEnum::from($status);
        } catch (\ValueError $e) {
            $this->error("❌ Estado inválido: {$status}");
            $this->error("   Estados válidos: pending, processing, completed, failed");
            return self::FAILURE;
        }

        // Obtener pedidos
        $orders = Order::where('status', $statusEnum)
            ->orderBy('id')
            ->limit($limit)
            ->get();

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

                // Despachar job
                ProcessShopifyOrder::dispatch($order);
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
