<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('supplier_name', 150);
            $table->enum('status', ['pending', 'partially_received', 'completed', 'closed_with_remainder'])
                ->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });

        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_supplier_name_nonblank CHECK (CHAR_LENGTH(TRIM(supplier_name)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
