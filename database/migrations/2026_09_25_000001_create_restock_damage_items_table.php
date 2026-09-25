<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_damage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restock_id')->constrained('restocks')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('damaged_quantity', 14, 3);
            $table->text('damage_note');
            $table->timestamp('created_at')->nullable();
            $table->unique(['restock_id', 'purchase_order_item_id']);
            $table->index(['purchase_order_item_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE restock_damage_items ADD CONSTRAINT restock_damage_items_quantity_positive CHECK (damaged_quantity > 0)');
        DB::statement('ALTER TABLE restock_damage_items ADD CONSTRAINT restock_damage_items_note_nonblank CHECK (CHAR_LENGTH(TRIM(damage_note)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_damage_items');
    }
};
