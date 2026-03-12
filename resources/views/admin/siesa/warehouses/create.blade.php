<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title-page">
            {{ __('Agregar Ubicación de Bodega') }}
        </h2>
    </x-slot>

    <div class="ui-page-lg">
        <div class="ui-container-sm">
            <div class="ui-card overflow-hidden">
                <div class="ui-card-body">
                    <form method="POST" action="{{ route('admin.siesa.warehouses.store') }}">
                        @csrf

                        <div class="ui-form-grid">
                        <div class="ui-form-col">
                            <label for="shopify_location_id" class="ui-label">
                                Shopify Location ID <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="shopify_location_id" id="shopify_location_id"
                                value="{{ old('shopify_location_id') }}"
                                class="ui-input-sm @error('shopify_location_id') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">ID de ubicación de inventario en Shopify. Ej:
                                80414113963</p>
                            @error('shopify_location_id')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="shopify_location_name" class="ui-label">
                                Nombre de Ubicación (Opcional)
                            </label>
                            <input type="text" name="shopify_location_name" id="shopify_location_name"
                                value="{{ old('shopify_location_name') }}" maxlength="255"
                                class="ui-input-sm @error('shopify_location_name') border-red-500 @enderror">
                            <p class="ui-help-sm">Nombre descriptivo para identificar la ubicación. Ej:
                                Bodega Principal
                            </p>
                            @error('shopify_location_name')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="bodega_code" class="ui-label">
                                Código de Bodega <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="bodega_code" id="bodega_code" value="{{ old('bodega_code') }}"
                                maxlength="10"
                                class="ui-input-sm @error('bodega_code') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">Código de bodega en SIESA (máx. 10 caracteres). Ej:
                                001, 002
                            </p>
                            @error('bodega_code')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="location_code" class="ui-label">
                                Código de Localización (Opcional)
                            </label>
                            <input type="text" name="location_code" id="location_code"
                                value="{{ old('location_code') }}" maxlength="10"
                                class="ui-input-sm @error('location_code') border-red-500 @enderror">
                            <p class="ui-help-sm">Código de localización en SIESA (máx. 10 caracteres).
                                Ej: 15
                            </p>
                            @error('location_code')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>
                        </div>

                        <div class="ui-form-actions">
                            <a href="{{ route('admin.siesa.warehouses.index') }}"
                                class="ui-btn-neutral">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="ui-btn-primary">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
