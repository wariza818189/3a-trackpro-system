<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->after('recorded_by');
            $table->index(
                ['cash_register_session_id', 'created_at'],
                'sales_cash_register_session_id_created_at_index',
            );
            $table->foreign('cash_register_session_id', 'sales_cash_register_session_id_foreign')
                ->references('id')->on('cash_register_sessions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign('sales_cash_register_session_id_foreign');
            $table->dropIndex('sales_cash_register_session_id_created_at_index');
            $table->dropColumn('cash_register_session_id');
        });
    }
};
