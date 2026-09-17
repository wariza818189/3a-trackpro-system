<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->decimal('opening_cash', 16, 2);
            $table->foreignId('opened_by');
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->timestamps();

            $table->unique('active_slot', 'cash_register_sessions_active_slot_unique');
            $table->index(['opened_by', 'opened_at'], 'cash_register_sessions_opened_by_opened_at_index');
            $table->index(['closed_by', 'closed_at'], 'cash_register_sessions_closed_by_closed_at_index');
            $table->index('closed_at', 'cash_register_sessions_closed_at_index');

            $table->foreign('opened_by', 'cash_register_sessions_opened_by_foreign')
                ->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('closed_by', 'cash_register_sessions_closed_by_foreign')
                ->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement('ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_opening_cash_nonnegative CHECK (opening_cash >= 0)');
        DB::statement('ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_state_consistent CHECK ((active_slot IS NOT NULL AND active_slot = 1 AND closed_by IS NULL AND closed_at IS NULL) OR (active_slot IS NULL AND closed_by IS NOT NULL AND closed_at IS NOT NULL))');
        DB::statement('ALTER TABLE cash_register_sessions ADD CONSTRAINT cash_register_sessions_close_time_ordered CHECK (closed_at IS NULL OR closed_at >= opened_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
