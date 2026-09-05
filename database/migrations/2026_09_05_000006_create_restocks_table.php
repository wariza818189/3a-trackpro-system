<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->text('reference_text')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 16, 2);
            $table->timestamp('created_at')->nullable();
            $table->index(['recorded_by', 'created_at']);
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE restocks ADD CONSTRAINT restocks_cost_nonnegative CHECK (total_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('restocks');
    }
};
