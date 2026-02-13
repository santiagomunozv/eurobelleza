<?php

namespace App\Enums;

enum SyncBatchStatusEnum: string
{
  case RUNNING = 'running';
  case COMPLETED = 'completed';
  case FAILED = 'failed';
  case PARTIAL = 'partial';

  public static function getValues(): array
  {
    return array_column(self::cases(), 'value');
  }

  public function isRunning(): bool
  {
    return $this === self::RUNNING;
  }

  public function isCompleted(): bool
  {
    return $this === self::COMPLETED;
  }

  public function isFailed(): bool
  {
    return $this === self::FAILED;
  }

  public function isPartial(): bool
  {
    return $this === self::PARTIAL;
  }

  public function isFinished(): bool
  {
    return in_array($this, [self::COMPLETED, self::FAILED, self::PARTIAL]);
  }
}
