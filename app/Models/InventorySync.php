<?php

namespace App\Models;

use App\Enums\InventorySyncStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySync extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sync_batch_id',
        'sku',
        'product_name',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_inventory_item_id',
        'shopify_location_id',
        'siesa_quantity',
        'shopify_quantity_before',
        'shopify_quantity_after',
        'status',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'status' => InventorySyncStatusEnum::class,
        'siesa_quantity' => 'integer',
        'shopify_quantity_before' => 'integer',
        'shopify_quantity_after' => 'integer',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventorySyncBatch::class, 'sync_batch_id');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', InventorySyncStatusEnum::SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', InventorySyncStatusEnum::FAILED);
    }

    public function scopeSkipped($query)
    {
        return $query->where('status', InventorySyncStatusEnum::SKIPPED);
    }
}
