<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('movement_type', ['INITIAL_STOCK', 'RESTOCK', 'SALE', 'CORRECTION', 'SALE_VOID']);
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_change', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('restock_item_id')->nullable()->constrained('restock_items')->restrictOnDelete()->restrictOnUpdate();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['sale_item_id', 'movement_type']);
            $table->unique('restock_item_id');
            $table->index(['product_variant_id', 'created_at', 'id'], 'movements_variant_chronology_index');
            $table->index(['performed_by', 'created_at']);
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT movements_before_nonnegative CHECK (quantity_before >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT movements_after_nonnegative CHECK (quantity_after >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT movements_quantity_balanced CHECK (quantity_after = quantity_before + quantity_change)');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT movements_type_consistent CHECK ((movement_type = 'INITIAL_STOCK' AND quantity_before = 0 AND quantity_change >= 0 AND sale_item_id IS NULL AND restock_item_id IS NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0) OR (movement_type = 'CORRECTION' AND quantity_change <> 0 AND sale_item_id IS NULL AND restock_item_id IS NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) > 0) OR (movement_type = 'RESTOCK' AND quantity_change > 0 AND restock_item_id IS NOT NULL AND sale_item_id IS NULL) OR (movement_type = 'SALE' AND quantity_change < 0 AND sale_item_id IS NOT NULL AND restock_item_id IS NULL) OR (movement_type = 'SALE_VOID' AND quantity_change > 0 AND sale_item_id IS NOT NULL AND restock_item_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
