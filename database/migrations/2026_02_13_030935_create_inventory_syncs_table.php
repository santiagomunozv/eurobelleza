<?php

use App\Enums\InventorySyncStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_batch_id')->constrained('inventory_sync_batches')->onDelete('cascade');
            $table->string('sku', 100);
            $table->string('product_name', 500);
            $table->string('shopify_product_id')->nullable();
            $table->string('shopify_variant_id')->nullable();
            $table->string('shopify_inventory_item_id')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->integer('siesa_quantity');
            $table->integer('shopify_quantity_before')->nullable();
            $table->integer('shopify_quantity_after')->nullable();
            $table->enum('status', InventorySyncStatusEnum::getValues())->default(InventorySyncStatusEnum::PENDING->value);
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('sync_batch_id');
            $table->index('sku');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_syncs');
    }
};
