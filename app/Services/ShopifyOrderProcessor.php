<?php

namespace App\Services;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use App\Services\Siesa\SiesaFlatFileGenerator;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Storage;
use Exception;

class ShopifyOrderProcessor
{
    public function __construct(
        private SiesaFlatFileGenerator $fileGenerator,
        private OrderLogService $logService,
        private OrderRepository $orderRepository
    ) {}

    public function process(Order $order): bool
    {
        $this->logService->logInfo($order, 'Iniciando procesamiento de pedido', [
            'shopify_order_id' => $order->shopify_order_id,
            'order_number' => $order->shopify_order_number,
        ]);

        if ($order->status !== OrderStatusEnum::PENDING) {
            $this->logService->logWarning($order, 'Pedido no está en estado PENDING, se omite procesamiento', [
                'current_status' => $order->status->value,
            ]);
            return false;
        }

        $financialStatus = $order->order_json['financial_status'] ?? null;
        if ($financialStatus !== 'paid') {
            $this->logService->logWarning($order, 'Pedido no está pagado, se omite procesamiento', [
                'financial_status' => $financialStatus,
            ]);
            return false;
        }

        $this->orderRepository->updateStatus($order, OrderStatusEnum::PROCESSING);
        $this->logService->logInfo($order, 'Estado actualizado a PROCESSING');

        try {
            $fileContent = $this->fileGenerator->generate($order);

            $this->logService->logInfo($order, 'Archivo SIESA generado exitosamente', [
                'lines' => count(explode("\n", $fileContent)),
                'size_bytes' => strlen($fileContent),
            ]);

            $filePath = $this->saveFile($order, $fileContent);

            $this->logService->logSuccess($order, 'Archivo guardado en disco', [
                'file_path' => $filePath,
            ]);

            $s3Path = $this->uploadToS3($order, $fileContent);

            $this->logService->logSuccess($order, 'Archivo PE0 subido a S3', [
                's3_path' => $s3Path,
            ]);

            $this->orderRepository->updateStatus($order, OrderStatusEnum::SENT_TO_SIESA);
            $this->logService->logSuccess($order, 'Pedido enviado a SIESA exitosamente');

            return true;
        } catch (Exception $e) {
            $this->orderRepository->incrementAttempts($order);
            $this->orderRepository->updateStatus($order, OrderStatusEnum::FAILED);

            $this->logService->logError($order, 'Error al procesar pedido: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    private function saveFile(Order $order, string $content): string
    {
        $date = now()->format('Ymd');
        $orderNumber = str_pad($order->shopify_order_number, 8, '0', STR_PAD_LEFT);

        $directory = "siesa/pedidos/{$date}";

        $filenamePE0 = "{$orderNumber}.PE0";
        $fullPathPE0 = "{$directory}/{$filenamePE0}";
        Storage::disk('local')->put($fullPathPE0, $content);

        $filenameTXT = "{$orderNumber}.txt";
        $fullPathTXT = "{$directory}/{$filenameTXT}";
        Storage::disk('local')->put($fullPathTXT, $content);

        return $fullPathPE0;
    }

    private function uploadToS3(Order $order, string $content): string
    {
        $orderNumber = str_pad($order->shopify_order_number, 8, '0', STR_PAD_LEFT);

        // Estructura plana en S3: {numero}.PE0
        // S3 es solo canal de transferencia, el histórico queda en local con carpetas por fecha
        $s3Path = "{$orderNumber}.PE0";

        Storage::disk('siesa_pedidos')->put($s3Path, $content);

        return $s3Path;
    }
}
