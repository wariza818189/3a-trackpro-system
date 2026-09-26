<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Authentication tests require the in-memory SQLite database.');
        }

        $this->withoutVite();

        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_09_05_000001_create_categories_table.php'))->up();
        (require database_path('migrations/2026_09_05_000002_create_products_table.php'))->up();

        // Behavior-only SQLite scaffolding for authenticated Dashboard requests.
        // MySQL constraints remain covered solely by the guarded MySQL suite.
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->string('size', 80)->default('');
            $table->string('type_series', 80)->default('');
            $table->string('thickness', 40)->default('');
            $table->string('unit', 30);
            $table->string('quantity_mode')->default('whole');
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('low_stock_threshold', 14, 3)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->decimal('opening_cash', 16, 2);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('checkout_token')->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cash_register_session_id')->nullable()->constrained('cash_register_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->string('status')->default('completed');
            $table->decimal('total_amount', 16, 2);
            $table->decimal('cash_received', 16, 2);
            $table->decimal('change_amount', 16, 2);
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name_snapshot', 150);
            $table->string('size_snapshot', 80)->default('');
            $table->string('type_series_snapshot', 80)->default('');
            $table->string('thickness_snapshot', 40)->default('');
            $table->string('unit_snapshot', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamp('created_at')->nullable();
            $table->unique(['sale_id', 'product_variant_id']);
        });
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('movement_type');
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_change', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete()->restrictOnUpdate();
            // Authentication fixtures contain no Restocks or movement records.
            $table->unsignedBigInteger('restock_item_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
