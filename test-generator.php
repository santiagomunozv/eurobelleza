<?php

// Script temporal para probar el generador de archivos SIESA

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Services\Siesa\SiesaFlatFileGenerator;

$order = Order::find(7);

if (!$order) {
  echo "Pedido no encontrado\n";
  exit(1);
}

echo "=================================\n";
echo "PRUEBA DE GENERADOR DE ARCHIVOS SIESA\n";
echo "=================================\n\n";

echo "Pedido: #{$order->shopify_order_number}\n";
echo "Fecha: {$order->created_at}\n";
echo "Productos: " . count($order->order_json['line_items']) . "\n\n";

$generator = $app->make(SiesaFlatFileGenerator::class);

try {
  $content = $generator->generate($order);

  echo "✓ Archivo generado exitosamente!\n\n";

  $lines = explode("\n", $content);
  echo "Total de líneas: " . count($lines) . "\n";

  foreach ($lines as $index => $line) {
    echo "Línea " . ($index + 1) . " - Longitud: " . strlen($line) . " caracteres\n";
  }

  echo "\n=== CONTENIDO DEL ARCHIVO ===\n\n";
  echo $content;
  echo "\n\n=== FIN DEL ARCHIVO ===\n\n";

  // Verificar longitud de cada línea
  echo "=== VERIFICACIÓN DE LONGITUDES ===\n";
  foreach ($lines as $index => $line) {
    $length = strlen($line);
    $status = $length === 543 ? "✓ OK" : "✗ ERROR";
    echo "Línea " . ($index + 1) . ": {$length} caracteres {$status}\n";
  }
} catch (Exception $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
  echo "Trace: " . $e->getTraceAsString() . "\n";
  exit(1);
}
