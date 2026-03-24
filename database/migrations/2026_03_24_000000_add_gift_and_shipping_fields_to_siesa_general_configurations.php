<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('siesa_general_configurations', function (Blueprint $table) {
      $table->string('lista_precio_flete', 3)->after('lista_precio');
      $table->string('lista_precio_obsequio', 3)->after('lista_precio_flete');
      $table->string('motivo_obsequio', 2)->after('motivo');
    });
  }

  public function down(): void
  {
    Schema::table('siesa_general_configurations', function (Blueprint $table) {
      $table->dropColumn(['lista_precio_flete', 'lista_precio_obsequio', 'motivo_obsequio']);
    });
  }
};
