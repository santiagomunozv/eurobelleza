<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Editar Método de Pago') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('admin.siesa.payment-gateways.update', $mapping) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label for="payment_gateway_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre del Método de Pago <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="payment_gateway_name" id="payment_gateway_name"
                                value="{{ old('payment_gateway_name', $mapping->payment_gateway_name) }}"
                                maxlength="100"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('payment_gateway_name') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">Ej: Addi Payment, visa, mastercard</p>
                            @error('payment_gateway_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="sucursal" class="block text-sm font-medium text-gray-700 mb-2">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="sucursal" id="sucursal"
                                value="{{ old('sucursal', $mapping->sucursal) }}" maxlength="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('sucursal') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">Código de sucursal en SIESA (2 caracteres). Ej: 01, 02
                            </p>
                            @error('sucursal')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="condicion_pago" class="block text-sm font-medium text-gray-700 mb-2">
                                Condición de Pago <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="condicion_pago" id="condicion_pago"
                                value="{{ old('condicion_pago', $mapping->condicion_pago) }}" maxlength="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('condicion_pago') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">Condición de pago en SIESA (2 caracteres). Ej: 30, 60,
                                90</p>
                            @error('condicion_pago')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="centro_costo" class="block text-sm font-medium text-gray-700 mb-2">
                                Centro de Costo <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="centro_costo" id="centro_costo"
                                value="{{ old('centro_costo', $mapping->centro_costo) }}" maxlength="8"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error('centro_costo') border-red-500 @enderror"
                                required>
                            <p class="mt-1 text-sm text-gray-500">Centro de costo en SIESA (máx. 8 caracteres). Ej:
                                021001</p>
                            @error('centro_costo')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end mt-8 space-x-3">
                            <a href="{{ route('admin.siesa.payment-gateways.index') }}"
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
