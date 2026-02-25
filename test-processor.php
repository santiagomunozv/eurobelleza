<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\ShopifyOrderProcessor;

$order = Order::find(7);

if (!$order) {
  echo "❌ Pedido no encontrado\n";
  exit(1);
}

echo "=================================\n";
echo "PRUEBA DE PROCESAMIENTO COMPLETO\n";
echo "=================================\n\n";

echo "Pedido: #{$order->shopify_order_number}\n";
echo "Estado actual: {$order->status->value}\n";
echo "Financial status: {$order->order_json['financial_status']}\n\n";

$processor = app(ShopifyOrderProcessor::class);

try {
  $result = $processor->process($order);

  if ($result) {
    echo "✅ Pedido procesado exitosamente\n\n";

    $order->refresh();
    echo "Estado final: {$order->status->value}\n";
    echo "Intentos: {$order->attempts}\n\n";

    echo "=== LOGS DEL PEDIDO ===\n";
    foreach ($order->logs as $log) {
      echo "[{$log->level->value}] {$log->message}\n";
    }

    echo "\n=== ARCHIVO GENERADO ===\n";
    $date = now()->format('Ymd');
    $filePath = "siesa/pedidos/{$date}/pedido-{$order->shopify_order_number}.txt";

    if (\Storage::disk('local')->exists($filePath)) {
      echo "✅ Archivo encontrado: storage/app/{$filePath}\n";
      echo "Tamaño: " . \Storage::disk('local')->size($filePath) . " bytes\n";
    } else {
      echo "❌ Archivo no encontrado\n";
    }
  } else {
    echo "⚠️  Pedido no procesado (condiciones no cumplidas)\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Trace: " . $e->getTraceAsString() . "\n";
  exit(1);
}
