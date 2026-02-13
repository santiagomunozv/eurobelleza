<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case COMPLETED = 'completed';
  case FAILED = 'failed';

  public static function getValues(): array
  {
    return array_column(self::cases(), 'value');
  }

  public function isPending(): bool
  {
    return $this === self::PENDING;
  }

  public function isProcessing(): bool
  {
    return $this === self::PROCESSING;
  }

  public function isCompleted(): bool
  {
    return $this === self::COMPLETED;
  }

  public function isFailed(): bool
  {
    return $this === self::FAILED;
  }

  public function canRetry(): bool
  {
    return in_array($this, [self::PENDING, self::FAILED]);
  }
}
