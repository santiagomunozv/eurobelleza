<?php

namespace App\Console\Commands;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\OrderLogService;
use App\Services\Siesa\SiesaRunResultProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckSiesaErrors extends Command
{
    protected $signature = 'siesa:process-rpa-results';

    protected $description = 'Procesa resultados de corridas RPA y archivos P99 de Siesa en S3';

    public function __construct(
        private OrderRepository $orderRepository,
        private OrderLogService $logService,
        private SiesaRunResultProcessor $runResultProcessor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $resultStats = $this->procesarResultadosRpa();
        $errorStats = $this->procesarArchivosErrorLegados();

        $this->table(
            ['Fuente', 'Procesados', 'Con error', 'Notas'],
            [
                [
                    'resultados/',
                    $resultStats['processed_files'],
                    $resultStats['failed_orders'],
                    $resultStats['notes'],
                ],
                [
                    'errores/',
                    $errorStats['processed_files'],
                    $errorStats['failed_orders'],
                    $errorStats['notes'],
                ],
            ]
        );

        return self::SUCCESS;
    }

    private function procesarResultadosRpa(): array
    {
        $processedFiles = 0;
        $failedOrders = 0;
        $notes = [];

        $resultFiles = collect(Storage::disk('siesa_resultados')->files())
            ->filter(fn($path) => str_ends_with(strtolower($path), '.json'))
            ->sort()
            ->values();

        if ($resultFiles->isEmpty()) {
            return [
                'processed_files' => 0,
                'failed_orders' => 0,
                'notes' => 'Sin resultados pendientes',
            ];
        }

        foreach ($resultFiles as $path) {
            try {
                $content = Storage::disk('siesa_resultados')->get($path);
                $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
                $summary = $this->runResultProcessor->process($path, $payload);

                Storage::disk('siesa_resultados')->delete($path);

                $processedFiles++;
                $failedOrders += $summary['failed'];

                $note = "run_id={$summary['run_id']} pendientes_p97={$summary['awaiting_confirmation']} con_error={$summary['failed']}";
                if (($summary['warnings'] ?? 0) > 0) {
                    $note .= " con_advertencia={$summary['warnings']}";
                }
                if (($summary['unresolved'] ?? 0) > 0) {
                    $note .= " no_resueltos={$summary['unresolved']}";
                }
                if (($summary['skipped_completed'] ?? 0) > 0) {
                    $note .= " ignorados_completed={$summary['skipped_completed']}";
                }
                if (!empty($summary['missing_files'])) {
                    $note .= ' faltantes=' . implode(',', $summary['missing_files']);
                }
                if (!empty($summary['fatal_error'])) {
                    $note .= ' fatal_error';
                }
                $notes[] = $note;
            } catch (\Throwable $e) {
                Log::error('No se pudo procesar el resultado RPA', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
                $notes[] = "fallo {$path}";
            }
        }

        return [
            'processed_files' => $processedFiles,
            'failed_orders' => $failedOrders,
            'notes' => implode(' | ', $notes),
        ];
    }

    private function procesarArchivosErrorLegados(): array
    {
        $processedFiles = 0;
        $failedOrders = 0;
        $notes = [];

        $files = Storage::disk('siesa_errores')->files();
        $legacyP99Files = array_values(array_filter($files, fn($file) => str_ends_with(strtoupper($file), '.P99')));

        if (empty($legacyP99Files)) {
            return [
                'processed_files' => 0,
                'failed_orders' => 0,
                'notes' => 'Sin P99 pendientes',
            ];
        }

        foreach ($legacyP99Files as $path) {
            $contenido = Storage::disk('siesa_errores')->get($path);
            $resultadoP99 = $this->parsearP99Legado($contenido);

            foreach ($resultadoP99['errors'] as $numeroPedido => $lineasError) {
                if ($this->marcarPedidoConError($numeroPedido, $lineasError, $path)) {
                    $failedOrders++;
                }
            }

            foreach ($resultadoP99['warnings'] as $numeroPedido => $lineasAdvertencia) {
                if (!isset($resultadoP99['errors'][$numeroPedido])) {
                    $this->marcarPedidoConAdvertencia($numeroPedido, $lineasAdvertencia, $path);
                }
            }

            Storage::disk('siesa_errores')->delete($path);
            $processedFiles++;
            $notes[] = basename($path);
        }

        return [
            'processed_files' => $processedFiles,
            'failed_orders' => $failedOrders,
            'notes' => implode(', ', $notes),
        ];
    }

    private function parsearP99Legado(string $contenido): array
    {
        $contenido = $this->normalizarContenidoP99($contenido);

        $resultado = [
            'errors' => [],
            'warnings' => [],
        ];

        foreach (explode("\n", $contenido) as $linea) {
            if (!preg_match('/^(\*?)\s*(\d{10})\s+(.+)$/', $linea, $matches)) {
                continue;
            }

            $isWarning = $matches[1] === '*';
            $numeroPedido = (string) (int) $matches[2];
            $mensaje = trim($matches[3]);

            if ($isWarning) {
                $resultado['warnings'][$numeroPedido][] = $mensaje;
            } else {
                $resultado['errors'][$numeroPedido][] = $mensaje;
            }
        }

        return $resultado;
    }

    private function normalizarContenidoP99(string $contenido): string
    {
        if (mb_check_encoding($contenido, 'UTF-8')) {
            return $contenido;
        }

        $contenidoConvertido = @mb_convert_encoding($contenido, 'UTF-8', 'CP850');

        if ($contenidoConvertido !== false && mb_check_encoding($contenidoConvertido, 'UTF-8')) {
            return $contenidoConvertido;
        }

        return mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
    }

    private function marcarPedidoConError(string $numeroPedido, array $lineasError, string $archivoP99): bool
    {
        $order = Order::where('shopify_order_number', $numeroPedido)
            ->whereIn('status', [
                OrderStatusEnum::SENT_TO_SIESA->value,
                OrderStatusEnum::RPA_PROCESSING->value,
            ])
            ->first();

        if (!$order) {
            $this->warn("Pedido #{$numeroPedido}: no encontrado en estado sent_to_siesa/rpa_processing");
            return false;
        }

        $mensajeError = implode(' | ', array_unique($lineasError));

        if ($this->esErrorPedidoDuplicado($lineasError)) {
            $this->orderRepository->updateStatus($order, OrderStatusEnum::RPA_PROCESSING, $mensajeError);

            $this->logService->logWarning($order, 'siesa_duplicate_awaiting_p97_desde_p99', [
                'archivo_p99' => $archivoP99,
                'errores' => $lineasError,
            ]);

            $this->info("Pedido #{$numeroPedido}: duplicado en Siesa; queda esperando P97");

            return false;
        }

        $this->orderRepository->updateStatus($order, OrderStatusEnum::SIESA_ERROR, $mensajeError);

        $this->logService->logError($order, 'siesa_error_detectado_desde_p99', [
            'archivo_p99' => $archivoP99,
            'errores' => $lineasError,
        ]);

        $this->info("Pedido #{$numeroPedido}: marcado como siesa_error");

        return true;
    }

    private function esErrorPedidoDuplicado(array $lineasError): bool
    {
        foreach ($lineasError as $linea) {
            if (str_contains(mb_strtoupper($linea), 'CLIENTE YA TIENE ESTE PEDIDO')) {
                return true;
            }
        }

        return false;
    }

    private function marcarPedidoConAdvertencia(string $numeroPedido, array $lineasAdvertencia, string $archivoP99): bool
    {
        $order = Order::where('shopify_order_number', $numeroPedido)
            ->whereIn('status', [
                OrderStatusEnum::SENT_TO_SIESA->value,
                OrderStatusEnum::RPA_PROCESSING->value,
            ])
            ->first();

        if (!$order) {
            $this->warn("Pedido #{$numeroPedido}: advertencia P99 ignorada; no encontrado en estado sent_to_siesa/rpa_processing");
            return false;
        }

        $mensajeAdvertencia = implode(' | ', array_unique($lineasAdvertencia));

        $this->orderRepository->updateStatus($order, OrderStatusEnum::RPA_PROCESSING, $mensajeAdvertencia);

        $this->logService->logWarning($order, 'siesa_warning_detectada_desde_p99', [
            'archivo_p99' => $archivoP99,
            'advertencias' => $lineasAdvertencia,
        ]);

        $this->info("Pedido #{$numeroPedido}: marcado como rpa_processing por advertencia P99");

        return true;
    }
}
