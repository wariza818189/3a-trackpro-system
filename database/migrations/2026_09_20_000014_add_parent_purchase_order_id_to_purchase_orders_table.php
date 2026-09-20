<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('parent_purchase_order_id')->nullable()->after('id');
            $table->index(
                'parent_purchase_order_id',
                'purchase_orders_parent_purchase_order_id_index',
            );
            $table->foreign(
                'parent_purchase_order_id',
                'purchase_orders_parent_purchase_order_id_foreign',
            )->references('id')->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('purchase_orders_parent_purchase_order_id_foreign');
            $table->dropIndex('purchase_orders_parent_purchase_order_id_index');
            $table->dropColumn('parent_purchase_order_id');
        });
    }
};
