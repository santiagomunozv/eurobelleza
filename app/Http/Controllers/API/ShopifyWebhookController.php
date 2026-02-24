<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\OrderRepository;
use App\Enums\OrderStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifyWebhookController extends Controller
{
  /**
   * Recibe el webhook de creación de pedido desde Shopify
   *
   * @param Request $request
   * @param OrderRepository $orderRepository
   * @return JsonResponse
   */
  public function ordersCreate(Request $request, OrderRepository $orderRepository): JsonResponse
  {
    DB::beginTransaction();

    try {
      $orderData = $request->all();

      $shopifyOrderId = $orderData['id'] ?? null;
      $shopifyOrderNumber = $orderData['order_number'] ?? $orderData['name'] ?? null;

      if (!$shopifyOrderId || !$shopifyOrderNumber) {
        Log::error('Webhook de Shopify sin datos requeridos', [
          'data' => $orderData
        ]);

        return response()->json([
          'error' => 'Missing required fields'
        ], 400);
      }

      $existingOrder = $orderRepository->findByShopifyOrderId($shopifyOrderId);

      if ($existingOrder) {
        Log::info('Pedido duplicado ignorado', [
          'shopify_order_id' => $shopifyOrderId,
          'order_id' => $existingOrder->id
        ]);

        DB::commit();

        return response()->json([
          'message' => 'Order already exists',
          'order_id' => $existingOrder->id
        ], 200);
      }

      $order = $orderRepository->create([
        'shopify_order_id' => $shopifyOrderId,
        'shopify_order_number' => $shopifyOrderNumber,
        'order_json' => $orderData,
        'status' => OrderStatusEnum::PENDING->value,
        'attempts' => 0,
      ]);

      Log::info('Pedido recibido de Shopify', [
        'order_id' => $order->id,
        'shopify_order_id' => $shopifyOrderId,
        'shopify_order_number' => $shopifyOrderNumber,
      ]);

      DB::commit();

      return response()->json([
        'message' => 'Order received successfully',
        'order_id' => $order->id
      ], 201);
    } catch (Exception $e) {
      DB::rollBack();

      Log::error('Error procesando webhook de Shopify', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'error' => 'Internal server error'
      ], 500);
    }
  }
}
