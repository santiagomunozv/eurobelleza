<?php

namespace App\Enums;

enum OrderLogLevelEnum: string
{
  case INFO = 'info';
  case WARNING = 'warning';
  case ERROR = 'error';

  public static function getValues(): array
  {
    return array_column(self::cases(), 'value');
  }

  public function isError(): bool
  {
    return $this === self::ERROR;
  }

  public function isWarning(): bool
  {
    return $this === self::WARNING;
  }

  public function isInfo(): bool
  {
    return $this === self::INFO;
  }
}
