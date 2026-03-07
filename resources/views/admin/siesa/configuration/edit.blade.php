<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuración General SIESA') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Mensaje de éxito -->
            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 border border-green-200"
                    role="alert">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            <!-- Mensaje de error general -->
            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 border border-red-200"
                    role="alert">
                    <div class="flex">
                        <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium mb-2">Por favor corrija los siguientes errores:</p>
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Formulario -->
            <div class="bg-white shadow-md rounded-xl overflow-hidden">
                <form method="POST" action="{{ route('admin.siesa.configuration.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                Configuración de Archivos Planos SIESA
                            </h3>
                            <p class="text-sm text-gray-600">
                                Configure los valores por defecto para la generación de archivos planos de pedidos.
                            </p>
                        </div>

                        <div class="space-y-6">
                            <!-- Código del Cliente -->
                            <div>
                                <label for="codigo_cliente" class="block text-sm font-medium text-gray-700 mb-2">
                                    Código del Cliente <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="codigo_cliente" name="codigo_cliente" maxlength="13"
                                    value="{{ old('codigo_cliente', $configuration->codigo_cliente) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('codigo_cliente') border-red-500 @enderror"
                                    placeholder="Ej: 222222222222" required>
                                @error('codigo_cliente')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Máximo 12 caracteres</p>
                            </div>

                            <!-- Tipo de Cliente -->
                            <div>
                                <label for="tipo_cliente" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Cliente <span class="text-red-500">*</span>
                                </label>
                                <select id="tipo_cliente" name="tipo_cliente"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('tipo_cliente') border-red-500 @enderror"
                                    required>
                                    @foreach ($tipoClienteOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('tipo_cliente', $configuration->tipo_cliente->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tipo_cliente')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Código del Vendedor -->
                            <div>
                                <label for="codigo_vendedor" class="block text-sm font-medium text-gray-700 mb-2">
                                    Código del Vendedor <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="codigo_vendedor" name="codigo_vendedor" maxlength="13"
                                    value="{{ old('codigo_vendedor', $configuration->codigo_vendedor) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('codigo_vendedor') border-red-500 @enderror"
                                    placeholder="Ej: 16746504" required>
                                @error('codigo_vendedor')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Documento de identidad del vendedor. Máximo 13
                                    caracteres</p>
                            </div>

                            <!-- Detalle del Movimiento -->
                            <div>
                                <label for="detalle_movimiento" class="block text-sm font-medium text-gray-700 mb-2">
                                    Detalle del Movimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="detalle_movimiento" name="detalle_movimiento" maxlength="20"
                                    value="{{ old('detalle_movimiento', $configuration->detalle_movimiento) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('detalle_movimiento') border-red-500 @enderror"
                                    placeholder="Ej: PEDIDO SHOPIFY" required>
                                @error('detalle_movimiento')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Texto descriptivo del pedido. Máximo 20 caracteres
                                </p>
                            </div>

                            <!-- Motivo -->
                            <div>
                                <label for="motivo" class="block text-sm font-medium text-gray-700 mb-2">
                                    Motivo del Movimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="motivo" name="motivo" maxlength="2"
                                    value="{{ old('motivo', $configuration->motivo) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('motivo') border-red-500 @enderror"
                                    placeholder="Ej: 01" required>
                                @error('motivo')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Código del motivo (Ej: 01 = Ventas nacionales).
                                    Máximo 2 caracteres</p>
                            </div>

                            <!-- Lista de Precio -->
                            <div>
                                <label for="lista_precio" class="block text-sm font-medium text-gray-700 mb-2">
                                    Lista de Precio <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="lista_precio" name="lista_precio" maxlength="3"
                                    value="{{ old('lista_precio', $configuration->lista_precio) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('lista_precio') border-red-500 @enderror"
                                    placeholder="Ej: 012" required>
                                @error('lista_precio')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Código de lista de precio. Máximo 3 caracteres</p>
                            </div>

                            <!-- Unidad de Captura -->
                            <div>
                                <label for="unidad_captura" class="block text-sm font-medium text-gray-700 mb-2">
                                    Unidad de Captura <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="unidad_captura" name="unidad_captura" maxlength="3"
                                    value="{{ old('unidad_captura', $configuration->unidad_captura) }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('unidad_captura') border-red-500 @enderror"
                                    placeholder="Ej: UND" required>
                                @error('unidad_captura')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Unidad de medida (UND, PAQ, KG, etc.). Máximo 3
                                    caracteres</p>
                            </div>

                            <!-- Tipo de Búsqueda del Ítem -->
                            <div>
                                <label for="tipo_busqueda_item" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Búsqueda del Ítem <span class="text-red-500">*</span>
                                </label>
                                <select id="tipo_busqueda_item" name="tipo_busqueda_item"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('tipo_busqueda_item') border-red-500 @enderror"
                                    required>
                                    @foreach ($tipoBusquedaItemOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('tipo_busqueda_item', $configuration->tipo_busqueda_item->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tipo_busqueda_item')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Modo de identificar el ítem en SIESA</p>
                            </div>

                            <!-- Unidad del Precio -->
                            <div>
                                <label for="unidad_precio" class="block text-sm font-medium text-gray-700 mb-2">
                                    Unidad del Precio <span class="text-red-500">*</span>
                                </label>
                                <select id="unidad_precio" name="unidad_precio"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900 focus:ring-offset-0 transition-colors @error('unidad_precio') border-red-500 @enderror"
                                    required>
                                    @foreach ($unidadPrecioOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('unidad_precio', $configuration->unidad_precio->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unidad_precio')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">En qué unidad está expresado el precio</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer con botones -->
                    <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                        <p class="text-xs text-gray-500">
                            <span class="text-red-500">*</span> Campos obligatorios
                        </p>
                        <div class="flex gap-3">
                            <a href="{{ route('admin.orders.index') }}"
                                class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 transition-colors">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="px-5 py-2.5 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 transition-colors">
                                Guardar Configuración
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Información adicional -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Información importante</p>
                        <p>Esta configuración se aplica a todos los archivos planos generados para SIESA. Los cambios
                            afectarán los pedidos procesados a partir de este momento.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
