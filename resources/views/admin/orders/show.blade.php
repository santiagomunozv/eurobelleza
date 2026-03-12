<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="ui-title-page">Pedido #{{ $order->shopify_order_number }}</h2>
                <p class="ui-section-subtitle mt-1">Resumen de logs y trazabilidad del pedido.</p>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="ui-btn-secondary">Volver a pedidos</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="ui-card p-5">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <p class="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">Shopify ID</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--color-text)]">{{ $order->shopify_order_id }}</p>
                    </div>
                    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <p class="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">Estado pedido</p>
                        <p class="mt-1 text-sm font-semibold text-[var(--color-text)]">{{ ucfirst($order->status->value) }}</p>
                    </div>
                    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <p class="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">Logs info</p>
                        <p class="mt-1 text-lg font-semibold text-blue-700">{{ $summary['info'] }}</p>
                    </div>
                    <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                        <p class="text-xs uppercase tracking-wide text-[var(--color-text-muted)]">Logs warning / error</p>
                        <p class="mt-1 text-lg font-semibold text-amber-700">{{ $summary['warning'] }} / <span class="text-red-700">{{ $summary['error'] }}</span></p>
                    </div>
                </div>
            </section>

            <section class="ui-card overflow-hidden">
                <div class="border-b border-[var(--color-border)] px-5 py-3 ui-section-subtitle">
                    Mostrando {{ $logs->firstItem() ?? 0 }} a {{ $logs->lastItem() ?? 0 }} de {{ $logs->total() }} logs
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table-compact">
                        <thead class="ui-table-compact-head">
                            <tr>
                                <th class="ui-table-compact-th">Fecha</th>
                                <th class="ui-table-compact-th">Nivel</th>
                                <th class="ui-table-compact-th">Mensaje</th>
                                <th class="ui-table-compact-th">Contexto</th>
                            </tr>
                        </thead>
                        <tbody class="ui-table-compact-body">
                            @forelse($logs as $log)
                                <tr class="align-top">
                                    <td class="ui-table-compact-td whitespace-nowrap">
                                        {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                    </td>
                                    <td class="ui-table-compact-td">
                                        @php
                                            $levelClasses = match ($log->level?->value) {
                                                'error' => 'bg-red-100 text-red-800',
                                                'warning' => 'bg-amber-100 text-amber-800',
                                                default => 'bg-blue-100 text-blue-800',
                                            };
                                        @endphp
                                        <span class="ui-badge {{ $levelClasses }}">
                                            {{ strtoupper($log->level?->value ?? 'info') }}
                                        </span>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        <p class="text-sm text-[var(--color-text)]">{{ $log->message }}</p>
                                    </td>
                                    <td class="ui-table-compact-td">
                                        @if (!empty($log->context))
                                            <pre class="max-w-xl overflow-auto whitespace-pre-wrap rounded-md bg-slate-50 p-2 text-xs text-slate-700">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @else
                                            <span class="text-xs text-[var(--color-text-muted)]">Sin contexto</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center ui-section-subtitle">
                                        Este pedido no tiene logs registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-[var(--color-border)] p-4">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
