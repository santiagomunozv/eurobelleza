<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SiesaPaymentGatewayMapping;

class SiesaPaymentGatewayMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiesaPaymentGatewayMapping::create([
            'payment_gateway_name' => 'Addi Payment',
            'sucursal' => '02',
            'condicion_pago' => '30',
            'centro_costo' => '021001',
        ]);
    }
}
