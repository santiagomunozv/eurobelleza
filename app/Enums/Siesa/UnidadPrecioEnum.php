<?php

namespace App\Enums\Siesa;

enum UnidadPrecioEnum: string
{
  case INVENTARIO = '1';
  case ALTERNA = '2';
  case OBSEQUIO = '3';

  public function label(): string
  {
    return match ($this) {
      self::INVENTARIO => 'Unidad de Inventario',
      self::ALTERNA => 'Unidad Alterna',
      self::OBSEQUIO => 'Obsequio',
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
