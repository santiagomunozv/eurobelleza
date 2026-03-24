<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
  case PENDING = 'pending';
  case PROCESSING = 'processing';
  case COMPLETED = 'completed';
  case FAILED = 'failed';
  case SENT_TO_SIESA = 'sent_to_siesa';
  case SIESA_ERROR = 'siesa_error';

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

  public function isSentToSiesa(): bool
  {
    return $this === self::SENT_TO_SIESA;
  }

  public function isSiesaError(): bool
  {
    return $this === self::SIESA_ERROR;
  }

  public function canRetry(): bool
  {
    return in_array($this, [self::PENDING, self::FAILED, self::SIESA_ERROR]);
  }
}
