<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Pedidos') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="relative flex flex-col w-full h-full text-gray-700 bg-white shadow-md rounded-xl bg-clip-border">
                <!-- Header -->
                <div class="relative mx-4 mt-4 overflow-hidden text-gray-700 bg-white rounded-none bg-clip-border">
                    <div class="flex flex-col justify-between gap-8 mb-4 md:flex-row md:items-center">
                        <div>
                            <h5
                                class="block font-sans text-xl antialiased font-semibold leading-snug tracking-normal text-blue-gray-900">
                                Pedidos de Shopify
                            </h5>
                            <p
                                class="block mt-1 font-sans text-base antialiased font-normal leading-relaxed text-gray-700">
                                Gestión y seguimiento de pedidos sincronizados con SIESA
                            </p>
                        </div>
                        <div class="flex w-full gap-2 shrink-0 md:w-max">
                            <form method="GET" action="{{ route('admin.orders.index') }}" class="flex w-full gap-2">
                                <!-- Buscador -->
                                <div class="w-full md:w-72">
                                    <div class="relative h-10 w-full min-w-[200px]">
                                        <div
                                            class="absolute grid w-5 h-5 top-2/4 right-3 -translate-y-2/4 place-items-center text-blue-gray-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z">
                                                </path>
                                            </svg>
                                        </div>
                                        <input type="text" name="search" value="{{ request('search') }}"
                                            class="peer h-full w-full rounded-[7px] border border-blue-gray-200 bg-transparent px-3 py-2.5 !pr-9 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all placeholder-shown:border placeholder-shown:border-blue-gray-200 focus:border-2 focus:border-gray-900 focus:outline-0"
                                            placeholder="Buscar por número de pedido..." />
                                    </div>
                                </div>

                                <!-- Filtro de estado -->
                                <div class="w-full md:w-48">
                                    <select name="status"
                                        class="h-10 w-full rounded-[7px] border border-blue-gray-200 bg-transparent px-3 py-2 font-sans text-sm font-normal text-blue-gray-700 outline outline-0 transition-all focus:border-2 focus:border-gray-900 focus:outline-0">
                                        <option value="">Todos los estados</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>
                                            Pendiente</option>
                                        <option value="processing"
                                            {{ request('status') == 'processing' ? 'selected' : '' }}>Procesando
                                        </option>
                                        <option value="completed"
                                            {{ request('status') == 'completed' ? 'selected' : '' }}>Completado</option>
                                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>
                                            Fallido</option>
                                    </select>
                                </div>

                                <!-- Botón filtrar -->
                                <button type="submit"
                                    class="flex select-none items-center gap-3 rounded-lg bg-gray-900 py-2 px-4 text-center align-middle font-sans text-xs font-bold uppercase text-white shadow-md shadow-gray-900/10 transition-all hover:shadow-lg hover:shadow-gray-900/20 focus:opacity-[0.85] focus:shadow-none active:opacity-[0.85] active:shadow-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z">
                                        </path>
                                    </svg>
                                    Buscar
                                </button>

                                @if (request('search') || request('status'))
                                    <a href="{{ route('admin.orders.index') }}"
                                        class="flex select-none items-center gap-3 rounded-lg border border-gray-900 py-2 px-4 text-center align-middle font-sans text-xs font-bold uppercase text-gray-900 transition-all hover:opacity-75 focus:ring focus:ring-gray-300 active:opacity-[0.85]">
                                        Limpiar
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="p-6 px-0 overflow-scroll">
                    <table class="w-full text-left table-auto min-w-max">
                        <thead>
                            <tr>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                        Número de Pedido
                                    </p>
                                </th>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                        Cliente
                                    </p>
                                </th>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                        Total
                                    </p>
                                </th>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                        Estado
                                    </p>
                                </th>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                        Fecha
                                    </p>
                                </th>
                                <th class="p-4 border-y border-blue-gray-100 bg-blue-gray-50/50">
                                    <p
                                        class="block font-sans text-sm antialiased font-normal leading-none text-blue-gray-900 opacity-70">
                                    </p>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-amber-500/20 text-amber-900',
                                        'processing' => 'bg-blue-500/20 text-blue-900',
                                        'completed' => 'bg-green-500/20 text-green-900',
                                        'failed' => 'bg-red-500/20 text-red-900',
                                    ];
                                    $statusLabels = [
                                        'pending' => 'Pendiente',
                                        'processing' => 'Procesando',
                                        'completed' => 'Completado',
                                        'failed' => 'Fallido',
                                    ];
                                @endphp
                                <tr>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block font-sans text-sm antialiased font-bold leading-normal text-blue-gray-900">
                                            #{{ $order->shopify_order_number }}
                                        </p>
                                        <p
                                            class="block font-sans text-xs antialiased font-normal leading-normal text-blue-gray-900 opacity-70">
                                            ID: {{ $order->shopify_order_id }}
                                        </p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block font-sans text-sm antialiased font-normal leading-normal text-blue-gray-900">
                                            {{ $order->customer_name }}
                                        </p>
                                        <p
                                            class="block font-sans text-xs antialiased font-normal leading-normal text-blue-gray-900 opacity-70">
                                            {{ $order->customer_email }}
                                        </p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block font-sans text-sm antialiased font-bold leading-normal text-blue-gray-900">
                                            ${{ number_format($order->total_price, 0, '', '.') }}
                                        </p>
                                        <p
                                            class="block font-sans text-xs antialiased font-normal leading-normal text-blue-gray-900 opacity-70">
                                            COP
                                        </p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <div class="w-max">
                                            <div
                                                class="relative grid items-center px-2 py-1 font-sans text-xs font-bold uppercase rounded-md select-none whitespace-nowrap {{ $statusColors[$order->status->value] ?? 'bg-gray-500/20 text-gray-900' }}">
                                                <span>{{ $statusLabels[$order->status->value] ?? $order->status->value }}</span>
                                            </div>
                                        </div>
                                        @if ($order->error_message)
                                            <p
                                                class="block mt-1 font-sans text-xs antialiased font-normal leading-normal text-red-600">
                                                {{ Str::limit($order->error_message, 30) }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <p
                                            class="block font-sans text-sm antialiased font-normal leading-normal text-blue-gray-900">
                                            {{ $order->created_at->format('d/m/Y') }}
                                        </p>
                                        <p
                                            class="block font-sans text-xs antialiased font-normal leading-normal text-blue-gray-900 opacity-70">
                                            {{ $order->created_at->format('H:i') }}
                                        </p>
                                    </td>
                                    <td class="p-4 border-b border-blue-gray-50">
                                        <button
                                            class="relative h-10 max-h-[40px] w-10 max-w-[40px] select-none rounded-lg text-center align-middle font-sans text-xs font-medium uppercase text-gray-900 transition-all hover:bg-gray-900/10 active:bg-gray-900/20"
                                            type="button" title="Ver detalle">
                                            <span
                                                class="absolute transform -translate-x-1/2 -translate-y-1/2 top-1/2 left-1/2">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="currentColor" class="w-4 h-4">
                                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                                    <path fill-rule="evenodd"
                                                        d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center border-b border-blue-gray-50">
                                        <p
                                            class="block font-sans text-sm antialiased font-normal leading-normal text-blue-gray-900 opacity-70">
                                            No se encontraron pedidos
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="flex items-center justify-between p-4 border-t border-blue-gray-50">
                    <div class="text-sm text-gray-700">
                        Mostrando {{ $orders->firstItem() ?? 0 }} a {{ $orders->lastItem() ?? 0 }} de
                        {{ $orders->total() }} pedidos
                    </div>
                    <div>
                        {{ $orders->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
