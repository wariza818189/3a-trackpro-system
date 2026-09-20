<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Procurement\CreatePurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PurchaseOrderCreationTest extends PurchaseOrderCreationTestCase
{
    public function test_active_admin_creates_exact_canonical_one_line_purchase_order_without_inventory_mutation(): void
    {
        $product = $this->product($this->category(), ['name' => 'Machine Bolt']);
        $variant = $this->variant($product, [
            'size' => 'M8',
            'type_series' => 'Grade 8.8',
            'thickness' => '2 mm',
            'unit' => 'piece',
            'current_stock' => '3.000',
            'cost_price' => '90.00',
        ]);
        $this->initialize($variant, quantity: '3.000');
        $token = Str::uuid()->toString();

        $purchaseOrder = $this->service()->execute(
            $this->admin,
            '  '.strtoupper($token).'  ',
            "  ACME\u{2003}Industrial\nSupply  ",
            "  Urgent\n planning\torder  ",
            [[
                'product_variant_id' => $variant->getKey(),
                'ordered_quantity' => '01',
                'expected_unit_cost' => '025.5',
            ]],
        );

        $this->assertTrue($purchaseOrder->wasRecentlyCreated);
        $this->assertTrue($purchaseOrder->relationLoaded('items'));
        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(1, PurchaseOrderItem::query()->count());
        $this->assertSame(strtolower($token), $purchaseOrder->submission_token);
        $this->assertSame($this->admin->id, $purchaseOrder->created_by);
        $this->assertSame('ACME Industrial Supply', $purchaseOrder->supplier_name);
        $this->assertSame('Urgent planning order', $purchaseOrder->notes);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $purchaseOrder->status);
        $this->assertNull($purchaseOrder->parent_purchase_order_id);

        $item = $purchaseOrder->items->sole();
        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame('Machine Bolt', $item->product_name_snapshot);
        $this->assertSame('M8', $item->size_snapshot);
        $this->assertSame('Grade 8.8', $item->type_series_snapshot);
        $this->assertSame('2 mm', $item->thickness_snapshot);
        $this->assertSame('piece', $item->unit_snapshot);
        $this->assertSame('1.000', $item->ordered_quantity);
        $this->assertSame('25.50', $item->expected_unit_cost);
        $this->assertSame('3.000', $variant->fresh()->current_stock);
        $this->assertSame('90.00', $variant->fresh()->cost_price);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
    }

    public function test_actor_and_submission_token_are_defensively_validated_from_persisted_state(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        $payload = $this->payload($variant);

        $staff = User::factory()->create();
        $disabled = User::factory()->admin()->disabled()->create();
        $unpersisted = new User([
            'name' => 'Unpersisted',
            'username' => 'unpersisted',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
        foreach ([$staff, $disabled, $unpersisted] as $actor) {
            $this->assertServiceValidation(
                fn () => $this->executePayload($actor, $payload),
                'actor',
            );
        }

        foreach ([null, 123, '', 'not-a-uuid', Str::uuid()->toString().'x'] as $token) {
            $this->assertServiceValidation(
                fn () => $this->executePayload($this->admin, array_replace($payload, ['submission_token' => $token])),
                'submission_token',
            );
        }

        $this->assertSame(0, PurchaseOrder::query()->count());
        $created = $this->executePayload($this->admin, $payload);
        $this->assertSame($this->admin->id, $created->created_by);
    }

    public function test_low_healthy_covered_and_zero_initialized_variants_are_all_eligible(): void
    {
        $product = $this->product($this->category());
        $low = $this->variant($product, ['size' => 'Low', 'current_stock' => '1.000', 'low_stock_threshold' => '5.000']);
        $healthy = $this->variant($product, ['size' => 'Healthy', 'current_stock' => '20.000', 'low_stock_threshold' => '5.000']);
        $covered = $this->variant($product, ['size' => 'Covered', 'current_stock' => '0.000', 'low_stock_threshold' => '5.000']);
        $zeroInitialized = $this->variant($product, ['size' => 'Zero opening', 'current_stock' => '0.000']);
        foreach ([$low, $healthy, $covered, $zeroInitialized] as $variant) {
            $this->initialize($variant, quantity: '0.000');
        }
        $this->coverVariant($covered);

        $purchaseOrder = $this->service()->execute(
            $this->admin,
            Str::uuid()->toString(),
            'Eligibility Supplier',
            null,
            collect([$healthy, $zeroInitialized, $covered, $low])->map(fn (ProductVariant $variant): array => [
                'product_variant_id' => $variant->id,
                'ordered_quantity' => '1',
                'expected_unit_cost' => '0',
            ])->all(),
        );

        $this->assertSame(
            collect([$low->id, $healthy->id, $covered->id, $zeroInitialized->id])->sort()->values()->all(),
            $purchaseOrder->items->pluck('product_variant_id')->all(),
        );
        $this->assertSame(2, PurchaseOrder::query()->count(), 'The existing covering PO and the new PO should both remain.');
    }

    public function test_uninitialized_or_inactive_hierarchy_variants_are_rejected_atomically(): void
    {
        $activeProduct = $this->product($this->category());
        $uninitialized = $this->variant($activeProduct, ['size' => 'Uninitialized']);
        $inactiveVariant = $this->variant($activeProduct, [
            'size' => 'Inactive Variant',
            'status' => ProductVariant::STATUS_ARCHIVED,
        ]);
        $inactiveProductVariant = $this->variant($this->product($this->category(), [
            'status' => Product::STATUS_ARCHIVED,
        ]), ['size' => 'Inactive Product']);
        $inactiveCategoryVariant = $this->variant($this->product($this->category([
            'status' => Category::STATUS_ARCHIVED,
        ])), ['size' => 'Inactive Category']);
        foreach ([$inactiveVariant, $inactiveProductVariant, $inactiveCategoryVariant] as $variant) {
            $this->initialize($variant);
        }

        foreach ([$uninitialized, $inactiveVariant, $inactiveProductVariant, $inactiveCategoryVariant] as $variant) {
            $this->assertServiceValidation(
                fn () => $this->executePayload($this->admin, $this->payload($variant)),
                'items.0.product_variant_id',
            );
        }

        $this->assertSame(0, PurchaseOrder::query()->count());
        $this->assertSame(0, PurchaseOrderItem::query()->count());
    }

    public function test_quantity_canonicalization_enforces_variant_modes_and_decimal_range(): void
    {
        $product = $this->product($this->category());
        $whole = $this->variant($product, ['size' => 'Whole']);
        $fractional = $this->variant($product, [
            'size' => 'Fractional',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
        ]);
        $this->initialize($whole);
        $this->initialize($fractional);

        foreach (['1', '1.000'] as $quantity) {
            $created = $this->executePayload($this->admin, $this->payload($whole, ['items' => [[
                'product_variant_id' => $whole->id,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => '1',
            ]]]));
            $this->assertSame('1.000', $created->items->sole()->ordered_quantity);
        }

        foreach (['1.2', '1.250', '99999999999.999'] as $quantity) {
            $created = $this->executePayload($this->admin, $this->payload($fractional, ['items' => [[
                'product_variant_id' => $fractional->id,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => '1',
            ]]]));
            $expected = match ($quantity) {
                '1.2' => '1.200',
                default => $quantity,
            };
            $this->assertSame($expected, $created->items->sole()->ordered_quantity);
        }

        foreach (['1.001', '0', '-1', '+1'] as $quantity) {
            $this->assertServiceValidation(fn () => $this->executePayload($this->admin, $this->payload($whole, ['items' => [[
                'product_variant_id' => $whole->id,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => '1',
            ]]])), 'items.0.ordered_quantity');
        }
        foreach (['0', '1.0001', '100000000000.000', '1e3', '1,000'] as $quantity) {
            $this->assertServiceValidation(fn () => $this->executePayload($this->admin, $this->payload($fractional, ['items' => [[
                'product_variant_id' => $fractional->id,
                'ordered_quantity' => $quantity,
                'expected_unit_cost' => '1',
            ]]])), 'items.0.ordered_quantity');
        }
    }

    public function test_expected_cost_canonicalization_accepts_zero_and_maximum_and_rejects_invalid_forms(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);

        $valid = [
            ['0', '0.00'],
            ['25', '25.00'],
            ['025.5', '25.50'],
            ['25.50', '25.50'],
            ['9999999999.99', '9999999999.99'],
        ];
        foreach ($valid as [$cost, $expected]) {
            $created = $this->executePayload($this->admin, $this->payload($variant, ['items' => [[
                'product_variant_id' => $variant->id,
                'ordered_quantity' => '1',
                'expected_unit_cost' => $cost,
            ]]]));
            $this->assertSame($expected, $created->items->sole()->expected_unit_cost);
        }

        foreach (['1.001', '-1', '+1', '10000000000.00', '1e3', '1,000', '$25.00'] as $cost) {
            $this->assertServiceValidation(fn () => $this->executePayload($this->admin, $this->payload($variant, ['items' => [[
                'product_variant_id' => $variant->id,
                'ordered_quantity' => '1',
                'expected_unit_cost' => $cost,
            ]]])), 'items.0.expected_unit_cost');
        }
    }

    public function test_supplier_and_notes_use_unicode_aware_canonical_normalization_and_limits(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);

        $canonical = $this->service()->execute(
            $this->admin,
            Str::uuid()->toString(),
            "  Supplier\u{00A0}\u{2003}Name  ",
            "  First\n\tsecond  ",
            $this->payload($variant)['items'],
        );
        $this->assertSame('Supplier Name', $canonical->supplier_name);
        $this->assertSame('First second', $canonical->notes);

        $blankNotes = $this->service()->execute(
            $this->admin,
            Str::uuid()->toString(),
            str_repeat('界', 150),
            " \u{2003}\n ",
            $this->payload($variant)['items'],
        );
        $this->assertNull($blankNotes->notes);
        $this->assertSame(150, mb_strlen($blankNotes->supplier_name));

        foreach ([null, '', " \u{2003} ", str_repeat('界', 151)] as $supplier) {
            $this->assertServiceValidation(fn () => $this->service()->execute(
                $this->admin, Str::uuid()->toString(), $supplier, null, $this->payload($variant)['items'],
            ), 'supplier_name');
        }
        $this->assertServiceValidation(fn () => $this->service()->execute(
            $this->admin, Str::uuid()->toString(), 'Supplier', str_repeat('界', 1001), $this->payload($variant)['items'],
        ), 'notes');
    }

    public function test_item_collection_shape_ids_and_duplicates_are_rejected_before_persistence(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        foreach ([null, 'not-items', [], array_fill(0, 101, [
            'product_variant_id' => $variant->id,
            'ordered_quantity' => '1',
            'expected_unit_cost' => '1',
        ])] as $items) {
            $this->assertServiceValidation(fn () => $this->service()->execute(
                $this->admin, Str::uuid()->toString(), 'Supplier', null, $items,
            ), 'items');
        }

        $invalidLines = [
            ['not-a-line'],
            [['product_variant_id' => $variant->id, 'ordered_quantity' => '1']],
            [['product_variant_id' => $variant->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1', 'unexpected' => true]],
        ];
        foreach ($invalidLines as $items) {
            $this->assertServiceValidation(fn () => $this->service()->execute(
                $this->admin, Str::uuid()->toString(), 'Supplier', null, $items,
            ), 'items.0');
        }

        foreach ([0, -1, 'variant', null] as $variantId) {
            $this->assertServiceValidation(fn () => $this->service()->execute(
                $this->admin, Str::uuid()->toString(), 'Supplier', null, [[
                    'product_variant_id' => $variantId,
                    'ordered_quantity' => '1',
                    'expected_unit_cost' => '1',
                ]],
            ), 'items.0.product_variant_id');
        }

        $this->assertServiceValidation(fn () => $this->service()->execute(
            $this->admin, Str::uuid()->toString(), 'Supplier', null, [
                ['product_variant_id' => $variant->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1'],
                ['product_variant_id' => $variant->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '2'],
            ],
        ), 'items.1.product_variant_id');
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_multi_line_creation_uses_stable_variant_order_and_each_authoritative_snapshot(): void
    {
        $firstProduct = $this->product($this->category(), ['name' => 'First Product']);
        $secondProduct = $this->product($this->category(), ['name' => 'Second Product']);
        $whole = $this->variant($firstProduct, ['size' => 'Whole', 'type_series' => '', 'thickness' => '', 'unit' => 'piece']);
        $fractional = $this->variant($secondProduct, [
            'size' => 'Bulk', 'type_series' => 'Fine', 'thickness' => '3 mm',
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'current_stock' => '4.500',
        ]);
        $this->initialize($whole);
        $this->initialize($fractional, quantity: '4.500');
        $beforeStocks = [$whole->current_stock, $fractional->current_stock];

        $purchaseOrder = $this->service()->execute(
            $this->admin,
            Str::uuid()->toString(),
            'Multi Supplier',
            null,
            [
                ['product_variant_id' => $fractional->id, 'ordered_quantity' => '1.25', 'expected_unit_cost' => '15.5'],
                ['product_variant_id' => $whole->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '10'],
            ],
        );

        $expectedIds = collect([$whole->id, $fractional->id])->sort()->values()->all();
        $this->assertSame($expectedIds, $purchaseOrder->items->pluck('product_variant_id')->all());
        $this->assertSame($expectedIds, PurchaseOrderItem::query()->orderBy('id')->pluck('product_variant_id')->all());
        $this->assertSame(['First Product', 'Second Product'], $purchaseOrder->items->pluck('product_name_snapshot')->all());
        $this->assertSame(['2.000', '1.250'], $purchaseOrder->items->pluck('ordered_quantity')->all());
        $this->assertSame($beforeStocks, [$whole->fresh()->current_stock, $fractional->fresh()->current_stock]);
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_unexpected_snapshot_and_server_owned_keys_are_rejected(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        $item = [
            'product_variant_id' => $variant->id,
            'ordered_quantity' => '1',
            'expected_unit_cost' => '1',
            'product_name_snapshot' => 'Spoofed',
            'unit_snapshot' => 'kg',
            'status' => PurchaseOrder::STATUS_COMPLETED,
            'created_by' => 999,
            'parent_purchase_order_id' => 999,
        ];

        $this->assertServiceValidation(fn () => $this->service()->execute(
            $this->admin, Str::uuid()->toString(), 'Supplier', null, [$item],
        ), 'items.0');
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_semantically_equivalent_replay_returns_same_order_without_rewrites(): void
    {
        $product = $this->product($this->category(), ['name' => 'Replay Product']);
        $first = $this->variant($product, ['size' => 'First']);
        $second = $this->variant($product, ['size' => 'Second', 'unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($first);
        $this->initialize($second);
        $token = Str::uuid()->toString();
        $original = $this->service()->execute(
            $this->admin,
            $token,
            'Replay Supplier',
            'Equivalent note',
            [
                ['product_variant_id' => $second->id, 'ordered_quantity' => '2.5', 'expected_unit_cost' => '025.5'],
                ['product_variant_id' => $first->id, 'ordered_quantity' => '01', 'expected_unit_cost' => '10'],
            ],
        );
        $originalSnapshots = $original->items->pluck('product_name_snapshot')->all();

        $replay = $this->service()->execute(
            $this->admin,
            strtoupper($token),
            " Replay\u{2003} Supplier ",
            " Equivalent\n note ",
            [
                ['product_variant_id' => $first->id, 'ordered_quantity' => '1.000', 'expected_unit_cost' => '010.00'],
                ['product_variant_id' => $second->id, 'ordered_quantity' => '02.500', 'expected_unit_cost' => '25.50'],
            ],
        );

        $this->assertSame($original->id, $replay->id);
        $this->assertFalse($replay->wasRecentlyCreated);
        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(2, PurchaseOrderItem::query()->count());
        $this->assertSame($originalSnapshots, $replay->items->pluck('product_name_snapshot')->all());
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_same_token_conflicts_for_any_changed_creation_semantics_or_actor(): void
    {
        $product = $this->product($this->category());
        $variant = $this->variant($product, ['size' => 'Original']);
        $otherVariant = $this->variant($product, ['size' => 'Other']);
        $this->initialize($variant);
        $this->initialize($otherVariant);
        $otherAdmin = User::factory()->admin()->create();
        $payload = $this->payload($variant);
        $original = $this->executePayload($this->admin, $payload);

        $changes = [
            ['supplier_name' => 'Different Supplier'],
            ['notes' => 'Different note'],
            ['items' => [['product_variant_id' => $otherVariant->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '25.50']]],
            ['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '3', 'expected_unit_cost' => '25.50']]],
            ['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '26.00']]],
        ];
        foreach ($changes as $change) {
            $this->assertServiceValidation(
                fn () => $this->executePayload($this->admin, array_replace($payload, $change)),
                'submission_token',
            );
        }
        $this->assertServiceValidation(
            fn () => $this->executePayload($otherAdmin, $payload),
            'submission_token',
        );

        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(1, PurchaseOrderItem::query()->count());
        $this->assertSame($original->id, PurchaseOrder::query()->sole()->id);
    }

    public function test_historical_replay_ignores_later_catalog_ineligibility_and_lifecycle_status(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $variant = $this->variant($product);
        $this->initialize($variant);
        $payload = $this->payload($variant);
        $original = $this->executePayload($this->admin, $payload);

        $variant->status = ProductVariant::STATUS_ARCHIVED;
        $variant->save();
        $product->status = Product::STATUS_ARCHIVED;
        $product->save();
        $category->status = Category::STATUS_ARCHIVED;
        $category->save();
        $original->status = PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
        $original->save();

        $replay = $this->executePayload($this->admin, $payload);
        $this->assertSame($original->id, $replay->id);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $replay->status);
        $this->assertFalse($replay->wasRecentlyCreated);

        $newPayload = array_replace($payload, ['submission_token' => Str::uuid()->toString()]);
        $this->assertServiceValidation(
            fn () => $this->executePayload($this->admin, $newPayload),
            'items.0.product_variant_id',
        );
        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_domain_validation_failure_in_one_line_leaves_no_partial_header_or_items(): void
    {
        $product = $this->product($this->category());
        $valid = $this->variant($product, ['size' => 'Valid']);
        $uninitialized = $this->variant($product, ['size' => 'Uninitialized']);
        $this->initialize($valid);

        $this->assertServiceValidation(fn () => $this->service()->execute(
            $this->admin,
            Str::uuid()->toString(),
            'Atomic Supplier',
            null,
            [
                ['product_variant_id' => $valid->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1'],
                ['product_variant_id' => $uninitialized->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1'],
            ],
        ), 'items.1.product_variant_id');

        $this->assertSame(0, PurchaseOrder::query()->count());
        $this->assertSame(0, PurchaseOrderItem::query()->count());
        $this->assertSame('0.000', $valid->fresh()->current_stock);
    }

    public function test_second_item_database_failure_rolls_back_header_and_first_item(): void
    {
        $product = $this->product($this->category());
        $first = $this->variant($product, ['size' => 'First']);
        $second = $this->variant($product, ['size' => 'Second']);
        $this->initialize($first);
        $this->initialize($second);
        $secondId = (int) max($first->id, $second->id);

        DB::unprepared(<<<SQL
            CREATE TRIGGER fail_second_purchase_order_item
            BEFORE INSERT ON purchase_order_items
            WHEN NEW.product_variant_id = {$secondId}
            BEGIN
                SELECT RAISE(ABORT, 'forced test item failure');
            END
        SQL);

        try {
            $this->service()->execute(
                $this->admin,
                Str::uuid()->toString(),
                'Rollback Supplier',
                null,
                [
                    ['product_variant_id' => $second->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1'],
                    ['product_variant_id' => $first->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1'],
                ],
            );
            $this->fail('Expected the test-only SQLite trigger to reject the second item insert.');
        } catch (QueryException) {
            $this->assertSame(0, PurchaseOrder::query()->count());
            $this->assertSame(0, PurchaseOrderItem::query()->count());
            $this->assertSame(2, StockMovement::query()->count());
            $this->assertSame('0.000', $first->fresh()->current_stock);
            $this->assertSame('0.000', $second->fresh()->current_stock);
        }
    }

    public function test_purchase_order_routes_have_the_exact_read_and_create_boundary(): void
    {
        $guestPayload = [
            'submission_token' => Str::uuid()->toString(),
            'supplier_name' => 'Supplier',
            'items' => [],
        ];
        $this->get(route('purchase-orders.create'))->assertRedirect('/login');
        $this->post(route('purchase-orders.store'), $guestPayload)->assertRedirect('/login');

        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('purchase-orders.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.store'), $guestPayload)->assertForbidden();

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get(route('purchase-orders.create'))->assertRedirect('/login');

        $this->actingAs($this->admin)->get(route('purchase-orders.create'))->assertOk();

        $routes = [
            'purchase-orders.index' => ['GET', 'HEAD'],
            'purchase-orders.create' => ['GET', 'HEAD'],
            'purchase-orders.store' => ['POST'],
            'purchase-orders.show' => ['GET', 'HEAD'],
        ];
        foreach ($routes as $name => $methods) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame($methods, $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('can:access-admin', $middleware);
        }
        foreach (['purchase-orders.edit', 'purchase-orders.update', 'purchase-orders.destroy'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }
    }

    public function test_create_page_groups_eligible_catalog_and_excludes_ineligible_variants(): void
    {
        $activeProduct = $this->product($this->category(['name' => 'Fasteners']), ['name' => 'Procurement Bolt']);
        $uncovered = $this->variant($activeProduct, ['size' => 'Uncovered 8mm', 'current_stock' => '0.000']);
        $covered = $this->variant($activeProduct, ['size' => 'Covered 10mm', 'current_stock' => '1.000']);
        $healthy = $this->variant($activeProduct, ['size' => 'Healthy 12mm', 'current_stock' => '20.000']);
        $uninitialized = $this->variant($activeProduct, ['size' => 'Uninitialized']);
        $inactive = $this->variant($activeProduct, ['size' => 'Inactive', 'status' => ProductVariant::STATUS_ARCHIVED]);
        $inactiveProductVariant = $this->variant($this->product($this->category(), [
            'name' => 'Archived Product',
            'status' => Product::STATUS_ARCHIVED,
        ]), ['size' => 'Archived Product Variant']);
        $inactiveCategoryVariant = $this->variant($this->product($this->category([
            'name' => 'Archived Category',
            'status' => Category::STATUS_ARCHIVED,
        ]), ['name' => 'Hidden Product']), ['size' => 'Archived Category Variant']);

        foreach ([$uncovered, $covered, $healthy, $inactive, $inactiveProductVariant, $inactiveCategoryVariant] as $variant) {
            $this->initialize($variant);
        }
        $this->coverVariant($covered);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.create'));

        $response->assertOk()
            ->assertSee('Priority — No Open PO Coverage')
            ->assertSee('Low Stock — Already Covered')
            ->assertSee('Other Active Initialized Variants')
            ->assertSee('Uncovered 8mm')
            ->assertSee('Covered 10mm')
            ->assertSee('Healthy 12mm')
            ->assertSee('Open PO coverage: 5.000 piece')
            ->assertDontSee('Uninitialized')
            ->assertDontSee('Inactive')
            ->assertDontSee('Archived Product Variant')
            ->assertDontSee('Archived Category Variant')
            ->assertDontSee('Suggested order quantity')
            ->assertSee('name="_token"', false);

        $this->assertMatchesRegularExpression(
            '/name="submission_token" value="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"/',
            $response->getContent(),
        );
        $this->assertSame(1, substr_count($response->getContent(), 'data-id="'.$covered->id.'" data-search='));
    }

    public function test_admin_http_submission_creates_canonical_multi_line_pending_order(): void
    {
        $firstProduct = $this->product($this->category(), ['name' => 'HTTP Bolt']);
        $secondProduct = $this->product($this->category(), ['name' => 'HTTP Sand']);
        $whole = $this->variant($firstProduct, ['size' => 'M10', 'current_stock' => '4.000']);
        $fractional = $this->variant($secondProduct, [
            'size' => 'Fine',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
            'current_stock' => '8.500',
        ]);
        $this->initialize($whole, quantity: '4.000');
        $this->initialize($fractional, quantity: '8.500');
        $this->coverVariant($whole);
        $token = Str::uuid()->toString();

        $response = $this->actingAs($this->admin)->post(route('purchase-orders.store'), [
            'submission_token' => strtoupper($token),
            'supplier_name' => "  HTTP\u{2003}Supplier  ",
            'notes' => "  Planned\n delivery  ",
            'items' => [
                ['product_variant_id' => $fractional->id, 'ordered_quantity' => '1.25', 'expected_unit_cost' => '025.5'],
                ['product_variant_id' => $whole->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '10'],
            ],
        ]);

        $created = PurchaseOrder::query()->where('submission_token', $token)->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase-orders.show', $created))
            ->assertSessionHas('purchase_order_confirmation', fn (array $confirmation): bool => $confirmation['replayed'] === false && $confirmation['line_count'] === 2
            );

        $items = $created->items()->orderBy('product_variant_id')->get();
        $this->assertSame($this->admin->id, $created->created_by);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $created->status);
        $this->assertNull($created->parent_purchase_order_id);
        $this->assertSame('HTTP Supplier', $created->supplier_name);
        $this->assertSame('Planned delivery', $created->notes);
        $this->assertSame(['2.000', '1.250'], $items->pluck('ordered_quantity')->all());
        $this->assertSame(['10.00', '25.50'], $items->pluck('expected_unit_cost')->all());
        $this->assertSame(['HTTP Bolt', 'HTTP Sand'], $items->pluck('product_name_snapshot')->all());
        $this->assertSame('4.000', $whole->fresh()->current_stock);
        $this->assertSame('8.500', $fractional->fresh()->current_stock);
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_http_request_rejects_invalid_shapes_and_decimal_boundaries(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        $base = $this->payload($variant);
        $cases = [
            [['supplier_name' => '   '], 'supplier_name'],
            [['supplier_name' => str_repeat('S', 151)], 'supplier_name'],
            [['notes' => str_repeat('N', 1001)], 'notes'],
            [['items' => null], 'items'],
            [['items' => []], 'items'],
            [['items' => array_fill(0, 101, $base['items'][0])], 'items'],
            [['items' => [$base['items'][0], $base['items'][0]]], 'items.1.product_variant_id'],
            [['items' => [['product_variant_id' => 'bad', 'ordered_quantity' => '1', 'expected_unit_cost' => '1']]], 'items.0.product_variant_id'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '0', 'expected_unit_cost' => '1']]], 'items.0.ordered_quantity'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '-1', 'expected_unit_cost' => '1']]], 'items.0.ordered_quantity'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '1.0001', 'expected_unit_cost' => '1']]], 'items.0.ordered_quantity'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '100000000000.000', 'expected_unit_cost' => '1']]], 'items.0.ordered_quantity'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '1.001']]], 'items.0.expected_unit_cost'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '-1']]], 'items.0.expected_unit_cost'],
            [['items' => [['product_variant_id' => $variant->id, 'ordered_quantity' => '1', 'expected_unit_cost' => '10000000000.00']]], 'items.0.expected_unit_cost'],
            [['items' => [array_merge($base['items'][0], ['status' => 'completed'])]], 'items.0'],
            [['status' => 'completed'], 'request'],
            [['created_by' => 999], 'request'],
        ];

        foreach ($cases as [$change, $error]) {
            $payload = array_replace($base, $change, ['submission_token' => Str::uuid()->toString()]);
            $this->actingAs($this->admin)
                ->from(route('purchase-orders.create'))
                ->post(route('purchase-orders.store'), $payload)
                ->assertRedirect(route('purchase-orders.create'))
                ->assertSessionHasErrors($error);
        }
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_http_preserves_token_for_domain_validation_and_accepts_fractional_mode(): void
    {
        $product = $this->product($this->category());
        $whole = $this->variant($product, ['size' => 'Whole']);
        $fractional = $this->variant($product, ['size' => 'Fractional', 'unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($whole);
        $this->initialize($fractional);
        $token = Str::uuid()->toString();

        $this->actingAs($this->admin)
            ->from(route('purchase-orders.create'))
            ->post(route('purchase-orders.store'), $this->payload($whole, [
                'submission_token' => $token,
                'items' => [[
                    'product_variant_id' => $whole->id,
                    'ordered_quantity' => '1.250',
                    'expected_unit_cost' => '5',
                ]],
            ]))
            ->assertRedirect(route('purchase-orders.create'))
            ->assertSessionHasErrors('items.0.ordered_quantity')
            ->assertSessionHasInput('submission_token', $token);

        $response = $this->actingAs($this->admin)
            ->post(route('purchase-orders.store'), $this->payload($fractional, [
                'items' => [[
                    'product_variant_id' => $fractional->id,
                    'ordered_quantity' => '1.250',
                    'expected_unit_cost' => '5',
                ]],
            ]));
        $created = PurchaseOrder::query()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase-orders.show', $created));
        $this->assertSame('1.250', PurchaseOrderItem::query()->sole()->ordered_quantity);
    }

    public function test_http_idempotent_replay_reuses_order_and_fresh_get_uses_new_token(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        $token = Str::uuid()->toString();
        $payload = $this->payload($variant, ['submission_token' => $token]);

        $createdResponse = $this->actingAs($this->admin)->post(route('purchase-orders.store'), $payload);
        $purchaseOrder = PurchaseOrder::query()->sole();
        $createdResponse->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHas('purchase_order_confirmation', fn (array $confirmation): bool => $confirmation['replayed'] === false);

        $replay = array_replace($payload, [
            'supplier_name' => " Sample\u{2003}Supplier ",
            'notes' => " Test\n planning note ",
            'items' => [[
                'product_variant_id' => $variant->id,
                'ordered_quantity' => '02.000',
                'expected_unit_cost' => '025.5',
            ]],
        ]);
        $response = $this->actingAs($this->admin)->post(route('purchase-orders.store'), $replay);
        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHas('purchase_order_confirmation', fn (array $confirmation): bool => $confirmation['replayed'] === true);
        $this->assertSame(1, PurchaseOrder::query()->count());
        $this->assertSame(1, PurchaseOrderItem::query()->count());

        $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('Purchase Order already recorded')
            ->assertSee('no duplicate Purchase Order was created')
            ->assertDontSee($token);

        $page = $this->get(route('purchase-orders.create'))->assertOk()->getContent();
        preg_match('/name="submission_token" value="([0-9a-f-]{36})"/', $page, $matches);
        $this->assertNotSame($token, $matches[1] ?? null);
    }

    public function test_http_semantic_token_conflict_preserves_draft_and_replaces_token(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $this->initialize($variant);
        $token = Str::uuid()->toString();
        $payload = $this->payload($variant, ['submission_token' => $token]);
        $this->actingAs($this->admin)->post(route('purchase-orders.store'), $payload)->assertSessionHasNoErrors();

        $conflict = array_replace($payload, ['supplier_name' => 'Changed Supplier']);
        $response = $this->actingAs($this->admin)
            ->post(route('purchase-orders.store'), $conflict)
            ->assertRedirect(route('purchase-orders.create'))
            ->assertSessionHasErrors('submission_token')
            ->assertSessionHasInput('supplier_name', 'Changed Supplier')
            ->assertSessionHasInput('items.0.product_variant_id', $variant->id);

        $freshToken = session()->getOldInput('submission_token');
        $this->assertIsString($freshToken);
        $this->assertNotSame($token, $freshToken);
        $this->assertTrue(Str::isUuid($freshToken));
        $this->assertSame(1, PurchaseOrder::query()->count());

        $response = $this->get(route('purchase-orders.create'));
        $response->assertOk()
            ->assertSee('Changed Supplier')
            ->assertSee((string) $variant->id, false)
            ->assertDontSee('SQLSTATE')
            ->assertDontSee($token);
    }

    public function test_old_draft_removes_now_ineligible_selection_and_keeps_other_input(): void
    {
        $product = $this->product($this->category(), ['name' => 'Old Draft Product']);
        $eligible = $this->variant($product, ['size' => 'Still Eligible']);
        $removed = $this->variant($product, ['size' => 'Now Archived']);
        $this->initialize($eligible);
        $this->initialize($removed);
        $removed->status = ProductVariant::STATUS_ARCHIVED;
        $removed->save();

        $response = $this->actingAs($this->admin)
            ->withSession(['_old_input' => [
                'submission_token' => Str::uuid()->toString(),
                'supplier_name' => 'Preserved Supplier',
                'notes' => 'Preserved note',
                'items' => [
                    ['product_variant_id' => $eligible->id, 'ordered_quantity' => '2', 'expected_unit_cost' => '3'],
                    ['product_variant_id' => $removed->id, 'ordered_quantity' => '4', 'expected_unit_cost' => '5'],
                ],
            ]])
            ->get(route('purchase-orders.create'));

        $response->assertOk()
            ->assertSee('Preserved Supplier')
            ->assertSee('Preserved note')
            ->assertSee('previously selected variant became unavailable')
            ->assertSee('Still Eligible')
            ->assertDontSee('Now Archived');
        $this->assertSame(1, substr_count($response->getContent(), 'data-po-draft-row data-id='));
    }

    private function service(): CreatePurchaseOrder
    {
        return app(CreatePurchaseOrder::class);
    }

    /** @param array<string, mixed> $payload */
    private function executePayload(User $actor, array $payload): PurchaseOrder
    {
        return $this->service()->execute(
            $actor,
            $payload['submission_token'],
            $payload['supplier_name'],
            $payload['notes'],
            $payload['items'],
        );
    }
}
