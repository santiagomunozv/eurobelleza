<x-app-layout>
    <x-slot name="header">
        <div class="ui-header-row">
            <h2 class="ui-title-page">
                {{ __('Métodos de Pago - SIESA') }}
            </h2>
            <a href="{{ route('admin.siesa.payment-gateways.create') }}"
                class="ui-btn-primary">
                Agregar Método de Pago
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
                            <p class="text-gray-500 mb-4">No hay métodos de pago configurados.</p>
                            <a href="{{ route('admin.siesa.payment-gateways.create') }}"
                                class="ui-btn-primary">
                                Agregar el Primero
                            </a>
                        </div>
                    @else
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead class="ui-table-head">
                                    <tr>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Método de Pago
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Sucursal
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Condición Pago
                                        </th>
                                        <th scope="col"
                                            class="ui-table-th">
                                            Centro Costo
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
                                                {{ $mapping->payment_gateway_name }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->sucursal }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->condicion_pago }}
                                            </td>
                                            <td class="ui-table-td">
                                                {{ $mapping->centro_costo }}
                                            </td>
                                            <td class="ui-table-td text-right font-medium">
                                                <a href="{{ route('admin.siesa.payment-gateways.edit', $mapping) }}"
                                                    class="ui-action-link mr-3">
                                                    Editar
                                                </a>
                                                <form
                                                    action="{{ route('admin.siesa.payment-gateways.destroy', $mapping) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('¿Estás seguro de eliminar este método de pago?');">
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
