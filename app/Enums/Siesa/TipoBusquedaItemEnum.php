<?php

namespace App\Enums\Siesa;

enum TipoBusquedaItemEnum: string
{
  case BARRAS = 'B';
  case ITEM = 'I';
  case REFERENCIA = 'R';
  case DESCRIPCION = 'D';

  public function label(): string
  {
    return match ($this) {
      self::BARRAS => 'Por Código de Barras',
      self::ITEM => 'Por Código de Item',
      self::REFERENCIA => 'Por Referencia',
      self::DESCRIPCION => 'Por Descripción',
    };
  }

  public static function toArray(): array
  {
    return array_map(fn($case) => [
      'value' => $case->value,
      'label' => $case->label()
    ], self::cases());
  }
}
