<?php

namespace App\Models;

use App\Enums\OrderLogLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'level' => OrderLogLevelEnum::class,
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
