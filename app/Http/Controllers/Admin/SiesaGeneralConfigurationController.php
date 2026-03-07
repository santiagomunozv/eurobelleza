<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSiesaGeneralConfigurationRequest;
use App\Models\SiesaGeneralConfiguration;
use App\Enums\Siesa\TipoClienteEnum;
use App\Enums\Siesa\TipoBusquedaItemEnum;
use App\Enums\Siesa\UnidadPrecioEnum;

class SiesaGeneralConfigurationController extends Controller
{
    /**
     * Muestra el formulario de configuración general de SIESA
     */
    public function edit()
    {
        $configuration = SiesaGeneralConfiguration::getConfig();

        return view('admin.siesa.configuration.edit', [
            'configuration' => $configuration,
            'tipoClienteOptions' => TipoClienteEnum::toArray(),
            'tipoBusquedaItemOptions' => TipoBusquedaItemEnum::toArray(),
            'unidadPrecioOptions' => UnidadPrecioEnum::toArray(),
        ]);
    }

    /**
     * Actualiza la configuración general de SIESA
     */
    public function update(UpdateSiesaGeneralConfigurationRequest $request)
    {
        $configuration = SiesaGeneralConfiguration::getConfig();
        $configuration->update($request->validated());

        return redirect()
            ->route('admin.siesa.configuration.edit')
            ->with('success', 'Configuración actualizada correctamente');
    }
}
