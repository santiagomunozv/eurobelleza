<?php
// Verificar suma de todas las longitudes

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\SiesaFileStructure;

echo "=== VERIFICACIÓN DE LONGITUDES DE CONSTANTES ===\n\n";

$fields = [
  ['Orden compra', SiesaFileStructure::ORDEN_COMPRA_LENGTH, '1-10'],
  ['Tipo cliente', SiesaFileStructure::TIPO_CLIENTE_LENGTH, '11'],
  ['Código EAN', SiesaFileStructure::CODIGO_EAN_LENGTH, '12-31'],
  ['Código cliente', SiesaFileStructure::CODIGO_CLIENTE_LENGTH, '32-44'],
  ['Sucursal', SiesaFileStructure::SUCURSAL_LENGTH, '45-46'],
  ['Fecha pedido', SiesaFileStructure::FECHA_PEDIDO_LENGTH, '47-54'],
  ['Bodega', SiesaFileStructure::BODEGA_LENGTH, '55-57'],
  ['Localización', SiesaFileStructure::LOCALIZACION_LENGTH, '58-59'],
  ['Tipo ítem', SiesaFileStructure::TIPO_ITEM_LENGTH, '60'],
  ['Código barras', SiesaFileStructure::CODIGO_BARRAS_LENGTH, '61-75'],
  ['Código ítem', SiesaFileStructure::CODIGO_ITEM_LENGTH, '76-90'],
  ['Extensión', SiesaFileStructure::EXTENSION_ITEM_LENGTH, '91-93'],
  ['Fecha entrega', SiesaFileStructure::FECHA_ENTREGA_LENGTH, '94-101'],
  ['Unidad captura', SiesaFileStructure::UNIDAD_CAPTURA_LENGTH, '102-104'],
  ['Cantidad', SiesaFileStructure::CANTIDAD_LENGTH, '105-117'],
  ['Cantidad U2', SiesaFileStructure::CANTIDAD_UNIDAD2_LENGTH, '118-130'],
  ['Unidad precio', SiesaFileStructure::UNIDAD_PRECIO_LENGTH, '131'],
  ['Lista precio', SiesaFileStructure::LISTA_PRECIO_LENGTH, '132-134'],
  ['Lista descuento', SiesaFileStructure::LISTA_DESCUENTO_LENGTH, '135-136'],
  ['Precio unitario', SiesaFileStructure::PRECIO_UNITARIO_LENGTH, '137-148'],
  ['Descuento 1', SiesaFileStructure::DESCUENTO1_LENGTH, '149-152'],
  ['Descuento 2', SiesaFileStructure::DESCUENTO2_LENGTH, '153-156'],
  ['Detalle', SiesaFileStructure::DETALLE_MOVIMIENTO_LENGTH, '157-176'],
  ['Desc ítem', SiesaFileStructure::DESCRIPCION_ITEM_LENGTH, '177-216'],
  ['Punto envío', SiesaFileStructure::PUNTO_ENVIO_LENGTH, '217-220'],
  ['Observación 1', SiesaFileStructure::OBSERVACION1_LENGTH, '221-280'],
  ['Observación 2', SiesaFileStructure::OBSERVACION2_LENGTH, '281-340'],
  ['Desc var 1', SiesaFileStructure::DESC_VARIABLE1_LENGTH, '341-380'],
  ['Desc var 2', SiesaFileStructure::DESC_VARIABLE2_LENGTH, '381-420'],
  ['Desc var 3', SiesaFileStructure::DESC_VARIABLE3_LENGTH, '421-460'],
  ['Desc var 4', SiesaFileStructure::DESC_VARIABLE4_LENGTH, '461-500'],
  ['Vendedor', SiesaFileStructure::CODIGO_VENDEDOR_LENGTH, '501-513'],
  ['Motivo', SiesaFileStructure::MOTIVO_LENGTH, '514-515'],
  ['Centro costo', SiesaFileStructure::CENTRO_COSTO_LENGTH, '516-523'],
  ['Proyecto', SiesaFileStructure::PROYECTO_LENGTH, '524-533'],
  ['Cond pago', SiesaFileStructure::CONDICION_PAGO_LENGTH, '534-535'],
  ['Doc alterno', SiesaFileStructure::DOCUMENTO_ALTERNO_LENGTH, '536-543'],
];

$total = 0;
foreach ($fields as $field) {
  list($name, $length, $positions) = $field;
  $total += $length;
  printf("%-20s | Pos: %-10s | Long: %3d | Acum: %3d\n", $name, $positions, $length, $total);
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "TOTAL: $total caracteres\n";
echo "ESPERADO: 543 caracteres\n";
echo "DIFERENCIA: " . ($total - 543) . " caracteres\n";
echo str_repeat("=", 60) . "\n";
