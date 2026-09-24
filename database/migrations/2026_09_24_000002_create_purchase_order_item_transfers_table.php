<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_item_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_purchase_order_item_id');
            $table->foreignId('target_purchase_order_item_id');
            $table->decimal('quantity', 14, 3);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('created_at')->nullable();
            $table->foreign('source_purchase_order_item_id', 'po_item_transfers_source_fk')
                ->references('id')->on('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('target_purchase_order_item_id', 'po_item_transfers_target_fk')
                ->references('id')->on('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->unique('source_purchase_order_item_id', 'po_item_transfers_source_unique');
            $table->unique('target_purchase_order_item_id', 'po_item_transfers_target_unique');
            $table->index(['created_by', 'created_at'], 'po_item_transfers_actor_created_index');
        });

        DB::statement('ALTER TABLE purchase_order_item_transfers ADD CONSTRAINT po_item_transfers_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_item_transfers ADD CONSTRAINT po_item_transfers_distinct_items CHECK (source_purchase_order_item_id <> target_purchase_order_item_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_transfers');
    }
};
