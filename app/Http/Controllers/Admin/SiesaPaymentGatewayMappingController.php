<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiesaPaymentGatewayMapping;
use App\Http\Requests\StoreSiesaPaymentGatewayMappingRequest;
use App\Http\Requests\UpdateSiesaPaymentGatewayMappingRequest;
use Illuminate\Http\Request;

class SiesaPaymentGatewayMappingController extends Controller
{
    public function index()
    {
        $mappings = SiesaPaymentGatewayMapping::orderBy('payment_gateway_name')->get();

        return view('admin.siesa.payment-gateways.index', compact('mappings'));
    }

    public function create()
    {
        return view('admin.siesa.payment-gateways.create');
    }

    public function store(StoreSiesaPaymentGatewayMappingRequest $request)
    {
        SiesaPaymentGatewayMapping::create($request->validated());

        return redirect()->route('admin.siesa.payment-gateways.index')
            ->with('success', 'Método de pago configurado exitosamente.');
    }

    public function edit(SiesaPaymentGatewayMapping $payment_gateway)
    {
        return view('admin.siesa.payment-gateways.edit', ['mapping' => $payment_gateway]);
    }

    public function update(UpdateSiesaPaymentGatewayMappingRequest $request, SiesaPaymentGatewayMapping $payment_gateway)
    {
        $payment_gateway->update($request->validated());

        return redirect()->route('admin.siesa.payment-gateways.index')
            ->with('success', 'Método de pago actualizado exitosamente.');
    }

    public function destroy(SiesaPaymentGatewayMapping $payment_gateway)
    {
        if ($payment_gateway->hasOrders()) {
            return redirect()->route('admin.siesa.payment-gateways.index')
                ->with('error', 'No se puede eliminar este método de pago porque tiene pedidos asociados.');
        }

        $payment_gateway->delete();

        return redirect()->route('admin.siesa.payment-gateways.index')
            ->with('success', 'Método de pago eliminado exitosamente.');
    }
}
