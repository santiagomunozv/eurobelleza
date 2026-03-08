<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiesaWarehouseMapping extends Model
{
  use HasFactory;

  protected $fillable = [
    'shopify_location_id',
    'shopify_location_name',
    'bodega_code',
    'location_code',
  ];

  protected $casts = [
    'shopify_location_id' => 'integer',
  ];
}
