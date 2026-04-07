<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title-page">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="ui-page">
        <div class="ui-container space-y-5">
            <section class="ui-card p-5">
                <div>
                    <h3 class="ui-section-title">Resumen de pedidos de hoy</h3>
                </div>

                <div class="ui-kpi-grid">
                    <article class="ui-kpi-card">
                        <p class="ui-kpi-label">Total pedidos hoy</p>
                        <p class="ui-kpi-value">{{ number_format($totalToday) }}</p>
                    </article>
                    <article class="ui-kpi-card">
                        <p class="ui-kpi-label">Tasa de completados</p>
                        <p class="ui-kpi-value">{{ $completionRate }}%</p>
                    </article>
                    <article class="ui-kpi-card">
                        <p class="ui-kpi-label">Tasa de fallidos</p>
                        <p class="ui-kpi-value">{{ $failureRate }}%</p>
                    </article>
                </div>

                <div class="ui-stats-grid mt-4">
                    @foreach ($statsByStatus as $stat)
                        <article class="ui-stat-card {{ $stat['bg'] }} {{ $stat['border'] }}">
                            <p class="ui-stat-title {{ $stat['color'] }}">{{ $stat['label'] }}</p>
                            <p class="ui-stat-value">{{ number_format($stat['count']) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="ui-card overflow-hidden">
                <div class="border-b border-[var(--color-border)] px-5 py-3">
                    <h4 class="ui-section-title text-base">Últimos pedidos de hoy</h4>
                </div>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead class="ui-table-head">
                            <tr>
                                <th class="ui-table-th">Pedido</th>
                                <th class="ui-table-th">Cliente</th>
                                <th class="ui-table-th">Total</th>
                                <th class="ui-table-th">Estado</th>
                                <th class="ui-table-th">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="ui-table-body">
                            @forelse ($latestOrders as $order)
                                @php
                                    $statusClasses = [
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'processing' => 'bg-blue-100 text-blue-800',
                                        'rpa_processing' => 'bg-indigo-100 text-indigo-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'sent_to_siesa' => 'bg-purple-100 text-purple-800',
                                        'siesa_error' => 'bg-orange-100 text-orange-800',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Pendiente',
                                        'processing' => 'Procesando',
                                        'rpa_processing' => 'Procesando en RPA',
                                        'completed' => 'Completado',
                                        'failed' => 'Fallido',
                                        'sent_to_siesa' => 'Enviado a SIESA',
                                        'siesa_error' => 'Error SIESA',
                                    ];
                                @endphp
                                <tr>
                                    <td class="ui-table-td-strong">#{{ $order->shopify_order_number }}</td>
                                    <td class="ui-table-td">
                                        <p class="text-gray-800">{{ $order->customer_name ?: 'Sin nombre' }}</p>
                                        <p class="text-xs text-gray-500">{{ $order->customer_email ?: 'Sin correo' }}
                                        </p>
                                    </td>
                                    <td class="ui-table-td-strong">
                                        ${{ number_format($order->total_price, 0, '', '.') }}</td>
                                    <td class="ui-table-td">
                                        <span
                                            class="ui-badge {{ $statusClasses[$order->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $statusLabels[$order->status->value] ?? $order->status->value }}
                                        </span>
                                    </td>
                                    <td class="ui-table-td">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center ui-section-subtitle">
                                        No hay pedidos registrados hoy.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
