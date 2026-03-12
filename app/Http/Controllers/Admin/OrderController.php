<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessShopifyOrder;
use App\Models\Order;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('shopify_order_number', 'like', "%{$search}%")
                    ->orWhere('shopify_order_id', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $logs = $order->logs()
            ->orderByDesc('created_at')
            ->paginate(25);

        $logCounts = $order->logs()
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        $summary = [
            'info' => (int) ($logCounts['info'] ?? 0),
            'warning' => (int) ($logCounts['warning'] ?? 0),
            'error' => (int) ($logCounts['error'] ?? 0),
        ];

        return view('admin.orders.show', compact('order', 'logs', 'summary'));
    }

    public function reprocess(
        Order $order,
        OrderLogService $orderLogService,
        OrderConfigurationValidator $configValidator,
        ShopifyApiClient $shopifyApiClient
    ): RedirectResponse
    {
        if ($order->status === OrderStatusEnum::COMPLETED) {
            return redirect()
                ->route('admin.orders.index')
                ->with('error', 'El pedido ya está completado y no requiere reproceso.');
        }

        try {
            if ($shopifyApiClient->needsTokenRefresh()) {
                $shopifyApiClient->refreshAccessToken();
            }

            $freshData = $shopifyApiClient->getOrderById($order->shopify_order_id);

            if (!$freshData) {
                return redirect()
                    ->route('admin.orders.index')
                    ->with('error', "No se pudo obtener el pedido #{$order->shopify_order_number} desde Shopify.");
            }

            $order->order_json = $freshData;
            $order->save();
        } catch (\Exception $e) {
            $orderLogService->logError($order, 'Error consultando pedido en Shopify para reproceso manual', [
                'requested_by_user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.orders.index')
                ->with('error', "No se pudo actualizar el pedido desde Shopify: {$e->getMessage()}");
        }

        $validation = $configValidator->validate($order->order_json);
        if (!$validation['valid']) {
            $orderLogService->logWarning($order, 'Reproceso manual bloqueado por configuración incompleta', [
                'requested_by_user_id' => auth()->id(),
                'errors' => $validation['errors'],
                'details' => $validation['details'],
            ]);

            return redirect()
                ->route('admin.orders.index')
                ->with('error', $validation['errors'][0] ?? 'No fue posible reprocesar el pedido por configuración incompleta.');
        }

        $previousStatus = $order->status->value;

        $order->update([
            'status' => 'pending',
            'error_message' => null,
            'processed_at' => null,
            'attempts' => 0,
        ]);

        $orderLogService->logInfo($order, 'Reproceso manual solicitado desde panel administrativo', [
            'requested_by_user_id' => auth()->id(),
            'previous_status' => $previousStatus,
            'new_status' => 'pending',
            'order_json_refreshed' => true,
        ]);

        ProcessShopifyOrder::dispatch($order->fresh());

        return redirect()
            ->route('admin.orders.index')
            ->with('success', "Pedido #{$order->shopify_order_number} enviado a reproceso.");
    }
}
