<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLog;
use App\Enums\OrderLogLevelEnum;

class OrderLogService
{
  public function logInfo(Order $order, string $message, ?array $context = null): OrderLog
  {
    return $this->createLog($order, OrderLogLevelEnum::INFO, $message, $context);
  }

  public function logWarning(Order $order, string $message, ?array $context = null): OrderLog
  {
    return $this->createLog($order, OrderLogLevelEnum::WARNING, $message, $context);
  }

  public function logError(Order $order, string $message, ?array $context = null): OrderLog
  {
    return $this->createLog($order, OrderLogLevelEnum::ERROR, $message, $context);
  }

  public function logSuccess(Order $order, string $message, ?array $context = null): OrderLog
  {
    return $this->createLog($order, OrderLogLevelEnum::INFO, $message, $context);
  }

  private function createLog(Order $order, OrderLogLevelEnum $level, string $message, ?array $context): OrderLog
  {
    return OrderLog::create([
      'order_id' => $order->id,
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ]);
  }
}
