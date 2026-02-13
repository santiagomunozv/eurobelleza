<?php

use App\Enums\SyncBatchStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('successful_syncs')->default(0);
            $table->unsignedInteger('failed_syncs')->default(0);
            $table->unsignedInteger('skipped_syncs')->default(0);
            $table->enum('status', SyncBatchStatusEnum::getValues())->default(SyncBatchStatusEnum::RUNNING->value);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sync_batches');
    }
};
