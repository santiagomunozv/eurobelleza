<?php

namespace App\Models;

use App\Enums\Siesa\TipoClienteEnum;
use App\Enums\Siesa\TipoBusquedaItemEnum;
use App\Enums\Siesa\UnidadPrecioEnum;
use Illuminate\Database\Eloquent\Model;

class SiesaGeneralConfiguration extends Model
{
  protected $guarded = [];

  protected $casts = [
    'tipo_cliente' => TipoClienteEnum::class,
    'tipo_busqueda_item' => TipoBusquedaItemEnum::class,
    'unidad_precio' => UnidadPrecioEnum::class,
  ];

  /**
   * Obtiene la configuración única del sistema (Singleton)
   *
   * @return self
   */
  public static function getConfig(): self
  {
    return self::firstOrFail();
  }

  /**
   * Prevenir la creación de múltiples registros
   */
  protected static function boot()
  {
    parent::boot();

    static::creating(function ($model) {
      if (self::count() > 0) {
        throw new \Exception('Solo puede existir un registro de configuración general de SIESA');
      }
    });
  }
}
