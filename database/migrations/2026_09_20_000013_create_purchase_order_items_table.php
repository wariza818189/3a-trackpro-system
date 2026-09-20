<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('expected_unit_cost', 12, 2);
            $table->timestamps();
            $table->unique(
                ['purchase_order_id', 'product_variant_id'],
                'po_items_po_variant_unique',
            );
            $table->index(
                ['product_variant_id', 'purchase_order_id'],
                'po_items_variant_po_index',
            );
        });

        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_positive CHECK (ordered_quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_cost_nonnegative CHECK (expected_unit_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
