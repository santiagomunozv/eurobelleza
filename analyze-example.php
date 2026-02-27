<?php
$line = file_get_contents('.github/documentacion/planos/PVEUROBE.txt');
$line = explode("\n", $line)[0];

echo "Longitud total: " . strlen($line) . "\n\n";

$fields = [
    ['orden_compra', 1, 10],
    ['tipo_cliente', 11, 11],
    ['codigo_ean', 12, 31],
    ['codigo_cliente', 32, 44],
    ['sucursal', 45, 46],
    ['fecha_pedido', 47, 54],
    ['bodega', 55, 57],
    ['localizacion', 58, 59],
    ['tipo_busqueda', 60, 60],
    ['codigo_barras', 61, 75],
    ['codigo_item', 76, 90],
    ['extension_item', 91, 93],
    ['fecha_entrega', 94, 101],
    ['unidad_captura', 102, 104],
    ['cantidad', 105, 117],
    ['cantidad_u2', 118, 130],
    ['unidad_precio', 131, 131],
    ['lista_precio', 132, 134],
    ['lista_descuento', 135, 136],
    ['precio_unitario', 137, 148],
    ['descuento1', 149, 152],
    ['descuento2', 153, 156],
    ['detalle_movimiento', 157, 176],
    ['descripcion_item', 177, 216],
    ['punto_envio', 217, 220],
    ['observacion1', 221, 280],
    ['observacion2', 281, 340],
    ['desc_variable1', 341, 380],
    ['desc_variable2', 381, 420],
    ['desc_variable3', 421, 460],
    ['desc_variable4', 461, 500],
    ['codigo_vendedor', 501, 513],
    ['motivo', 514, 515],
    ['centro_costo', 516, 523],
    ['proyecto', 524, 533],
    ['condicion_pago', 534, 535],
    ['documento_alterno', 536, 543],
];

foreach ($fields as $field) {
    $name = $field[0];
    $start = $field[1] - 1;
    $end = $field[2] - 1;
    $length = $end - $start + 1;
    $value = substr($line, $start, $length);

    echo sprintf(
        "%-20s | Pos %3d-%3d (%2d chars) | '%s'\n",
        $name,
        $field[1],
        $field[2],
        $length,
        $value
    );
}
