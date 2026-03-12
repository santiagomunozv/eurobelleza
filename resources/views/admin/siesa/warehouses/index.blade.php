<x-app-layout>
    <x-slot name="header">
        <div class="ui-header-row">
            <h2 class="ui-title-page">
                {{ __('Ubicaciones de Bodega - SIESA') }}
            </h2>
            <a href="{{ route('admin.siesa.warehouses.create') }}"
                class="ui-btn-primary">
                Agregar Ubicación
            </a>
        </div>
    </x-slot>

    <div class="ui-page-lg">
        <div class="ui-container">
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

            <div class="ui-card overflow-hidden">
                <div class="ui-card-body">
                    @if ($mappings->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 mb-4">No hay ubicaciones de bodega configuradas.</p>
                            <a href="{{ route('admin.siesa.warehouses.create') }}"
                                class="ui-btn-primary">
                                Agregar la Primera
                            </a>
                        </div>
                    @else
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead class="ui-table-head">
                                    <tr>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Shopify Location ID
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Nombre Ubicación
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Código Bodega
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Código Localización
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th-right">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="ui-table-body">
                                    @foreach ($mappings as $mapping)
                                        <tr>
                                            <td class="ui-table-td-strong">
                                                {{ $mapping->shopify_location_id }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->shopify_location_name ?? '-' }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->bodega_code }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->location_code ?? '-' }}
                                            </td>
                                            <td class="ui-table-td text-right font-medium">
                                                <a href="{{ route('admin.siesa.warehouses.edit', $mapping) }}"
                                                    class="ui-action-link mr-3">
                                                    Editar
                                                </a>
                                                <form action="{{ route('admin.siesa.warehouses.destroy', $mapping) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('¿Estás seguro de eliminar esta ubicación de bodega?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ui-action-danger">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
