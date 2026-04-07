<?php

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SiesaGeneralConfigurationController;
use App\Http\Controllers\Admin\SiesaPaymentGatewayMappingController;
use App\Http\Controllers\Admin\SiesaWarehouseMappingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', '/login');

Route::get('/dashboard', function () {
    $todayStart = now()->startOfDay();
    $todayEnd = now()->endOfDay();

    $statusCounts = Order::query()
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

    $statusMeta = [
        OrderStatusEnum::PENDING->value => ['label' => 'Pendientes', 'color' => 'text-amber-700', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200'],
        OrderStatusEnum::PROCESSING->value => ['label' => 'Procesando', 'color' => 'text-blue-700', 'bg' => 'bg-blue-50', 'border' => 'border-blue-200'],
        OrderStatusEnum::RPA_PROCESSING->value => ['label' => 'Procesando en RPA', 'color' => 'text-indigo-700', 'bg' => 'bg-indigo-50', 'border' => 'border-indigo-200'],
        OrderStatusEnum::COMPLETED->value => ['label' => 'Completados', 'color' => 'text-green-700', 'bg' => 'bg-green-50', 'border' => 'border-green-200'],
        OrderStatusEnum::FAILED->value => ['label' => 'Fallidos', 'color' => 'text-red-700', 'bg' => 'bg-red-50', 'border' => 'border-red-200'],
        OrderStatusEnum::SENT_TO_SIESA->value => ['label' => 'Enviados a SIESA', 'color' => 'text-purple-700', 'bg' => 'bg-purple-50', 'border' => 'border-purple-200'],
        OrderStatusEnum::SIESA_ERROR->value => ['label' => 'Error SIESA', 'color' => 'text-orange-700', 'bg' => 'bg-orange-50', 'border' => 'border-orange-200'],
    ];

    $statsByStatus = collect(OrderStatusEnum::cases())
        ->map(function (OrderStatusEnum $status) use ($statusCounts, $statusMeta) {
            return [
                'status' => $status->value,
                'label' => $statusMeta[$status->value]['label'],
                'count' => (int) ($statusCounts[$status->value] ?? 0),
                'color' => $statusMeta[$status->value]['color'],
                'bg' => $statusMeta[$status->value]['bg'],
                'border' => $statusMeta[$status->value]['border'],
            ];
        });

    $totalToday = $statsByStatus->sum('count');
    $completedToday = (int) ($statusCounts[OrderStatusEnum::COMPLETED->value] ?? 0);
    $failedToday = (int) ($statusCounts[OrderStatusEnum::FAILED->value] ?? 0);

    $completionRate = $totalToday > 0 ? round(($completedToday / $totalToday) * 100, 1) : 0;
    $failureRate = $totalToday > 0 ? round(($failedToday / $totalToday) * 100, 1) : 0;

    $latestOrders = Order::query()
        ->whereBetween('created_at', [$todayStart, $todayEnd])
        ->latest('created_at')
        ->limit(8)
        ->get();

    return view('dashboard', compact(
        'statsByStatus',
        'totalToday',
        'completedToday',
        'failedToday',
        'completionRate',
        'failureRate',
        'latestOrders'
    ));
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/reprocess', [OrderController::class, 'reprocess'])->name('orders.reprocess');

    // Configuración general de SIESA
    Route::get('/siesa/configuration', [SiesaGeneralConfigurationController::class, 'edit'])
        ->name('siesa.configuration.edit');
    Route::put('/siesa/configuration', [SiesaGeneralConfigurationController::class, 'update'])
        ->name('siesa.configuration.update');

    // Métodos de pago SIESA
    Route::resource('/siesa/payment-gateways', SiesaPaymentGatewayMappingController::class)
        ->except(['show'])
        ->names('siesa.payment-gateways');

    // Ubicaciones de bodega SIESA
    Route::resource('/siesa/warehouses', SiesaWarehouseMappingController::class)
        ->except(['show'])
        ->names('siesa.warehouses');
});

require __DIR__ . '/auth.php';
