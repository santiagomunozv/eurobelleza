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
        Schema::create('siesa_payment_gateway_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('payment_gateway_name', 100)->unique();
            $table->char('sucursal', 2);
            $table->char('condicion_pago', 2);
            $table->string('centro_costo', 8);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siesa_payment_gateway_mappings');
    }
};
