<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ShopifyOrderProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessShopifyOrder implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 3;
  public int $timeout = 120;
  public int $backoff = 60;

  public function __construct(
    public Order $order
  ) {}

  public function handle(ShopifyOrderProcessor $processor): void
  {
    try {
      $processor->process($this->order);
    } catch (Exception $e) {
      Log::error('Error procesando pedido Shopify', [
        'order_id' => $this->order->id,
        'shopify_order_id' => $this->order->shopify_order_id,
        'exception' => $e->getMessage(),
        'attempts' => $this->attempts(),
      ]);

      if ($this->attempts() >= $this->tries) {
        Log::critical('Pedido falló después de 3 intentos', [
          'order_id' => $this->order->id,
          'shopify_order_id' => $this->order->shopify_order_id,
        ]);
      }

      throw $e;
    }
  }

  public function failed(Exception $exception): void
  {
    Log::critical('Job ProcessShopifyOrder falló definitivamente', [
      'order_id' => $this->order->id,
      'shopify_order_id' => $this->order->shopify_order_id,
      'exception' => $exception->getMessage(),
    ]);
  }
}
