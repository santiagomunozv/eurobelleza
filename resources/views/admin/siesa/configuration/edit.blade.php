<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title-page">
            {{ __('Configuración General SIESA') }}
        </h2>
    </x-slot>

    <div class="ui-page-lg">
        <div class="ui-container-md">
            <!-- Mensaje de éxito -->
            @if (session('success'))
                <div class="ui-alert-success" role="alert">
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
                <div class="ui-alert-error" role="alert">
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
            <div class="ui-card overflow-hidden">
                <form method="POST" action="{{ route('admin.siesa.configuration.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="ui-card-body">
                        <div class="mb-6">
                            <h3 class="mb-1 text-lg font-semibold text-gray-900">
                                Configuración de Archivos Planos SIESA
                            </h3>
                            <p class="ui-text-muted text-sm">
                                Configure los valores por defecto para la generación de archivos planos de pedidos.
                            </p>
                        </div>

                        <div class="ui-form-grid">
                            <!-- Código del Cliente -->
                            <div class="ui-form-col">
                                <label for="codigo_cliente" class="ui-label">
                                    Código del Cliente <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="codigo_cliente" name="codigo_cliente" maxlength="13"
                                    value="{{ old('codigo_cliente', $configuration->codigo_cliente) }}"
                                    class="ui-input-sm @error('codigo_cliente') border-red-500 @enderror"
                                    placeholder="Ej: 222222222222" required>
                                @error('codigo_cliente')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Máximo 12 caracteres</p>
                            </div>

                            <!-- Tipo de Cliente -->
                            <div class="ui-form-col">
                                <label for="tipo_cliente" class="ui-label">
                                    Tipo de Cliente <span class="text-red-500">*</span>
                                </label>
                                <select id="tipo_cliente" name="tipo_cliente"
                                    class="ui-input-sm @error('tipo_cliente') border-red-500 @enderror" required>
                                    @foreach ($tipoClienteOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('tipo_cliente', $configuration->tipo_cliente->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tipo_cliente')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Código del Vendedor -->
                            <div class="ui-form-col">
                                <label for="codigo_vendedor" class="ui-label">
                                    Código del Vendedor <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="codigo_vendedor" name="codigo_vendedor" maxlength="13"
                                    value="{{ old('codigo_vendedor', $configuration->codigo_vendedor) }}"
                                    class="ui-input-sm @error('codigo_vendedor') border-red-500 @enderror"
                                    placeholder="Ej: 16746504" required>
                                @error('codigo_vendedor')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Documento de identidad del vendedor. Máximo 13
                                    caracteres</p>
                            </div>

                            <!-- Detalle del Movimiento -->
                            <div class="ui-form-col">
                                <label for="detalle_movimiento" class="ui-label">
                                    Detalle del Movimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="detalle_movimiento" name="detalle_movimiento" maxlength="20"
                                    value="{{ old('detalle_movimiento', $configuration->detalle_movimiento) }}"
                                    class="ui-input-sm @error('detalle_movimiento') border-red-500 @enderror"
                                    placeholder="Ej: PEDIDO SHOPIFY" required>
                                @error('detalle_movimiento')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Texto descriptivo del pedido. Máximo 20 caracteres
                                </p>
                            </div>

                            <!-- Motivo -->
                            <div class="ui-form-col">
                                <label for="motivo" class="ui-label">
                                    Motivo del Movimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="motivo" name="motivo" maxlength="2"
                                    value="{{ old('motivo', $configuration->motivo) }}"
                                    class="ui-input-sm @error('motivo') border-red-500 @enderror" placeholder="Ej: 01"
                                    required>
                                @error('motivo')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Código del motivo (Ej: 01 = Ventas nacionales).
                                    Máximo 2 caracteres</p>
                            </div>

                            <!-- Motivo Obsequio -->
                            <div class="ui-form-col">
                                <label for="motivo_obsequio" class="ui-label">
                                    Motivo de Obsequio <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="motivo_obsequio" name="motivo_obsequio" maxlength="2"
                                    value="{{ old('motivo_obsequio', $configuration->motivo_obsequio) }}"
                                    class="ui-input-sm @error('motivo_obsequio') border-red-500 @enderror"
                                    placeholder="Ej: 02" required>
                                @error('motivo_obsequio')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Motivo para productos de obsequio (precio = 0).
                                    Máximo 2 caracteres</p>
                            </div>

                            <!-- Lista de Precio -->
                            <div class="ui-form-col">
                                <label for="lista_precio" class="ui-label">
                                    Lista de Precio <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="lista_precio" name="lista_precio" maxlength="3"
                                    value="{{ old('lista_precio', $configuration->lista_precio) }}"
                                    class="ui-input-sm @error('lista_precio') border-red-500 @enderror"
                                    placeholder="Ej: 012" required>
                                @error('lista_precio')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Código de lista de precio. Máximo 3 caracteres</p>
                            </div>

                            <!-- Lista de Precio Flete -->
                            <div class="ui-form-col">
                                <label for="lista_precio_flete" class="ui-label">
                                    Lista de Precio Flete <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="lista_precio_flete" name="lista_precio_flete"
                                    maxlength="3"
                                    value="{{ old('lista_precio_flete', $configuration->lista_precio_flete) }}"
                                    class="ui-input-sm @error('lista_precio_flete') border-red-500 @enderror"
                                    placeholder="Ej: 999" required>
                                @error('lista_precio_flete')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Lista de precio para líneas de envío. Máximo 3 caracteres</p>
                            </div>

                            <!-- Lista de Precio Obsequio -->
                            <div class="ui-form-col">
                                <label for="lista_precio_obsequio" class="ui-label">
                                    Lista de Precio Obsequio <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="lista_precio_obsequio" name="lista_precio_obsequio"
                                    maxlength="3"
                                    value="{{ old('lista_precio_obsequio', $configuration->lista_precio_obsequio) }}"
                                    class="ui-input-sm @error('lista_precio_obsequio') border-red-500 @enderror"
                                    placeholder="Ej: 013" required>
                                @error('lista_precio_obsequio')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Lista de precio para productos de obsequio (precio = 0). Máximo 3
                                    caracteres</p>
                            </div>

                            <!-- Unidad de Captura -->
                            <div class="ui-form-col">
                                <label for="unidad_captura" class="ui-label">
                                    Unidad de Captura <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="unidad_captura" name="unidad_captura" maxlength="3"
                                    value="{{ old('unidad_captura', $configuration->unidad_captura) }}"
                                    class="ui-input-sm @error('unidad_captura') border-red-500 @enderror"
                                    placeholder="Ej: UND" required>
                                @error('unidad_captura')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Unidad de medida (UND, PAQ, KG, etc.). Máximo 3
                                    caracteres</p>
                            </div>

                            <!-- Tipo de Búsqueda del Ítem -->
                            <div class="ui-form-col">
                                <label for="tipo_busqueda_item" class="ui-label">
                                    Tipo de Búsqueda del Ítem <span class="text-red-500">*</span>
                                </label>
                                <select id="tipo_busqueda_item" name="tipo_busqueda_item"
                                    class="ui-input-sm @error('tipo_busqueda_item') border-red-500 @enderror"
                                    required>
                                    @foreach ($tipoBusquedaItemOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('tipo_busqueda_item', $configuration->tipo_busqueda_item->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tipo_busqueda_item')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">Modo de identificar el ítem en SIESA</p>
                            </div>

                            <!-- Unidad del Precio -->
                            <div class="ui-form-col">
                                <label for="unidad_precio" class="ui-label">
                                    Unidad del Precio <span class="text-red-500">*</span>
                                </label>
                                <select id="unidad_precio" name="unidad_precio"
                                    class="ui-input-sm @error('unidad_precio') border-red-500 @enderror" required>
                                    @foreach ($unidadPrecioOptions as $option)
                                        <option value="{{ $option['value'] }}"
                                            {{ old('unidad_precio', $configuration->unidad_precio->value) == $option['value'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unidad_precio')
                                    <p class="ui-error">{{ $message }}</p>
                                @enderror
                                <p class="ui-help">En qué unidad está expresado el precio</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer con botones -->
                    <div
                        class="flex items-center justify-between border-t border-[var(--color-border)] bg-gray-50 px-6 py-4">
                        <p class="ui-help">
                            <span class="text-red-500">*</span> Campos obligatorios
                        </p>
                        <div class="flex gap-3">
                            <a href="{{ route('admin.orders.index') }}" class="ui-btn-neutral">
                                Cancelar
                            </a>
                            <button type="submit" class="ui-btn-primary">
                                Guardar Configuración
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Información adicional -->
            <div class="ui-badge-info mt-6">
                <div class="flex">
                    <svg class="h-5 w-5 text-[#1c4789] mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
