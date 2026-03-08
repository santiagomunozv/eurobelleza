<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('siesa_warehouse_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_location_id')->unique()->comment('ID de ubicación en Shopify');
            $table->string('shopify_location_name')->nullable()->comment('Nombre de la ubicación en Shopify');
            $table->string('bodega_code', 10)->comment('Código de bodega en SIESA');
            $table->string('location_code', 10)->nullable()->comment('Código de localización en SIESA');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siesa_warehouse_mappings');
    }
};
