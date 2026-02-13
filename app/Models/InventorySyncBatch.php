<?php

namespace App\Models;

use App\Enums\SyncBatchStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySyncBatch extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'total_products',
        'successful_syncs',
        'failed_syncs',
        'skipped_syncs',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'status' => SyncBatchStatusEnum::class,
        'total_products' => 'integer',
        'successful_syncs' => 'integer',
        'failed_syncs' => 'integer',
        'skipped_syncs' => 'integer',
    ];

    public function syncs(): HasMany
    {
        return $this->hasMany(InventorySync::class, 'sync_batch_id');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', SyncBatchStatusEnum::RUNNING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SyncBatchStatusEnum::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', SyncBatchStatusEnum::FAILED);
    }
}
