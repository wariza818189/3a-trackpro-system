<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restocks', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable();
            $table->index('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('restock_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable();
            $table->index('purchase_order_item_id');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('restock_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropIndex(['purchase_order_item_id']);
            $table->dropColumn('purchase_order_item_id');
        });

        Schema::table('restocks', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropIndex(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });
    }
};
