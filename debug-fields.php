<?php

require __DIR__ . '/vendor/autoload.php';

use App\Helpers\SiesaFileStructure;

// Test de cada campo con caracteres UTF-8
$fields = [
    'orden_compra' => SiesaFileStructure::padRight('61146', 10),
    'tipo_cliente' => '2',
    'codigo_ean' => SiesaFileStructure::padRight('', 20),
    'compania' => SiesaFileStructure::padRight('2222222222222', 13),
    'tipo_documento' => '00',
    'numero_documento' => SiesaFileStructure::padRight('2026021700115', 13),
    'tipo_registro' => 'R',
    'bodega' => SiesaFileStructure::padRight('001', 15),
    'ubicacion' => SiesaFileStructure::padRight('15', 15),
    'codigo_item' => SiesaFileStructure::padRight('13801', 20),
    'codigo_barras' => SiesaFileStructure::padRight('', 13),
    'fecha' => '20260217',
    'unidad_medida' => 'UND',
    'cantidad' => SiesaFileStructure::formatQuantity(1),
    'costo' => SiesaFileStructure::formatPrice(0),
    'lista_precios' => '1999',
    'vendedor' => SiesaFileStructure::padRight('', 2),
    'precio' => SiesaFileStructure::formatPrice(48000),
    'porcentaje_descuento' => SiesaFileStructure::formatPercentage(0),
    'motivo_movimiento' => SiesaFileStructure::padRight('', 8),
    'observacion1' => SiesaFileStructure::truncate('PEDIDO SHOPIFY', 60),
    'observacion2' => SiesaFileStructure::truncate('Nombres: VERÓNICA TRUJILLO ROJAS - Cédula: 1117551897 - Dirección: Calle 7b # 1 Bis 21 Pueblito Huilense - Ciudad: Rivera - Departamento: Huila - Teléfono: 3133421967', 60),
    'observacion3' => SiesaFileStructure::padRight('', 60),
    'observacion4' => SiesaFileStructure::padRight('', 60),
    'observacion5' => SiesaFileStructure::padRight('', 60),
    'observacion6' => SiesaFileStructure::padRight('', 60),
    'reservado1' => SiesaFileStructure::padRight('', 8),
    'reservado2' => SiesaFileStructure::padRight('', 8),
    'reservado3' => SiesaFileStructure::padRight('', 8),
    'centro_costo' => SiesaFileStructure::padRight('', 15),
    'condicion_pago' => SiesaFileStructure::padRight('', 3),
    'fecha2' => SiesaFileStructure::padRight('', 8),
    'fecha3' => SiesaFileStructure::padRight('', 8),
    'documento_alterno' => SiesaFileStructure::padRight('', 8),
];

echo "=== DEBUG DE CAMPOS ===\n\n";

$totalLength = 0;
$totalMbLength = 0;
foreach ($fields as $name => $value) {
    $length = strlen($value);
    $mbLength = mb_strlen($value, 'UTF-8');
    $totalLength += $length;
    $totalMbLength += $mbLength;
    
    if ($length != $mbLength) {
        echo sprintf("%-25s | bytes: %3d | chars: %3d | DIFF: %d | Valor: '%s'\n", 
            $name, 
            $length, 
            $mbLength, 
            $length - $mbLength,
            $value
        );
    }
}

echo "\n=== TOTALES ===\n";
echo "Total bytes: $totalLength\n";
echo "Total chars: $totalMbLength\n";
echo "Diferencia: " . ($totalLength - $totalMbLength) . "\n";
echo "Esperado: 543\n";

// Construir la línea completa
$line = implode('', $fields);
echo "\n=== LÍNEA COMPLETA ===\n";
echo "Longitud bytes: " . strlen($line) . "\n";
echo "Longitud chars: " . mb_strlen($line, 'UTF-8') . "\n";
