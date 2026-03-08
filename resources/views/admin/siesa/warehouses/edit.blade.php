<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Ubicación de Bodega') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('admin.siesa.warehouses.update', $mapping) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label for="shopify_location_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Shopify Location ID <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="shopify_location_id" id="shopify_location_id"
                                value="{{ old('shopify_location_id', $mapping->shopify_location_id) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('shopify_location_id') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">ID de ubicación de inventario en Shopify. Ej:
                                80414113963</p>
                            @error('shopify_location_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="shopify_location_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre de Ubicación (Opcional)
                            </label>
                            <input type="text" name="shopify_location_name" id="shopify_location_name"
                                value="{{ old('shopify_location_name', $mapping->shopify_location_name) }}"
                                maxlength="255"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('shopify_location_name') border-red-500 @enderror">
                            <p class="mt-1 text-sm text-gray-500">Nombre descriptivo para identificar la ubicación. Ej:
                                Bodega Principal
                            </p>
                            @error('shopify_location_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="bodega_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Código de Bodega <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="bodega_code" id="bodega_code"
                                value="{{ old('bodega_code', $mapping->bodega_code) }}" maxlength="10"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('bodega_code') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">Código de bodega en SIESA (máx. 10 caracteres). Ej:
                                001, 002
                            </p>
                            @error('bodega_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="location_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Código de Localización (Opcional)
                            </label>
                            <input type="text" name="location_code" id="location_code"
                                value="{{ old('location_code', $mapping->location_code) }}" maxlength="10"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('location_code') border-red-500 @enderror">
                            <p class="mt-1 text-sm text-gray-500">Código de localización en SIESA (máx. 10 caracteres).
                                Ej: 15
                            </p>
                            @error('location_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end mt-8 space-x-3">
                            <a href="{{ route('admin.siesa.warehouses.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
