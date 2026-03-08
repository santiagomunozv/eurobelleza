<?php

namespace Database\Seeders;

use App\Models\SiesaWarehouseMapping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiesaWarehouseMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'shopify_location_id' => 80414146731,
                'shopify_location_name' => 'BODEGA BARRANQUILLA',
                'bodega_code' => '001',
                'location_code' => '15',
            ],
            [
                'shopify_location_id' => 80414245035,
                'shopify_location_name' => 'BODEGA BOGOTÁ',
                'bodega_code' => '001',
                'location_code' => '15',
            ],
            [
                'shopify_location_id' => 80414113963,
                'shopify_location_name' => 'BODEGA CALI',
                'bodega_code' => '001',
                'location_code' => '15',
            ],
            [
                'shopify_location_id' => 80414081195,
                'shopify_location_name' => 'BODEGA SABANETA',
                'bodega_code' => '001',
                'location_code' => '15',
            ],
            [
                'shopify_location_id' => 63235489963,
                'shopify_location_name' => 'Eurobelleza - Arroyo Hondo',
                'bodega_code' => '001',
                'location_code' => '15',
            ],
        ];

        foreach ($warehouses as $warehouse) {
            SiesaWarehouseMapping::updateOrCreate(
                ['shopify_location_id' => $warehouse['shopify_location_id']],
                $warehouse
            );
        }
    }
}
