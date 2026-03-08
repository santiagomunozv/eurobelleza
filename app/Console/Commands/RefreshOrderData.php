<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use App\Services\Shopify\ShopifyApiClient;
use App\Services\OrderConfigurationValidator;
use App\Jobs\ProcessShopifyOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RefreshOrderData extends Command
{
    protected $signature = 'orders:refresh
                            {--ids=* : IDs específicos de pedidos a actualizar}
                            {--pending-without-fulfillments : Actualizar todos los PENDING sin fulfillments}
                            {--reprocess : Reprocesar automáticamente si ahora tiene configuración válida}';

    protected $description = 'Reconsulta pedidos desde Shopify API y actualiza el order_json en la base de datos';

    public function __construct(
        private ShopifyApiClient $apiClient,
        private OrderConfigurationValidator $configValidator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
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
                    $progressBar->advance();
                    continue;
                }

                // Actualizar el JSON
                $order->order_json = $freshData;
                $order->save();
                $updated++;

                // Si tiene opción de reprocesar y el pedido es PENDING
                if ($this->option('reprocess') && $order->status === OrderStatusEnum::PENDING) {
                    // Validar si ahora tiene configuración completa
                    $validation = $this->configValidator->validate($freshData);

                    if ($validation['valid']) {
                        ProcessShopifyOrder::dispatch($order);
                        $reprocessed++;
                    }
                }
            } catch (\Exception $e) {
                $failed++;
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

        return self::SUCCESS;
    }

    private function getOrdersToRefresh()
    {
        // Si se especificaron IDs
        if (!empty($this->option('ids'))) {
            return Order::whereIn('id', $this->option('ids'))->get();
        }

        // Si se especificó la opción para PENDING sin fulfillments
        if ($this->option('pending-without-fulfillments')) {
            return Order::where('status', OrderStatusEnum::PENDING)
                ->whereRaw("JSON_LENGTH(order_json->'$.fulfillments') = 0")
                ->get();
        }

        $this->error('❌ Debes especificar --ids o --pending-without-fulfillments');
        return collect();
    }

    private function fetchOrderFromShopify(string $shopifyOrderId): ?array
    {
        $shopDomain = config('shopify.shop_domain');
        $apiVersion = config('shopify.api_version', '2024-01');

        // Obtener token
        if ($this->apiClient->needsTokenRefresh()) {
            $this->apiClient->refreshAccessToken();
        }

        $token = cache()->get('shopify_access_token');

        if (!$token) {
            throw new \Exception('No se pudo obtener el token de Shopify');
        }

        $url = "https://{$shopDomain}/admin/api/{$apiVersion}/orders/{$shopifyOrderId}.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ])->get($url);

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['order'] ?? null;
    }
}
