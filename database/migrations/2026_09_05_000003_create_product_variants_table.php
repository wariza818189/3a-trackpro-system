<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->string('size', 80)->default('');
            $table->string('type_series', 80)->default('');
            $table->string('thickness', 40)->default('');
            $table->string('unit', 30);
            $table->enum('quantity_mode', ['whole', 'fractional'])->default('whole');
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->unique(['product_id', 'size', 'type_series', 'thickness', 'unit'], 'product_variants_identity_unique');
            $table->index(['product_id', 'status']);
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_cost_nonnegative CHECK (cost_price IS NULL OR cost_price >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_price_positive CHECK (selling_price > 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_stock_nonnegative CHECK (current_stock >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT variants_threshold_nonnegative CHECK (low_stock_threshold >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
