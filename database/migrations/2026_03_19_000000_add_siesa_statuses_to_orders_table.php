<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'rpa_processing', 'completed', 'failed', 'sent_to_siesa', 'siesa_error', 'payment_expired') NOT NULL DEFAULT 'pending'");
  }

  public function down(): void
  {
    DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending'");
  }
};
