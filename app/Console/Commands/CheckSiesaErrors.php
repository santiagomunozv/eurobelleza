<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use App\Repositories\OrderRepository;
use App\Services\OrderLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CheckSiesaErrors extends Command
{
    protected $signature = 'siesa:check-errors
                            {--hours=1 : Horas sin error para marcar un pedido como completado}';

    protected $description = 'Revisa archivos P99 de errores en S3, actualiza estados y marca pedidos completados';

    public function __construct(
        private OrderRepository $orderRepository,
        private OrderLogService $logService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->procesarArchivosError();
        $this->completarPedidosEnEspera((int) $this->option('hours'));

        return self::SUCCESS;
    }

    private function procesarArchivosError(): void
    {
        $archivos = Storage::disk('siesa_errores')->files();
        $archivosP99 = array_filter($archivos, fn($f) => str_ends_with(strtoupper($f), '.P99'));

        if (empty($archivosP99)) {
            $this->info('No hay archivos de error en S3');
            return;
        }

        $this->info('Procesando ' . count($archivosP99) . ' archivo(s) de error');

        foreach ($archivosP99 as $clave) {
            $contenido = Storage::disk('siesa_errores')->get($clave);
            $erroresPorPedido = $this->parsearErroresBlocking($contenido);

            foreach ($erroresPorPedido as $numeroPedido => $lineasError) {
                $this->marcarPedidoConError($numeroPedido, $lineasError, $clave);
            }

            Storage::disk('siesa_errores')->delete($clave);
            $this->info("Procesado y eliminado de S3: {$clave}");

            Log::info('siesa:check-errors archivo procesado', [
                'archivo' => $clave,
                'pedidos_con_error' => count($erroresPorPedido),
            ]);
        }
    }

    private function parsearErroresBlocking(string $contenido): array
    {
        $erroresPorPedido = [];

        foreach (explode("\n", $contenido) as $linea) {
            // Líneas blocking: comienzan con espacio + 10 dígitos del pedido
            // Líneas con * al inicio son advertencias y no detienen el proceso
            if (!preg_match('/^ (\d{10})\s+(.+)$/', $linea, $matches)) {
                continue;
            }

            $numeroPedido = (string) (int) $matches[1];
            $mensajeError = trim($matches[2]);

            $erroresPorPedido[$numeroPedido][] = $mensajeError;
        }

        return $erroresPorPedido;
    }

    private function marcarPedidoConError(string $numeroPedido, array $lineasError, string $archivoP99): void
    {
        $order = Order::where('shopify_order_number', $numeroPedido)
            ->where('status', OrderStatusEnum::SENT_TO_SIESA->value)
            ->first();

        if (!$order) {
            $this->warn("Pedido #{$numeroPedido}: no encontrado con estado sent_to_siesa");
            return;
        }

        $mensajeError = implode(' | ', array_unique($lineasError));

        $this->orderRepository->updateStatus($order, OrderStatusEnum::SIESA_ERROR, $mensajeError);

        $this->logService->logError($order, 'siesa_error_detectado', [
            'archivo_p99' => $archivoP99,
            'errores' => $lineasError,
        ]);

        $this->info("Pedido #{$numeroPedido}: marcado como siesa_error");
    }

    private function completarPedidosEnEspera(int $hours): void
    {
        $limite = now()->subHours($hours);

        $pedidos = Order::where('status', OrderStatusEnum::SENT_TO_SIESA->value)
            ->where('processed_at', '<=', $limite)
            ->get();

        if ($pedidos->isEmpty()) {
            return;
        }

        $this->info("Completando {$pedidos->count()} pedido(s) sin error tras {$hours}h");

        foreach ($pedidos as $order) {
            $this->orderRepository->updateStatus($order, OrderStatusEnum::COMPLETED);

            $this->logService->logSuccess($order, 'siesa_pedido_completado_sin_error', [
                'hours_elapsed' => $hours,
            ]);
        }
    }
}
