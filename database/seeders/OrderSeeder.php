<?php

namespace Database\Seeders;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $baseOrderJson = [
            "id" => 5398036938983,
            "name" => "#3663",
            "order_number" => 3663,
            "email" => "sago2506@gmail.com",
            "created_at" => "2023-07-27T13:42:18-05:00",
            "currency" => "COP",
            "current_total_price" => "780500.00",
            "financial_status" => "paid",
            "fulfillment_status" => null,
            "customer" => [
                "id" => 6929939857639,
                "email" => "sago2506@gmail.com",
                "first_name" => "Andres Santiago",
                "last_name" => "Muñoz Viana",
            ],
            "line_items" => [
                [
                    "id" => 13486231617767,
                    "name" => "30 Dias Liso, Antifrizz - 300gr",
                    "price" => "106000.00",
                    "quantity" => 4,
                    "sku" => "PTAADIA563",
                ],
                [
                    "id" => 13486231650535,
                    "name" => "A Single Shampoo - 250ml",
                    "price" => "149000.00",
                    "quantity" => 2,
                    "sku" => "DASHSG1074",
                ],
            ],
        ];

        Order::create([
            'shopify_order_id' => '5398036938983',
            'shopify_order_number' => '3663',
            'order_json' => $baseOrderJson,
            'status' => OrderStatusEnum::COMPLETED,
            'flat_file_name' => 'PEDIDO_3663.txt',
            'flat_file_path' => storage_path('app/siesa/pedidos/PEDIDO_3663.txt'),
            'processed_at' => now(),
        ]);

        Order::create([
            'shopify_order_id' => '5398036938984',
            'shopify_order_number' => '3664',
            'order_json' => array_merge($baseOrderJson, ['id' => 5398036938984, 'order_number' => 3664]),
            'status' => OrderStatusEnum::PENDING,
        ]);

        Order::create([
            'shopify_order_id' => '5398036938985',
            'shopify_order_number' => '3665',
            'order_json' => array_merge($baseOrderJson, ['id' => 5398036938985, 'order_number' => 3665]),
            'status' => OrderStatusEnum::PROCESSING,
        ]);

        Order::create([
            'shopify_order_id' => '5398036938986',
            'shopify_order_number' => '3666',
            'order_json' => array_merge($baseOrderJson, ['id' => 5398036938986, 'order_number' => 3666]),
            'status' => OrderStatusEnum::FAILED,
            'error_message' => 'Error al generar archivo plano: formato inválido',
            'attempts' => 3,
        ]);

        Order::create([
            'shopify_order_id' => '5398036938987',
            'shopify_order_number' => '3667',
            'order_json' => array_merge($baseOrderJson, ['id' => 5398036938987, 'order_number' => 3667]),
            'status' => OrderStatusEnum::RPA_PROCESSING,
        ]);
    }
}
