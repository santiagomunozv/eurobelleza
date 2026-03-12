<x-app-layout>
    <x-slot name="header">
        <h2 class="ui-title-page">
            {{ __('Agregar Método de Pago') }}
        </h2>
    </x-slot>

    <div class="ui-page-lg">
        <div class="ui-container-sm">
            <div class="ui-card overflow-hidden">
                <div class="ui-card-body">
                    <form method="POST" action="{{ route('admin.siesa.payment-gateways.store') }}">
                        @csrf

                        <div class="ui-form-grid">
                        <div class="ui-form-col">
                            <label for="payment_gateway_name" class="ui-label">
                                Nombre del Método de Pago <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="payment_gateway_name" id="payment_gateway_name"
                                value="{{ old('payment_gateway_name') }}" maxlength="100"
                                class="ui-input-sm @error('payment_gateway_name') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">Ej: Addi Payment, visa, mastercard</p>
                            @error('payment_gateway_name')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="sucursal" class="ui-label">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="sucursal" id="sucursal" value="{{ old('sucursal') }}"
                                maxlength="2"
                                class="ui-input-sm @error('sucursal') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">Código de sucursal en SIESA (2 caracteres). Ej: 01, 02
                            </p>
                            @error('sucursal')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="condicion_pago" class="ui-label">
                                Condición de Pago <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="condicion_pago" id="condicion_pago"
                                value="{{ old('condicion_pago') }}" maxlength="2"
                                class="ui-input-sm @error('condicion_pago') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">Condición de pago en SIESA (2 caracteres). Ej: 30, 60,
                                90</p>
                            @error('condicion_pago')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ui-form-col">
                            <label for="centro_costo" class="ui-label">
                                Centro de Costo <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="centro_costo" id="centro_costo"
                                value="{{ old('centro_costo') }}" maxlength="8"
                                class="ui-input-sm @error('centro_costo') border-red-500 @enderror"
                                required>
                            <p class="ui-help-sm">Centro de costo en SIESA (máx. 8 caracteres). Ej:
                                021001</p>
                            @error('centro_costo')
                                <p class="ui-error">{{ $message }}</p>
                            @enderror
                        </div>
                        </div>

                        <div class="ui-form-actions">
                            <a href="{{ route('admin.siesa.payment-gateways.index') }}"
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
