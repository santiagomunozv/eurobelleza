<?php

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_order_id')->unique();
            $table->string('shopify_order_number', 50);
            $table->longText('order_json');
            $table->string('flat_file_name')->nullable();
            $table->string('flat_file_path', 500)->nullable();
            $table->enum('status', OrderStatusEnum::getValues())->default(OrderStatusEnum::PENDING->value);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('shopify_order_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
