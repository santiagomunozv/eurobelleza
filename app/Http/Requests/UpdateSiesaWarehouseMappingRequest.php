<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiesaWarehouseMappingRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $warehouseId = $this->route('warehouse')->id;

    return [
      'shopify_location_id' => [
        'required',
        'integer',
        'min:1',
        Rule::unique('siesa_warehouse_mappings', 'shopify_location_id')->ignore($warehouseId),
      ],
      'shopify_location_name' => 'nullable|string|max:255',
      'bodega_code' => 'required|string|max:10',
      'location_code' => 'nullable|string|max:10',
    ];
  }

  public function messages(): array
  {
    return [
      'shopify_location_id.required' => 'El ID de ubicación de Shopify es obligatorio.',
      'shopify_location_id.integer' => 'El ID de ubicación debe ser un número entero.',
      'shopify_location_id.unique' => 'Ya existe una configuración para esta ubicación de Shopify.',
      'bodega_code.required' => 'El código de bodega es obligatorio.',
      'bodega_code.max' => 'El código de bodega no puede tener más de 10 caracteres.',
      'location_code.max' => 'El código de localización no puede tener más de 10 caracteres.',
    ];
  }
}
