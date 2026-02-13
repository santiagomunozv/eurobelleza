<?php

namespace App\Enums;

enum InventorySyncStatusEnum: string
{
  case PENDING = 'pending';
  case SUCCESS = 'success';
  case FAILED = 'failed';
  case SKIPPED = 'skipped';

  public static function getValues(): array
  {
    return array_column(self::cases(), 'value');
  }

  public function isPending(): bool
  {
    return $this === self::PENDING;
  }

  public function isSuccess(): bool
  {
    return $this === self::SUCCESS;
  }

  public function isFailed(): bool
  {
    return $this === self::FAILED;
  }

  public function isSkipped(): bool
  {
    return $this === self::SKIPPED;
  }
}
