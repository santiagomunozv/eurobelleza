<?php

use App\Enums\OrderLogLevelEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('level', OrderLogLevelEnum::getValues());
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};
