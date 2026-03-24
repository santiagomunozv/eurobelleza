<?php

namespace App\Http\Requests;

use App\Enums\Siesa\TipoClienteEnum;
use App\Enums\Siesa\TipoBusquedaItemEnum;
use App\Enums\Siesa\UnidadPrecioEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiesaGeneralConfigurationRequest extends FormRequest
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
        return [
            'codigo_cliente' => ['required', 'string', 'max:12'],
            'codigo_vendedor' => ['required', 'string', 'max:13'],
            'detalle_movimiento' => ['required', 'string', 'max:20'],
            'motivo' => ['required', 'string', 'max:2'],
            'motivo_obsequio' => ['required', 'string', 'max:2'],
            'lista_precio' => ['required', 'string', 'max:3'],
            'lista_precio_flete' => ['required', 'string', 'max:3'],
            'lista_precio_obsequio' => ['required', 'string', 'max:3'],
            'unidad_captura' => ['required', 'string', 'max:3'],
            'tipo_cliente' => ['required', Rule::enum(TipoClienteEnum::class)],
            'tipo_busqueda_item' => ['required', Rule::enum(TipoBusquedaItemEnum::class)],
            'unidad_precio' => ['required', Rule::enum(UnidadPrecioEnum::class)],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'codigo_cliente' => 'código del cliente',
            'codigo_vendedor' => 'código del vendedor',
            'detalle_movimiento' => 'detalle del movimiento',
            'motivo' => 'motivo',
            'motivo_obsequio' => 'motivo de obsequio',
            'lista_precio' => 'lista de precio',
            'lista_precio_flete' => 'lista de precio de flete',
            'lista_precio_obsequio' => 'lista de precio de obsequio',
            'unidad_captura' => 'unidad de captura',
            'tipo_cliente' => 'tipo de cliente',
            'tipo_busqueda_item' => 'tipo de búsqueda del ítem',
            'unidad_precio' => 'unidad del precio',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de texto.',
            'max' => 'El campo :attribute no puede tener más de :max caracteres.',
            'enum' => 'El valor seleccionado para :attribute no es válido.',
        ];
    }
}
