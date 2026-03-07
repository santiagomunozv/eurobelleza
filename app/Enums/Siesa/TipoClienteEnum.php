<?php

namespace App\Enums\Siesa;

enum TipoClienteEnum: string
{
  case EAN = '1';
  case TERCERO = '2';

  public function label(): string
  {
    return match ($this) {
      self::EAN => 'Con código EAN',
      self::TERCERO => 'Con tercero del sistema',
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
