<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('siesa_order_number', 20)->nullable()->after('processed_at');
            $table->string('siesa_document_alt', 20)->nullable()->after('siesa_order_number');
            $table->date('siesa_order_date')->nullable()->after('siesa_document_alt');
            $table->string('siesa_erp_status', 50)->nullable()->after('siesa_order_date');
            $table->timestamp('siesa_confirmed_at')->nullable()->after('siesa_erp_status');
            $table->string('siesa_confirmation_file', 255)->nullable()->after('siesa_confirmed_at');

            $table->index('siesa_document_alt');
            $table->index('siesa_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['siesa_document_alt']);
            $table->dropIndex(['siesa_confirmed_at']);
            $table->dropColumn([
                'siesa_order_number',
                'siesa_document_alt',
                'siesa_order_date',
                'siesa_erp_status',
                'siesa_confirmed_at',
                'siesa_confirmation_file',
            ]);
        });
    }
};
