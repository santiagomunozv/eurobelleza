<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title-page">
            {{ __('Pedidos') }}
        </h2>
    </x-slot>

    @php
        $hasFilters = request('search') || request('status') || request('date_from') || request('date_to');
        $statusLabels = [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'rpa_processing' => 'Procesando en RPA',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'sent_to_siesa' => 'Enviado a SIESA',
            'siesa_error' => 'Error SIESA',
            'payment_expired' => 'Vencido',
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="ui-alert-success" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="ui-alert-error" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <section class="ui-card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex flex-col gap-1">
                        <h3 class="ui-section-title">Pedidos de Shopify</h3>
                        <p class="ui-section-subtitle">Gestión y seguimiento de pedidos sincronizados con SIESA.</p>
                    </div>
                    <a href="{{ route('admin.orders.export', request()->query()) }}"
                        class="ui-btn-primary inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                            <path fill-rule="evenodd"
                                d="M12 2.25a.75.75 0 01.75.75v11.69l3.22-3.22a.75.75 0 111.06 1.06l-4.5 4.5a.75.75 0 01-1.06 0l-4.5-4.5a.75.75 0 111.06-1.06l3.22 3.22V3a.75.75 0 01.75-.75zm-9 13.5a.75.75 0 01.75.75v2.25a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5V16.5a.75.75 0 011.5 0v2.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V16.5a.75.75 0 01.75-.75z"
                                clip-rule="evenodd" />
                        </svg>
                        Exportar a Excel
                    </a>
                </div>

                <form method="GET" action="{{ route('admin.orders.index') }}" class="ui-orders-filter-grid">
                    <div>
                        <label for="search" class="ui-label">Buscar pedido</label>
                        <input id="search" type="text" name="search" value="{{ request('search') }}"
                            class="ui-input" placeholder="Número de pedido o ID de Shopify">
                    </div>

                    <div>
                        <label for="status" class="ui-label">Estado</label>
                        <select id="status" name="status" class="ui-input">
                            <option value="">Todos los estados</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendiente
                            </option>
                            <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>
                                Procesando</option>
                            <option value="rpa_processing"
                                {{ request('status') == 'rpa_processing' ? 'selected' : '' }}>
                                Procesando en RPA</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>
                                Completado</option>
                            <option value="sent_to_siesa" {{ request('status') == 'sent_to_siesa' ? 'selected' : '' }}>
                                Enviado a SIESA</option>
                            <option value="siesa_error" {{ request('status') == 'siesa_error' ? 'selected' : '' }}>
                                Error
                                SIESA</option>
                            <option value="payment_expired" {{ request('status') == 'payment_expired' ? 'selected' : '' }}>
                                Vencido</option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Fallido
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="date_from" class="ui-label">Fecha desde</label>
                        <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}"
                            class="ui-input">
                    </div>

                    <div>
                        <label for="date_to" class="ui-label">Fecha hasta</label>
                        <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}"
                            class="ui-input">
                    </div>

                    <div class="ui-orders-filter-action">
                        <button type="submit" class="ui-btn-primary w-full md:w-auto">Filtrar</button>
                    </div>

                    <div class="ui-orders-filter-action">
                        <a href="{{ route('admin.orders.index') }}"
                            class="ui-btn-secondary w-full md:w-auto {{ $hasFilters ? '' : 'invisible' }}">
                            Limpiar
                        </a>
                    </div>
                </form>

                @if ($hasFilters)
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-medium text-[var(--color-text-muted)]">Filtros activos:</span>
                        @if (request('search'))
                            <span class="ui-badge bg-[var(--color-primary-soft)] text-[#1c4789]">
                                Búsqueda: {{ request('search') }}
                            </span>
                        @endif
                        @if (request('status'))
                            <span class="ui-badge bg-[var(--color-primary-soft)] text-[#1c4789]">
                                Estado: {{ $statusLabels[request('status')] ?? request('status') }}
                            </span>
                        @endif
                        @if (request('date_from'))
                            <span class="ui-badge bg-[var(--color-primary-soft)] text-[#1c4789]">
                                Desde: {{ request('date_from') }}
                            </span>
                        @endif
                        @if (request('date_to'))
                            <span class="ui-badge bg-[var(--color-primary-soft)] text-[#1c4789]">
                                Hasta: {{ request('date_to') }}
                            </span>
                        @endif
                    </div>
                @endif
            </section>

            <section class="ui-card overflow-hidden">
                <div class="border-b border-[var(--color-border)] px-5 py-3 ui-section-subtitle">
                    Mostrando {{ $orders->firstItem() ?? 0 }} a {{ $orders->lastItem() ?? 0 }} de
                    {{ $orders->total() }} pedidos
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table-compact">
                        <thead class="ui-table-compact-head">
                            <tr>
                                <th class="ui-table-compact-th">Pedido</th>
                                <th class="ui-table-compact-th">Cliente</th>
                                <th class="ui-table-compact-th">Total</th>
                                <th class="ui-table-compact-th">Flete</th>
                                <th class="ui-table-compact-th">Bodega</th>
                                <th class="ui-table-compact-th">Pago</th>
                                <th class="ui-table-compact-th">Método</th>
                                <th class="ui-table-compact-th">Estado</th>
                                <th class="ui-table-compact-th">Fecha</th>
                                <th class="ui-table-compact-th-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="ui-table-compact-body">
                            @forelse($orders as $order)
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'processing' => 'bg-blue-100 text-blue-800',
                                        'rpa_processing' => 'bg-indigo-100 text-indigo-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'sent_to_siesa' => 'bg-purple-100 text-purple-800',
                                        'siesa_error' => 'bg-orange-100 text-orange-800',
                                        'payment_expired' => 'bg-slate-100 text-slate-800',
                                    ];
                                    $financialStatus = $order->order_json['financial_status'] ?? 'N/A';
                                    $financialStatusColors = [
                                        'paid' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'authorized' => 'bg-blue-100 text-blue-800',
                                        'partially_paid' => 'bg-orange-100 text-orange-800',
                                        'refunded' => 'bg-purple-100 text-purple-800',
                                        'voided' => 'bg-gray-200 text-gray-800',
                                        'expired' => 'bg-slate-100 text-slate-800',
                                        'partially_refunded' => 'bg-purple-100 text-purple-800',
                                    ];
                                    $financialStatusLabels = [
                                        'paid' => 'Pagado',
                                        'pending' => 'Pendiente',
                                        'authorized' => 'Autorizado',
                                        'partially_paid' => 'Parcial',
                                        'refunded' => 'Reembolsado',
                                        'voided' => 'Anulado',
                                        'expired' => 'Vencido',
                                        'partially_refunded' => 'Reemb. Parcial',
                                    ];
                                    $paymentGateways = $order->order_json['payment_gateway_names'] ?? [];
                                    $firstPayment = !empty($paymentGateways) ? $paymentGateways[0] : 'N/A';

                                    // Si el payment gateway es "manual", buscar en tags usando el array precargado
                                    if (strtolower($firstPayment) === 'manual') {
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
                                                        $firstPayment = $gatewayMapping->payment_gateway_name;
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $shippingAmount = floatval(
                                        $order->order_json['total_shipping_price_set']['shop_money']['amount'] ?? 0,
                                    );

                                    // Obtener bodega desde el array precargado
                                    $warehouseName = '';
                                    $fulfillments = $order->order_json['fulfillments'] ?? [];
                                    $locationId = $fulfillments[0]['location_id'] ?? null;
                                    if ($locationId && isset($warehouseMappings[$locationId])) {
                                        $warehouseName = $warehouseMappings[$locationId];
                                    }
                                @endphp
                                <tr class="align-top">
                                    <td class="ui-table-compact-td">
                                        <p class="font-semibold text-[var(--color-text)]">
                                            #{{ $order->shopify_order_number }}</p>
                                        <p class="text-xs text-[var(--color-text-muted)]">ID:
                                            {{ $order->shopify_order_id }}</p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="font-medium text-[var(--color-text)]">{{ $order->customer_name }}</p>
                                        <p class="text-xs text-[var(--color-text-muted)]">{{ $order->customer_email }}
                                        </p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="font-semibold text-[var(--color-text)]">
                                            ${{ number_format($order->total_price, 0, '', '.') }}</p>
                                        <p class="text-xs text-[var(--color-text-muted)]">COP</p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="font-semibold text-[var(--color-text)]">
                                            ${{ number_format($shippingAmount, 0, '', '.') }}</p>
                                        <p class="text-xs text-[var(--color-text-muted)]">COP</p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="text-[var(--color-text)]">
                                            {{ $warehouseName ?: '-' }}</p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <span
                                            class="ui-badge {{ $financialStatusColors[$financialStatus] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $financialStatusLabels[$financialStatus] ?? $financialStatus }}
                                        </span>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="text-[var(--color-text)]">{{ $firstPayment }}</p>
                                        @if (count($paymentGateways) > 1)
                                            <p class="text-xs text-[var(--color-text-muted)]">
                                                +{{ count($paymentGateways) - 1 }} más</p>
                                        @endif
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <span
                                            class="ui-badge {{ $statusColors[$order->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusLabels[$order->status->value] ?? $order->status->value }}
                                        </span>
                                        @if ($order->error_message)
                                            <p class="mt-1 text-xs text-red-700">
                                                {{ Str::limit($order->error_message, 42) }}</p>
                                        @endif
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="text-[var(--color-text)]">{{ $order->created_at->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs text-[var(--color-text-muted)]">
                                            {{ $order->created_at->format('H:i') }}</p>
                                    </td>
                                    <td class="ui-table-compact-td-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.orders.show', $order) }}"
                                                title="Ver logs del pedido" class="ui-icon-btn">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="currentColor" class="h-4 w-4">
                                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                                    <path fill-rule="evenodd"
                                                        d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </a>

                                            @if (!in_array($order->status->value, ['completed', 'sent_to_siesa', 'rpa_processing']))
                                                <form method="POST"
                                                    action="{{ route('admin.orders.reprocess', $order) }}"
                                                    onsubmit="return confirm('¿Reprocesar este pedido ahora?');">
                                                    @csrf
                                                    <button type="submit" class="ui-icon-btn"
                                                        title="Reprocesar pedido" aria-label="Reprocesar pedido">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                            fill="currentColor" class="h-4 w-4">
                                                            <path fill-rule="evenodd"
                                                                d="M4.5 4.5a.75.75 0 011.06 0l1.72 1.72A8.25 8.25 0 112.25 12a.75.75 0 011.5 0 6.75 6.75 0 104.31-6.29l1.41 1.41a.75.75 0 11-1.06 1.06L4.5 5.56a.75.75 0 010-1.06z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center ui-section-subtitle">
                                        No se encontraron pedidos para el criterio actual.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-[var(--color-border)] p-4">
                    {{ $orders->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
