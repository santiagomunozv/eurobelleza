<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiesaPaymentGatewayMapping extends Model
{
  use HasFactory;

  protected $fillable = [
    'payment_gateway_name',
    'sucursal',
    'condicion_pago',
    'centro_costo',
  ];

  /**
   * Busca un mapeo por nombre de gateway (exact match)
   */
  public static function findByGateway(string $gatewayName): ?self
  {
    return self::where('payment_gateway_name', $gatewayName)->first();
  }

  /**
   * Verifica si el gateway tiene pedidos asociados
   */
  public function hasOrders(): bool
  {
    return Order::whereJsonContains('order_json->payment_gateway_names', $this->payment_gateway_name)
      ->exists();
  }
}
