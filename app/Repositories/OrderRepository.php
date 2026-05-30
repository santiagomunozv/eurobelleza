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

    public function findByShopifyOrderNumber(string $shopifyOrderNumber): ?Order
    {
        return Order::where('shopify_order_number', $shopifyOrderNumber)->first();
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
            $data['error_message'] = $this->normalizeTextForDatabase($errorMessage);
        } elseif (in_array($status, [
            OrderStatusEnum::PENDING,
            OrderStatusEnum::PROCESSING,
            OrderStatusEnum::RPA_PROCESSING,
            OrderStatusEnum::SENT_TO_SIESA,
            OrderStatusEnum::COMPLETED,
        ], true)) {
            $data['error_message'] = null;
        }

        if ($status === OrderStatusEnum::COMPLETED) {
            $data['processed_at'] = now();
        } else {
            $data['processed_at'] = null;
        }

        return $order->update($data);
    }

    private function normalizeTextForDatabase(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'CP850');

            if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
                $converted = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }

            $value = $converted;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($value ?? '');
    }
}
