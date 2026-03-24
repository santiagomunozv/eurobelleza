<?php

namespace Database\Seeders;

use App\Models\SiesaGeneralConfiguration;
use App\Enums\Siesa\TipoClienteEnum;
use App\Enums\Siesa\TipoBusquedaItemEnum;
use App\Enums\Siesa\UnidadPrecioEnum;
use Illuminate\Database\Seeder;

class SiesaGeneralConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiesaGeneralConfiguration::create([
            'codigo_cliente' => '222222222222',
            'codigo_vendedor' => '16746504',
            'detalle_movimiento' => 'PEDIDO SHOPIFY',
            'motivo' => '01',
            'motivo_obsequio' => '02',
            'lista_precio' => '012',
            'lista_precio_flete' => '999',
            'lista_precio_obsequio' => '013',
            'unidad_captura' => 'UND',
            'tipo_cliente' => TipoClienteEnum::TERCERO,
            'tipo_busqueda_item' => TipoBusquedaItemEnum::REFERENCIA,
            'unidad_precio' => UnidadPrecioEnum::INVENTARIO,
        ]);
    }
}
