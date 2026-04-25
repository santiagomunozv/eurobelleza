<?php

namespace Tests\Feature;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiesaRunResultProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_orders_completed_from_rpa_result_file(): void
    {
        Storage::fake('siesa_pedidos');
        Storage::fake('siesa_resultados');
        Storage::fake('siesa_errores');

        $order = $this->createOrder('3663', OrderStatusEnum::SENT_TO_SIESA);
        Storage::disk('siesa_pedidos')->put('00003663.PE0', 'contenido');

        Storage::disk('siesa_resultados')->put('run_20260406_0630.json', json_encode([
            'run_id' => '2026-04-06_0630',
            'files_attempted' => ['00003663.PE0'],
            'files_without_error' => ['00003663.PE0'],
            'files_with_error' => [],
            'fatal_error' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->artisan('siesa:check-errors')->assertExitCode(0);

        $order->refresh();

        $this->assertSame(OrderStatusEnum::COMPLETED, $order->status);
        $this->assertNotNull($order->processed_at);
        Storage::disk('siesa_resultados')->assertMissing('run_20260406_0630.json');
        Storage::disk('siesa_pedidos')->assertMissing('00003663.PE0');
    }

    public function test_it_marks_orders_with_siesa_error_from_rpa_result_file(): void
    {
        Storage::fake('siesa_pedidos');
        Storage::fake('siesa_resultados');
        Storage::fake('siesa_errores');

        $order = $this->createOrder('3664', OrderStatusEnum::SENT_TO_SIESA);
        Storage::disk('siesa_pedidos')->put('00003664.PE0', 'contenido');

        Storage::disk('siesa_resultados')->put('run_20260406_1300.json', json_encode([
            'run_id' => '2026-04-06_1300',
            'files_attempted' => ['00003664.PE0'],
            'files_without_error' => [],
            'files_with_error' => [
                [
                    'file' => '00003664.PE0',
                    'p99_key' => 'errores/20260406_1300_00003664.P99',
                    'errors' => ['Cliente inválido', 'Bodega sin configuración'],
                ],
            ],
            'fatal_error' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->artisan('siesa:check-errors')->assertExitCode(0);

        $order->refresh();

        $this->assertSame(OrderStatusEnum::SIESA_ERROR, $order->status);
        $this->assertSame('Cliente inválido | Bodega sin configuración', $order->error_message);
        $this->assertNull($order->processed_at);
        Storage::disk('siesa_resultados')->assertMissing('run_20260406_1300.json');
        Storage::disk('siesa_pedidos')->assertMissing('00003664.PE0');
    }

    public function test_it_marks_orders_completed_with_warning_from_rpa_result_file(): void
    {
        Storage::fake('siesa_pedidos');
        Storage::fake('siesa_resultados');
        Storage::fake('siesa_errores');

        $order = $this->createOrder('65304', OrderStatusEnum::SENT_TO_SIESA);
        Storage::disk('siesa_pedidos')->put('00065304.PE0', 'contenido');

        Storage::disk('siesa_resultados')->put('run_20260424_1822.json', json_encode([
            'run_id' => '2026-04-24_1822',
            'files_attempted' => ['00065304.PE0'],
            'files_without_error' => [],
            'files_with_warning' => [
                [
                    'file' => '00065304.PE0',
                    'p99_key' => 'errores/20260424_1822_00065304.P99',
                    'warnings' => ['[000023] ITEM LIQUIDADO CON OTRA LISTA PRECIO'],
                ],
            ],
            'files_with_error' => [],
            'fatal_error' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->artisan('siesa:check-errors')->assertExitCode(0);

        $order->refresh();

        $this->assertSame(OrderStatusEnum::COMPLETED, $order->status);
        $this->assertNotNull($order->processed_at);
        $this->assertTrue($order->logs()->where('message', 'rpa_run_completed_with_warning')->exists());
        Storage::disk('siesa_resultados')->assertMissing('run_20260424_1822.json');
        Storage::disk('siesa_pedidos')->assertMissing('00065304.PE0');
    }

    public function test_it_keeps_unresolved_rpa_results_from_becoming_completed(): void
    {
        Storage::fake('siesa_pedidos');
        Storage::fake('siesa_resultados');
        Storage::fake('siesa_errores');

        $order = $this->createOrder('65305', OrderStatusEnum::SENT_TO_SIESA);
        Storage::disk('siesa_pedidos')->put('00065305.PE0', 'contenido');

        Storage::disk('siesa_resultados')->put('run_20260424_1826.json', json_encode([
            'run_id' => '2026-04-24_1826',
            'files_attempted' => ['00065305.PE0'],
            'files_without_error' => [],
            'files_with_error' => [],
            'files_unresolved' => [
                [
                    'file' => '00065305.PE0',
                    'p99_key' => 'errores/20260424_1826_00065305.P99',
                    'reason' => 'P99 no parseable',
                ],
            ],
            'fatal_error' => null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->artisan('siesa:check-errors')->assertExitCode(0);

        $order->refresh();

        $this->assertSame(OrderStatusEnum::RPA_PROCESSING, $order->status);
        $this->assertNull($order->processed_at);
        $this->assertTrue($order->logs()->where('message', 'rpa_run_unresolved_result')->exists());
        Storage::disk('siesa_resultados')->assertMissing('run_20260424_1826.json');
        Storage::disk('siesa_pedidos')->assertMissing('00065305.PE0');
    }

    private function createOrder(string $orderNumber, OrderStatusEnum $status): Order
    {
        return Order::create([
            'shopify_order_id' => 'gid-' . $orderNumber,
            'shopify_order_number' => $orderNumber,
            'order_json' => [
                'id' => 'gid-' . $orderNumber,
                'order_number' => (int) $orderNumber,
                'financial_status' => 'paid',
                'customer' => [
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => 'test@example.com',
                ],
            ],
            'status' => $status,
            'attempts' => 0,
        ]);
    }
}
