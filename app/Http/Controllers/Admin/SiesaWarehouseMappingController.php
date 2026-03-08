<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiesaWarehouseMapping;
use App\Http\Requests\StoreSiesaWarehouseMappingRequest;
use App\Http\Requests\UpdateSiesaWarehouseMappingRequest;
use Illuminate\Http\Request;

class SiesaWarehouseMappingController extends Controller
{
  public function index()
  {
    $mappings = SiesaWarehouseMapping::orderBy('shopify_location_name')->get();

    return view('admin.siesa.warehouses.index', compact('mappings'));
  }

  public function create()
  {
    return view('admin.siesa.warehouses.create');
  }

  public function store(StoreSiesaWarehouseMappingRequest $request)
  {
    SiesaWarehouseMapping::create($request->validated());

    return redirect()->route('admin.siesa.warehouses.index')
      ->with('success', 'Ubicación de bodega configurada exitosamente.');
  }

  public function edit(SiesaWarehouseMapping $warehouse)
  {
    return view('admin.siesa.warehouses.edit', ['mapping' => $warehouse]);
  }

  public function update(UpdateSiesaWarehouseMappingRequest $request, SiesaWarehouseMapping $warehouse)
  {
    $warehouse->update($request->validated());

    return redirect()->route('admin.siesa.warehouses.index')
      ->with('success', 'Ubicación de bodega actualizada exitosamente.');
  }

  public function destroy(SiesaWarehouseMapping $warehouse)
  {
    $warehouse->delete();

    return redirect()->route('admin.siesa.warehouses.index')
      ->with('success', 'Ubicación de bodega eliminada exitosamente.');
  }
}
