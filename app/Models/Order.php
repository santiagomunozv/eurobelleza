<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'shopify_order_id',
        'shopify_order_number',
        'order_json',
        'flat_file_name',
        'flat_file_path',
        'status',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'order_json' => 'array',
        'status' => OrderStatusEnum::class,
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(OrderLog::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatusEnum::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', OrderStatusEnum::FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', OrderStatusEnum::PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', OrderStatusEnum::PROCESSING);
    }

    public function getCustomerNameAttribute(): string
    {
        $customer = $this->order_json['customer'] ?? [];
        return trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    }

    public function getLineItemsAttribute(): array
    {
        return $this->order_json['line_items'] ?? [];
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) ($this->order_json['total_price'] ?? 0);
    }

    public function getCustomerEmailAttribute(): ?string
    {
        return $this->order_json['customer']['email'] ?? null;
    }
}
