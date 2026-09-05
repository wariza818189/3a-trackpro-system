<?php

namespace Tests\MySql;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SchemaIntegrityTest extends MySqlTestCase
{
    private int $adminId;

    private int $staffId;

    private int $categoryId;

    private int $productId;

    private int $variantId;

    private int $saleId;

    private int $saleItemId;

    private int $restockId;

    private int $restockItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = $this->insertUser('admin_fixture', 'admin');
        $this->staffId = $this->insertUser('staff_fixture', 'staff');
        $this->categoryId = DB::table('categories')->insertGetId(['name' => 'Fasteners']);
        $this->productId = DB::table('products')->insertGetId([
            'category_id' => $this->categoryId,
            'name' => 'Machine Bolt',
        ]);
        $this->variantId = DB::table('product_variants')->insertGetId($this->variant());
        $this->saleId = DB::table('sales')->insertGetId($this->sale());
        $this->saleItemId = DB::table('sale_items')->insertGetId($this->saleItem());
        $this->restockId = DB::table('restocks')->insertGetId($this->restock());
        $this->restockItemId = DB::table('restock_items')->insertGetId($this->restockItem());
    }

    public function test_schema_inventory_engine_collation_checks_and_foreign_keys(): void
    {
        $this->assertSame([
            'audit_logs',
            'categories',
            'migrations',
            'product_variants',
            'products',
            'restock_items',
            'restocks',
            'sale_items',
            'sales',
            'stock_movements',
            'users',
        ], $this->tableInventory());

        $metadata = DB::select(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation '
            .'FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [self::DATABASE, 'BASE TABLE']
        );

        foreach ($metadata as $table) {
            $this->assertSame('InnoDB', $table->engine, $table->table_name);
            $this->assertSame('utf8mb4_unicode_ci', $table->table_collation, $table->table_name);
        }

        $checkNames = array_map(
            static fn (object $row): string => $row->constraint_name,
            DB::select(
                'SELECT CONSTRAINT_NAME AS constraint_name FROM information_schema.TABLE_CONSTRAINTS '
                .'WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_TYPE = ? ORDER BY CONSTRAINT_NAME',
                [self::DATABASE, 'CHECK']
            )
        );

        $this->assertSame([
            'audit_entity_pair_consistent',
            'movements_after_nonnegative',
            'movements_before_nonnegative',
            'movements_quantity_balanced',
            'movements_type_consistent',
            'restock_items_cost_nonnegative',
            'restock_items_quantity_positive',
            'restock_items_total_balanced',
            'restocks_cost_nonnegative',
            'sale_items_price_positive',
            'sale_items_quantity_positive',
            'sale_items_total_balanced',
            'sales_cash_sufficient',
            'sales_change_balanced',
            'sales_total_positive',
            'sales_void_consistent',
            'variants_cost_nonnegative',
            'variants_price_positive',
            'variants_stock_nonnegative',
            'variants_threshold_nonnegative',
        ], $checkNames);

        $rules = DB::select(
            'SELECT DELETE_RULE AS delete_rule, UPDATE_RULE AS update_rule '
            .'FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?',
            [self::DATABASE]
        );

        $this->assertCount(14, $rules);
        foreach ($rules as $rule) {
            $this->assertSame('RESTRICT', $rule->delete_rule);
            $this->assertSame('RESTRICT', $rule->update_rule);
        }
    }

    public function test_users_accept_valid_roles_and_reject_duplicate_or_invalid_values(): void
    {
        $this->insertUser('another_admin', 'admin');
        $this->insertUser('another_staff', 'staff');

        $this->assertRejected(fn () => $this->insertUser('admin_fixture', 'staff'));
        $this->assertRejected(fn () => $this->insertUser('bad_role', 'manager'));
        $this->assertRejected(fn () => $this->insertUser('bad_status', 'staff', 'pending'));
    }

    public function test_product_variant_constraints_uniqueness_and_decimal_quantity(): void
    {
        $id = DB::table('product_variants')->insertGetId($this->variant([
            'size' => 'M8',
            'current_stock' => '123456.789',
            'low_stock_threshold' => '0.125',
        ]));

        $stored = DB::table('product_variants')->find($id);
        $this->assertSame('123456.789', $stored->current_stock);
        $this->assertSame('0.125', $stored->low_stock_threshold);

        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant()));
        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant(['size' => 'negative-cost', 'cost_price' => '-0.01'])));
        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant(['size' => 'zero-price', 'selling_price' => '0.00'])));
        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant(['size' => 'negative-price', 'selling_price' => '-0.01'])));
        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant(['size' => 'negative-stock', 'current_stock' => '-0.001'])));
        $this->assertRejected(fn () => DB::table('product_variants')->insert($this->variant(['size' => 'negative-threshold', 'low_stock_threshold' => '-0.001'])));
    }

    public function test_sales_accept_valid_cash_header_and_enforce_money_void_and_token_rules(): void
    {
        $validId = DB::table('sales')->insertGetId($this->sale(['checkout_token' => (string) Str::uuid()]));
        $this->assertSame('25.00', DB::table('sales')->find($validId)->change_amount);

        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'total_amount' => '0.00', 'cash_received' => '0.00', 'change_amount' => '0.00'])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'cash_received' => '74.99', 'change_amount' => '-0.01'])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'change_amount' => '24.99'])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'void_reason' => 'mistake', 'voided_by' => $this->adminId, 'voided_at' => now()])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'status' => 'voided'])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale(['checkout_token' => (string) Str::uuid(), 'status' => 'voided', 'void_reason' => '   ', 'voided_by' => $this->adminId, 'voided_at' => now()])));
        $this->assertRejected(fn () => DB::table('sales')->insert($this->sale()));
    }

    public function test_sale_items_enforce_positive_values_and_two_decimal_line_rounding(): void
    {
        $this->assertSame('0.750', DB::table('sale_items')->find($this->saleItemId)->quantity);
        $this->assertSame('75.00', DB::table('sale_items')->find($this->saleItemId)->line_total);

        $secondVariant = DB::table('product_variants')->insertGetId($this->variant(['size' => 'M10']));
        $valid = $this->saleItem([
            'product_variant_id' => $secondVariant,
            'quantity' => '0.333',
            'unit_price' => '10.00',
            'line_total' => '3.33',
        ]);
        DB::table('sale_items')->insert($valid);

        $this->assertRejected(fn () => DB::table('sale_items')->insert($this->saleItem(['product_variant_id' => $secondVariant, 'quantity' => '0.000', 'line_total' => '0.00'])));
        $this->assertRejected(fn () => DB::table('sale_items')->insert($this->saleItem(['product_variant_id' => $secondVariant, 'quantity' => '-0.001', 'line_total' => '-0.10'])));
        $this->assertRejected(fn () => DB::table('sale_items')->insert($this->saleItem(['product_variant_id' => $secondVariant, 'unit_price' => '0.00', 'line_total' => '0.00'])));
        $this->assertRejected(fn () => DB::table('sale_items')->insert($this->saleItem(['product_variant_id' => $secondVariant, 'unit_price' => '-1.00', 'line_total' => '-0.75'])));
        $this->assertRejected(fn () => DB::table('sale_items')->insert($this->saleItem(['product_variant_id' => $secondVariant, 'line_total' => '74.99'])));
    }

    public function test_restocks_and_items_enforce_tokens_costs_quantities_and_totals(): void
    {
        $this->assertRejected(fn () => DB::table('restocks')->insert($this->restock()));
        $this->assertRejected(fn () => DB::table('restocks')->insert($this->restock(['submission_token' => (string) Str::uuid(), 'total_cost' => '-0.01'])));

        $secondVariant = DB::table('product_variants')->insertGetId($this->variant(['size' => 'M12']));
        $this->assertRejected(fn () => DB::table('restock_items')->insert($this->restockItem(['product_variant_id' => $secondVariant, 'quantity' => '0.000'])));
        $this->assertRejected(fn () => DB::table('restock_items')->insert($this->restockItem(['product_variant_id' => $secondVariant, 'quantity' => '-0.001', 'line_total' => '-0.05'])));
        $this->assertRejected(fn () => DB::table('restock_items')->insert($this->restockItem(['product_variant_id' => $secondVariant, 'unit_cost' => '-0.01', 'line_total' => '-0.01'])));
        $this->assertRejected(fn () => DB::table('restock_items')->insert($this->restockItem(['product_variant_id' => $secondVariant, 'line_total' => '49.99'])));
    }

    public function test_stock_movements_accept_every_approved_shape(): void
    {
        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'INITIAL_STOCK',
            'quantity_before' => '0.000',
            'quantity_change' => '5.000',
            'quantity_after' => '5.000',
            'reason' => 'Opening count',
        ]));
        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'CORRECTION',
            'quantity_before' => '5.000',
            'quantity_change' => '-1.000',
            'quantity_after' => '4.000',
            'reason' => 'Damaged item',
        ]));
        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'RESTOCK',
            'quantity_before' => '4.000',
            'quantity_change' => '2.000',
            'quantity_after' => '6.000',
            'restock_item_id' => $this->restockItemId,
        ]));
        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'SALE',
            'quantity_before' => '6.000',
            'quantity_change' => '-0.750',
            'quantity_after' => '5.250',
            'sale_item_id' => $this->saleItemId,
        ]));
        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'SALE_VOID',
            'quantity_before' => '5.250',
            'quantity_change' => '0.750',
            'quantity_after' => '6.000',
            'sale_item_id' => $this->saleItemId,
        ]));

        $this->assertSame(5, DB::table('stock_movements')->count());
    }

    public function test_stock_movements_reject_invalid_quantities_reasons_directions_and_references(): void
    {
        $invalid = [
            ['movement_type' => 'INITIAL_STOCK', 'quantity_before' => '-1.000', 'quantity_change' => '2.000', 'quantity_after' => '1.000', 'reason' => 'Opening'],
            ['movement_type' => 'CORRECTION', 'quantity_before' => '1.000', 'quantity_change' => '-2.000', 'quantity_after' => '-1.000', 'reason' => 'Count'],
            ['movement_type' => 'INITIAL_STOCK', 'quantity_before' => '0.000', 'quantity_change' => '1.000', 'quantity_after' => '2.000', 'reason' => 'Opening'],
            ['movement_type' => 'SALE', 'quantity_before' => '1.000', 'quantity_change' => '1.000', 'quantity_after' => '2.000', 'sale_item_id' => $this->saleItemId],
            ['movement_type' => 'RESTOCK', 'quantity_before' => '2.000', 'quantity_change' => '-1.000', 'quantity_after' => '1.000', 'restock_item_id' => $this->restockItemId],
            ['movement_type' => 'SALE_VOID', 'quantity_before' => '2.000', 'quantity_change' => '-1.000', 'quantity_after' => '1.000', 'sale_item_id' => $this->saleItemId],
            ['movement_type' => 'CORRECTION', 'quantity_before' => '1.000', 'quantity_change' => '0.000', 'quantity_after' => '1.000', 'reason' => 'Count'],
            ['movement_type' => 'INITIAL_STOCK', 'quantity_before' => '1.000', 'quantity_change' => '1.000', 'quantity_after' => '2.000', 'reason' => 'Opening'],
            ['movement_type' => 'INITIAL_STOCK', 'quantity_before' => '0.000', 'quantity_change' => '1.000', 'quantity_after' => '1.000', 'reason' => null],
            ['movement_type' => 'CORRECTION', 'quantity_before' => '1.000', 'quantity_change' => '1.000', 'quantity_after' => '2.000', 'reason' => '   '],
            ['movement_type' => 'SALE', 'quantity_before' => '2.000', 'quantity_change' => '-1.000', 'quantity_after' => '1.000', 'restock_item_id' => $this->restockItemId],
            ['movement_type' => 'RESTOCK', 'quantity_before' => '1.000', 'quantity_change' => '1.000', 'quantity_after' => '2.000', 'sale_item_id' => $this->saleItemId],
        ];

        foreach ($invalid as $attributes) {
            $this->assertRejected(fn () => DB::table('stock_movements')->insert($this->movement($attributes)));
        }
    }

    public function test_history_foreign_keys_restrict_destructive_deletes(): void
    {
        $this->assertRejected(fn () => DB::table('product_variants')->where('id', $this->variantId)->delete());
        $this->assertRejected(fn () => DB::table('sales')->where('id', $this->saleId)->delete());
        $this->assertRejected(fn () => DB::table('restocks')->where('id', $this->restockId)->delete());

        DB::table('stock_movements')->insert($this->movement([
            'movement_type' => 'INITIAL_STOCK',
            'quantity_before' => '0.000',
            'quantity_change' => '0.000',
            'quantity_after' => '0.000',
            'reason' => 'Opening count',
        ]));

        $this->assertRejected(fn () => DB::table('product_variants')->where('id', $this->variantId)->delete());
        $this->assertRejected(fn () => DB::table('users')->where('id', $this->staffId)->delete());
    }

    public function test_audit_entity_pair_and_json_constraints(): void
    {
        $entityless = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->staffId,
            'action' => 'LOGIN',
            'entity_type' => null,
            'entity_id' => null,
            'after_values' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
            'description' => 'Signed in',
        ]);
        $populated = DB::table('audit_logs')->insertGetId([
            'user_id' => $this->adminId,
            'action' => 'UPDATE_PRODUCT',
            'entity_type' => 'product',
            'entity_id' => $this->productId,
            'before_values' => json_encode(['status' => 'archived'], JSON_THROW_ON_ERROR),
            'after_values' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
            'description' => 'Restored product',
        ]);

        $storedJson = DB::table('audit_logs')->where('id', $entityless)->value('after_values');
        $this->assertSame(['status' => 'active'], json_decode($storedJson, true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame('product', DB::table('audit_logs')->where('id', $populated)->value('entity_type'));

        $this->assertRejected(fn () => DB::table('audit_logs')->insert([
            'user_id' => $this->staffId,
            'action' => 'BAD_PAIR',
            'entity_type' => 'product',
            'entity_id' => null,
            'description' => 'Invalid',
        ]));
        $this->assertRejected(fn () => DB::table('audit_logs')->insert([
            'user_id' => $this->staffId,
            'action' => 'BAD_PAIR',
            'entity_type' => null,
            'entity_id' => $this->productId,
            'description' => 'Invalid',
        ]));
    }

    public function test_laravel_timestamps_round_trip_in_manila_time(): void
    {
        $before = CarbonImmutable::now('Asia/Manila')->subSecond();
        $user = User::create([
            'name' => 'Timestamp Test',
            'username' => 'timestamp_test',
            'password' => 'test-only-password',
            'role' => 'staff',
            'status' => 'active',
        ])->fresh();
        $after = CarbonImmutable::now('Asia/Manila')->addSecond();

        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
        $this->assertNotNull($user);
        $this->assertTrue($user->created_at->betweenIncluded($before, $after));
        $this->assertTrue($user->updated_at->betweenIncluded($before, $after));
        $this->assertSame(
            $user->created_at->format('Y-m-d H:i:s'),
            (string) DB::table('users')->where('id', $user->id)->value('created_at')
        );
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('MySQL unexpectedly accepted an invalid row or destructive delete.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->errorInfo);
        }
    }

    private function insertUser(string $username, string $role, string $status = 'active'): int
    {
        return DB::table('users')->insertGetId([
            'name' => Str::headline($username),
            'username' => $username,
            'password' => password_hash('test-only-password', PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function variant(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->productId,
            'size' => 'M6',
            'type_series' => 'Grade 8.8',
            'thickness' => '',
            'unit' => 'piece',
            'quantity_mode' => 'whole',
            'cost_price' => '50.00',
            'selling_price' => '100.00',
            'current_stock' => '10.000',
            'low_stock_threshold' => '2.000',
            'status' => 'active',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function sale(array $overrides = []): array
    {
        return array_merge([
            'checkout_token' => '10000000-0000-4000-8000-000000000001',
            'recorded_by' => $this->staffId,
            'status' => 'completed',
            'total_amount' => '75.00',
            'cash_received' => '100.00',
            'change_amount' => '25.00',
            'void_reason' => null,
            'voided_by' => null,
            'voided_at' => null,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function saleItem(array $overrides = []): array
    {
        return array_merge([
            'sale_id' => $this->saleId,
            'product_variant_id' => $this->variantId,
            'product_name_snapshot' => 'Machine Bolt',
            'size_snapshot' => 'M6',
            'type_series_snapshot' => 'Grade 8.8',
            'thickness_snapshot' => '',
            'unit_snapshot' => 'piece',
            'quantity' => '0.750',
            'unit_price' => '100.00',
            'line_total' => '75.00',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function restock(array $overrides = []): array
    {
        return array_merge([
            'submission_token' => '20000000-0000-4000-8000-000000000001',
            'recorded_by' => $this->staffId,
            'total_cost' => '50.00',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function restockItem(array $overrides = []): array
    {
        return array_merge([
            'restock_id' => $this->restockId,
            'product_variant_id' => $this->variantId,
            'product_name_snapshot' => 'Machine Bolt',
            'size_snapshot' => 'M6',
            'type_series_snapshot' => 'Grade 8.8',
            'thickness_snapshot' => '',
            'unit_snapshot' => 'piece',
            'quantity' => '1.000',
            'unit_cost' => '50.00',
            'line_total' => '50.00',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function movement(array $overrides): array
    {
        return array_merge([
            'product_variant_id' => $this->variantId,
            'performed_by' => $this->staffId,
            'sale_item_id' => null,
            'restock_item_id' => null,
            'reason' => null,
        ], $overrides);
    }
}
