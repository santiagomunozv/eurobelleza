<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Shopify\ShopifyOrderSyncService;
use App\Services\Shopify\ShopifyApiClient;
use Carbon\Carbon;

class SyncShopifyOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-orders
                            {--from= : Fecha desde (Y-m-d o "yesterday")}
                            {--to= : Fecha hasta (Y-m-d)}
                            {--dry-run : Simular sin guardar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza pedidos de Shopify que no llegaron por webhook';

    private ShopifyOrderSyncService $syncService;
    private ShopifyApiClient $apiClient;

    public function __construct(ShopifyOrderSyncService $syncService, ShopifyApiClient $apiClient)
    {
        parent::__construct();
        $this->syncService = $syncService;
        $this->apiClient = $apiClient;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Shopify Order Sync');
        $this->newLine();

        // Validar conexión
        if (!$this->validateConnection()) {
            return self::FAILURE;
        }

        // Obtener rango de fechas
        [$dateFrom, $dateTo] = $this->getDateRange();

        $this->info("📅 Rango: {$dateFrom->toDateTimeString()} → {$dateTo->toDateTimeString()}");
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('⚠️  Modo DRY RUN - No se guardarán cambios');
            $this->newLine();
        }

        try {
            // Ejecutar sincronización
            $this->info('🔍 Consultando Shopify API...');

            $stats = $this->syncService->syncOrders($dateFrom, $dateTo, $isDryRun);

            $this->displayStats($stats, $isDryRun);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Valida la conexión con Shopify
     */
    private function validateConnection(): bool
    {
        $this->info('🔐 Validando configuración...');

        try {
            // Renovar token si es necesario
            if ($this->apiClient->needsTokenRefresh()) {
                $this->warn('⚠️  Token expirado o no encontrado en cache, renovando...');

                try {
                    $newToken = $this->apiClient->refreshAccessToken();
                    $this->info('✅ Token renovado exitosamente');
                    $this->newLine();
                } catch (\Exception $e) {
                    $this->error('❌ No se pudo renovar el token');
                    $this->error("   {$e->getMessage()}");
                    $this->error('   Verifica SHOPIFY_CLIENT_ID y SHOPIFY_CLIENT_SECRET en .env');
                    return false;
                }
            }

            // Probar conexión
            if (!$this->apiClient->testConnection()) {
                $this->error('❌ No se pudo conectar con Shopify API');
                $this->error('   Verifica SHOPIFY_SHOP_DOMAIN y SHOPIFY_ACCESS_TOKEN en .env');
                return false;
            }

            $this->info('✅ Conexión exitosa');
            $this->newLine();
            return true;
        } catch (\Exception $e) {
            $this->error('❌ Error de configuración: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el rango de fechas desde las opciones o usa el default
     */
    private function getDateRange(): array
    {
        $from = $this->option('from');
        $to = $this->option('to');

        // Si no se especifica, usar ayer completo
        if (!$from && !$to) {
            $range = ShopifyOrderSyncService::getDefaultDateRange();
            return [$range['from'], $range['to']];
        }

        // Parsear fechas personalizadas
        try {
            $dateFrom = $from === 'yesterday'
                ? Carbon::yesterday()->startOfDay()
                : Carbon::parse($from ?? 'yesterday')->startOfDay();

            $dateTo = $to
                ? Carbon::parse($to)->endOfDay()
                : $dateFrom->copy()->endOfDay();

            return [$dateFrom, $dateTo];
        } catch (\Exception $e) {
            $this->error('❌ Formato de fecha inválido. Use Y-m-d (ej: 2026-03-06)');
            exit(self::FAILURE);
        }
    }

    /**
     * Muestra las estadísticas de sincronización
     */
    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();

        // Tabla de resultados
        $rows = [
            ['Pedidos encontrados en Shopify', $stats['orders_found']],
            ['Pedidos ya existentes en BD', $stats['orders_existing']],
            ['Pedidos faltantes', $stats['orders_missing']],
            ['Pedidos procesados', $isDryRun ? 'N/A (dry-run)' : $stats['orders_processed']],
            ['Pedidos fallidos', $isDryRun ? 'N/A (dry-run)' : $stats['orders_failed']],
        ];

        // Agregar estadísticas de PENDING si hay datos
        if (!$isDryRun && ($stats['pending_updated'] > 0 || $stats['pending_reprocessed'] > 0)) {
            $rows[] = ['---', '---'];
            $rows[] = ['Pedidos PENDING actualizados', $stats['pending_updated']];
            $rows[] = ['Pedidos PENDING reprocesados', $stats['pending_reprocessed']];
        }

        $this->table(['Métrica', 'Cantidad'], $rows);

        // Errores
        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->error('⚠️  Errores encontrados:');
            foreach ($stats['errors'] as $error) {
                $this->error('   - ' . $error);
            }
        }

        // Mensaje final
        $this->newLine();
        if ($stats['orders_missing'] === 0 && $stats['pending_updated'] === 0) {
            $this->info('✅ Todos los pedidos están sincronizados');
        } elseif ($isDryRun) {
            if ($stats['orders_missing'] > 0) {
                $this->warn("⚠️  Se encontraron {$stats['orders_missing']} pedidos faltantes");
                $this->info('   Ejecuta sin --dry-run para procesarlos');
            }
        } else {
            $messages = [];
            if ($stats['orders_processed'] > 0) {
                $messages[] = "{$stats['orders_processed']} pedidos nuevos procesados";
            }
            if ($stats['pending_reprocessed'] > 0) {
                $messages[] = "{$stats['pending_reprocessed']} pedidos PENDING reprocesados";
            }
            if (!empty($messages)) {
                $this->info('✅ Sincronización completada: ' . implode(', ', $messages));
            }
        }
    }
}
