<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiesaPaymentGatewayMappingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mappingId = $this->route('payment_gateway');

        return [
            'payment_gateway_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('siesa_payment_gateway_mappings', 'payment_gateway_name')->ignore($mappingId)
            ],
            'sucursal' => ['required', 'string', 'size:2'],
            'condicion_pago' => ['required', 'string', 'size:2'],
            'centro_costo' => ['required', 'string', 'max:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_gateway_name.required' => 'El nombre del método de pago es obligatorio.',
            'payment_gateway_name.unique' => 'Ya existe una configuración para este método de pago.',
            'payment_gateway_name.max' => 'El nombre no puede exceder los 100 caracteres.',
            'sucursal.required' => 'La sucursal es obligatoria.',
            'sucursal.size' => 'La sucursal debe tener exactamente 2 caracteres.',
            'condicion_pago.required' => 'La condición de pago es obligatoria.',
            'condicion_pago.size' => 'La condición de pago debe tener exactamente 2 caracteres.',
            'centro_costo.required' => 'El centro de costo es obligatorio.',
            'centro_costo.max' => 'El centro de costo no puede exceder los 8 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_gateway_name' => 'método de pago',
            'sucursal' => 'sucursal',
            'condicion_pago' => 'condición de pago',
            'centro_costo' => 'centro de costo',
        ];
    }
}
