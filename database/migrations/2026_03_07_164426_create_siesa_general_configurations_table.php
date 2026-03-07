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
        Schema::create('siesa_general_configurations', function (Blueprint $table) {
            $table->id();

            // Campos de texto digitado
            $table->string('codigo_cliente', 13);
            $table->string('codigo_vendedor', 13);
            $table->string('detalle_movimiento', 20);
            $table->string('motivo', 2);
            $table->string('lista_precio', 3);
            $table->string('unidad_captura', 3);

            // Campos enum (char)
            $table->char('tipo_cliente', 1)->default('2');
            $table->char('tipo_busqueda_item', 1)->default('R');
            $table->char('unidad_precio', 1)->default('1');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siesa_general_configurations');
    }
};
