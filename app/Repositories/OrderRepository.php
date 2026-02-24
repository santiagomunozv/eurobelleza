<?php

namespace App\Repositories;

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function findByShopifyOrderId(string $shopifyOrderId): ?Order
    {
        return Order::where('shopify_order_id', $shopifyOrderId)->first();
    }

    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function update(Order $order, array $data): bool
    {
        return $order->update($data);
    }

    public function getFailedOrders(): Collection
    {
        return Order::where('status', OrderStatusEnum::FAILED->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingOrders(): Collection
    {
        return Order::where('status', OrderStatusEnum::PENDING->value)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getOrdersByDateRange(string $startDate, string $endDate): Collection
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function incrementAttempts(Order $order): bool
    {
        return $order->increment('attempts');
    }

    public function updateStatus(Order $order, OrderStatusEnum $status, ?string $errorMessage = null): bool
    {
        $data = ['status' => $status->value];

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        if ($status === OrderStatusEnum::COMPLETED) {
            $data['processed_at'] = now();
        }

        return $order->update($data);
    }
}
