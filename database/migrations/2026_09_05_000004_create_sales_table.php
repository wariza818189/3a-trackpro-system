<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('checkout_token')->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->decimal('total_amount', 16, 2);
            $table->decimal('cash_received', 16, 2);
            $table->decimal('change_amount', 16, 2);
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['recorded_by', 'created_at']);
        });

        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_total_positive CHECK (total_amount > 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_cash_sufficient CHECK (cash_received >= total_amount)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_change_balanced CHECK (change_amount = cash_received - total_amount)');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_void_consistent CHECK ((status = 'completed' AND void_reason IS NULL AND voided_by IS NULL AND voided_at IS NULL) OR (status = 'voided' AND void_reason IS NOT NULL AND CHAR_LENGTH(TRIM(void_reason)) > 0 AND voided_by IS NOT NULL AND voided_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
