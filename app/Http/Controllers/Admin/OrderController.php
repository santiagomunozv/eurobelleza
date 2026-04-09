<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessShopifyOrder;
use App\Models\Order;
use App\Models\SiesaPaymentGatewayMapping;
use App\Models\SiesaWarehouseMapping;
use App\Services\OrderConfigurationValidator;
use App\Services\OrderLogService;
use App\Services\Shopify\ShopifyApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Cargar todos los payment gateways para optimizar búsquedas
        $allPaymentGateways = SiesaPaymentGatewayMapping::all()->keyBy(function ($item) {
            return strtolower($item->payment_gateway_name);
        });

        // Cargar warehouses una sola vez para evitar N+1 queries
        $locationIds = [];
        foreach ($orders as $order) {
            $fulfillments = $order->order_json['fulfillments'] ?? [];
            $locationId = $fulfillments[0]['location_id'] ?? null;
            if ($locationId) {
                $locationIds[] = $locationId;
            }
        }

        $warehouseMappings = [];
        if (!empty($locationIds)) {
            $mappings = SiesaWarehouseMapping::whereIn('shopify_location_id', array_unique($locationIds))->get();
            foreach ($mappings as $mapping) {
                $warehouseMappings[$mapping->shopify_location_id] = $mapping->shopify_location_name;
            }
        }

        return view('admin.orders.index', compact('orders', 'warehouseMappings', 'allPaymentGateways'));
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
    ): RedirectResponse {
        if (in_array($order->status, [OrderStatusEnum::COMPLETED, OrderStatusEnum::RPA_PROCESSING], true)) {
            return redirect()
                ->route('admin.orders.index')
                ->with('error', $order->status === OrderStatusEnum::COMPLETED
                    ? 'El pedido ya está completado y no requiere reproceso.'
                    : 'El pedido está siendo procesado por el RPA y no admite reproceso manual.');
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

    public function export(Request $request): StreamedResponse
    {
        $query = Order::query();

        // Aplicar los mismos filtros que en index()
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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $statusLabels = [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'rpa_processing' => 'Procesando en RPA',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'sent_to_siesa' => 'Enviado a SIESA',
            'siesa_error' => 'Error SIESA',
        ];

        $financialStatusLabels = [
            'paid' => 'Pagado',
            'pending' => 'Pendiente',
            'authorized' => 'Autorizado',
            'partially_paid' => 'Parcial',
            'refunded' => 'Reembolsado',
            'voided' => 'Anulado',
            'partially_refunded' => 'Reemb. Parcial',
        ];

        $fileName = 'pedidos_' . now()->format('Y-m-d_His') . '.csv';

        // Cargar todos los payment gateways para optimizar búsquedas
        $allPaymentGateways = SiesaPaymentGatewayMapping::all()->keyBy(function ($item) {
            return strtolower($item->payment_gateway_name);
        });

        // Cargar warehouses una sola vez para evitar N+1 queries
        $locationIds = [];
        foreach ($orders as $order) {
            $fulfillments = $order->order_json['fulfillments'] ?? [];
            $locationId = $fulfillments[0]['location_id'] ?? null;
            if ($locationId) {
                $locationIds[] = $locationId;
            }
        }

        $warehouseMappings = [];
        if (!empty($locationIds)) {
            $mappings = SiesaWarehouseMapping::whereIn('shopify_location_id', array_unique($locationIds))->get();
            foreach ($mappings as $mapping) {
                $warehouseMappings[$mapping->shopify_location_id] = $mapping->shopify_location_name;
            }
        }

        return response()->streamDownload(function () use ($orders, $statusLabels, $financialStatusLabels, $warehouseMappings, $allPaymentGateways) {
            $handle = fopen('php://output', 'w');

            // BOM para UTF-8 (para que Excel reconozca tildes)
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Encabezados
            fputcsv($handle, [
                'Número Pedido',
                'ID Shopify',
                'Cliente Nombre',
                'Cliente Email',
                'Total (COP)',
                'Flete (COP)',
                'Bodega',
                'Estado Pago',
                'Método Pago',
                'Estado',
                'Mensaje Error',
                'Fecha',
                'Hora',
            ], ';');

            // Datos
            foreach ($orders as $order) {
                $financialStatus = $order->order_json['financial_status'] ?? 'N/A';
                $paymentGateways = $order->order_json['payment_gateway_names'] ?? [];
                $paymentMethod = !empty($paymentGateways) ? $paymentGateways[0] : 'N/A';

                // Si el payment gateway es "manual", buscar en tags usando el array precargado
                if (strtolower($paymentMethod) === 'manual') {
                    $tags = $order->order_json['tags'] ?? '';
                    if (!empty($tags)) {
                        $words = preg_split('/[\s,]+/', strtolower($tags));
                        foreach ($words as $word) {
                            if (strlen($word) < 3) {
                                continue;
                            }
                            // Buscar en el array precargado
                            foreach ($allPaymentGateways as $gatewayKey => $gatewayMapping) {
                                if (str_contains($gatewayKey, $word)) {
                                    $paymentMethod = $gatewayMapping->payment_gateway_name;
                                    break 2;
                                }
                            }
                        }
                    }
                }

                $shippingAmount = floatval($order->order_json['total_shipping_price_set']['shop_money']['amount'] ?? 0);

                // Obtener bodega desde el array precargado
                $warehouseName = '';
                $fulfillments = $order->order_json['fulfillments'] ?? [];
                $locationId = $fulfillments[0]['location_id'] ?? null;
                if ($locationId && isset($warehouseMappings[$locationId])) {
                    $warehouseName = $warehouseMappings[$locationId];
                }

                fputcsv($handle, [
                    $order->shopify_order_number,
                    $order->shopify_order_id,
                    $order->customer_name ?? '',
                    $order->customer_email ?? '',
                    $order->total_price,
                    $shippingAmount,
                    $warehouseName,
                    $financialStatusLabels[$financialStatus] ?? $financialStatus,
                    $paymentMethod,
                    $statusLabels[$order->status->value] ?? $order->status->value,
                    $order->error_message ?? '',
                    $order->created_at->format('d/m/Y'),
                    $order->created_at->format('H:i'),
                ], ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ]);
    }
}
