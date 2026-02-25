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

      $this->orderRepository->updateStatus($order, OrderStatusEnum::COMPLETED);
      $this->logService->logSuccess($order, 'Pedido procesado exitosamente');

      return true;
    } catch (Exception $e) {
      $this->orderRepository->incrementAttempts($order);
      $this->orderRepository->updateStatus($order, OrderStatusEnum::FAILED);

      $this->logService->logError($order, 'Error al procesar pedido: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
      ]);

      throw $e;
    }
  }

  private function saveFile(Order $order, string $content): string
  {
    $date = now()->format('Ymd');
    $orderNumber = $order->shopify_order_number;

    $directory = "siesa/pedidos/{$date}";
    $filename = "pedido-{$orderNumber}.txt";
    $fullPath = "{$directory}/{$filename}";

    Storage::disk('local')->put($fullPath, $content);

    return $fullPath;
  }
}
