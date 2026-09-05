<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_id')->constrained('restocks')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['restock_id', 'product_variant_id']);
        });

        DB::statement('ALTER TABLE restock_items ADD CONSTRAINT restock_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE restock_items ADD CONSTRAINT restock_items_cost_nonnegative CHECK (unit_cost >= 0)');
        DB::statement('ALTER TABLE restock_items ADD CONSTRAINT restock_items_total_balanced CHECK (line_total = ROUND(quantity * unit_cost, 2))');
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_items');
    }
};
