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
use App\Services\Procurement\UpdatePurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchaseOrderUpdateTest extends PurchaseOrderCreationTestCase
{
    private CreatePurchaseOrder $createPurchaseOrder;

    private UpdatePurchaseOrder $updatePurchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPurchaseOrder = app(CreatePurchaseOrder::class);
        $this->updatePurchaseOrder = app(UpdatePurchaseOrder::class);
    }

    public function test_is_editable_is_pending_only_and_all_nonpending_updates_are_rejected(): void
    {
        $variant = $this->eligibleVariant();

        foreach ([
            PurchaseOrder::STATUS_PENDING => true,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => false,
            PurchaseOrder::STATUS_COMPLETED => false,
            PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER => false,
        ] as $status => $editable) {
            $purchaseOrder = $this->createOrder([$this->line($variant)]);
            $purchaseOrder->status = $status;
            $purchaseOrder->save();

            $this->assertSame($editable, $purchaseOrder->fresh()->isEditable());
            if ($editable) {
                continue;
            }

            $before = $this->storedState($purchaseOrder);
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $this->updatePurchaseOrder->revision($purchaseOrder),
                    'Changed Supplier',
                    null,
                    [$this->line($variant, '3', '30')],
                ),
                'purchase_order',
            );
            $this->assertSame($before, $this->storedState($purchaseOrder));
        }
    }

    public function test_pending_order_with_accepted_receiving_is_frozen_without_partial_mutation(): void
    {
        $variant = $this->eligibleVariant();
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $item = $purchaseOrder->items()->sole();
        DB::table('restock_items')->insert([
            'purchase_order_item_id' => $item->id,
            'quantity' => '1.000',
        ]);

        $this->assertTrue($purchaseOrder->fresh()->isEditable());
        $before = $this->storedState($purchaseOrder);
        $this->assertServiceValidation(
            fn () => $this->updatePurchaseOrder->execute(
                $this->admin,
                $purchaseOrder,
                $this->updatePurchaseOrder->revision($purchaseOrder),
                'Changed Supplier',
                'Changed notes',
                [$this->line($variant, '3', '30')],
            ),
            'purchase_order',
        );
        $this->assertSame($before, $this->storedState($purchaseOrder));
        $this->assertSame(1, DB::table('restock_items')->count());
    }

    public function test_only_persisted_active_admins_may_update_and_creator_remains_immutable(): void
    {
        $variant = $this->eligibleVariant();
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $editor = User::factory()->admin()->create();

        $updated = $this->updatePurchaseOrder->execute(
            $editor,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Editor Supplier',
            null,
            [$this->line($variant, '3', '30')],
        );
        $this->assertSame($this->admin->id, $updated->created_by);
        $this->assertSame('Editor Supplier', $updated->supplier_name);

        $staff = User::factory()->create();
        $inactive = User::factory()->admin()->disabled()->create();
        $unpersisted = User::factory()->admin()->make();
        foreach ([$staff, $inactive, $unpersisted] as $actor) {
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $actor,
                    $purchaseOrder,
                    $this->updatePurchaseOrder->revision($purchaseOrder),
                    'Forbidden Supplier',
                    null,
                    [$this->line($variant)],
                ),
                'actor',
            );
        }
        $this->assertSame('Editor Supplier', $purchaseOrder->fresh()->supplier_name);
        $this->assertSame($this->admin->id, $purchaseOrder->fresh()->created_by);
    }

    public function test_revision_is_deterministic_semantic_and_independent_of_timestamps(): void
    {
        $variant = $this->eligibleVariant();
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $revision = $this->updatePurchaseOrder->revision($purchaseOrder);

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $revision);
        $this->assertSame($revision, $this->updatePurchaseOrder->revision($purchaseOrder));

        DB::table('purchase_orders')->where('id', $purchaseOrder->id)->update([
            'updated_at' => '2030-01-01 00:00:00',
        ]);
        $this->assertSame('2030-01-01 00:00:00', $purchaseOrder->fresh()->updated_at?->format('Y-m-d H:i:s'));
        $this->assertSame($revision, $this->updatePurchaseOrder->revision($purchaseOrder));

        $purchaseOrder->supplier_name = 'Revision Supplier';
        $purchaseOrder->save();
        $supplierRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $this->assertNotSame($revision, $supplierRevision);
        $purchaseOrder->notes = 'Revision note';
        $purchaseOrder->save();
        $notesRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $this->assertNotSame($supplierRevision, $notesRevision);

        $item = $purchaseOrder->items()->sole();
        $item->ordered_quantity = '3.000';
        $item->save();
        $quantityRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $this->assertNotSame($notesRevision, $quantityRevision);
        $item->expected_unit_cost = '30.00';
        $item->save();
        $costRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $this->assertNotSame($quantityRevision, $costRevision);

        $second = $this->eligibleVariant(['size' => 'Revision Added']);
        $added = $this->manualItem($purchaseOrder, $second);
        $addedRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $this->assertNotSame($costRevision, $addedRevision);
        $added->delete();
        $this->assertSame($costRevision, $this->updatePurchaseOrder->revision($purchaseOrder));

        $purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $purchaseOrder->save();
        $this->assertNotSame($costRevision, $this->updatePurchaseOrder->revision($purchaseOrder));
    }

    public function test_header_and_retained_line_update_normalize_values_without_changing_identity_or_snapshots(): void
    {
        $variant = $this->eligibleVariant([
            'size' => 'Original Size',
            'type_series' => 'Original Series',
            'thickness' => 'Original Thickness',
            'unit' => 'kg',
            'quantity_mode' => 'fractional',
            'current_stock' => '7.500',
        ]);
        $parent = $this->createOrder([$this->line($this->eligibleVariant(['size' => 'Parent']))]);
        $purchaseOrder = $this->createOrder([$this->line($variant, '1.25', '025.5')], 'Original Supplier', 'Original notes');
        $purchaseOrder->parent_purchase_order_id = $parent->id;
        $purchaseOrder->save();
        $item = $purchaseOrder->items()->sole();
        $itemId = $item->id;
        $snapshots = $item->only([
            'product_name_snapshot', 'size_snapshot', 'type_series_snapshot',
            'thickness_snapshot', 'unit_snapshot',
        ]);
        $immutable = $purchaseOrder->only(['submission_token', 'created_by', 'status', 'parent_purchase_order_id']);
        $stock = $variant->current_stock;
        $movementCount = StockMovement::query()->count();

        $variant->size = 'Current Renamed Size';
        $variant->save();
        $beforeRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $updated = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            strtoupper($beforeRevision),
            "  Acme\u{00A0}  Supply  ",
            "  planned\n delivery  ",
            [$this->line($variant, '02.500', '00030')],
        );

        $updatedItem = $updated->items->sole();
        $this->assertSame('Acme Supply', $updated->supplier_name);
        $this->assertSame('planned delivery', $updated->notes);
        $this->assertSame($immutable, $updated->only(array_keys($immutable)));
        $this->assertSame($itemId, $updatedItem->id);
        $this->assertSame($snapshots, $updatedItem->only(array_keys($snapshots)));
        $this->assertSame('2.500', $updatedItem->ordered_quantity);
        $this->assertSame('30.00', $updatedItem->expected_unit_cost);
        $this->assertNotSame($beforeRevision, $this->updatePurchaseOrder->revision($updated));
        $this->assertSame($stock, $variant->fresh()->current_stock);
        $this->assertSame($movementCount, StockMovement::query()->count());

        $noOpRevision = $this->updatePurchaseOrder->revision($updated);
        $noOpTimestamp = $updated->fresh()->updated_at?->format('Y-m-d H:i:s');
        $this->updatePurchaseOrder->execute(
            $this->admin,
            $updated,
            $noOpRevision,
            'Acme Supply',
            'planned delivery',
            [$this->line($variant, '2.500', '30.00')],
        );
        $this->assertSame($noOpRevision, $this->updatePurchaseOrder->revision($updated));
        $this->assertSame($noOpTimestamp, $updated->fresh()->updated_at?->format('Y-m-d H:i:s'));
    }

    public function test_full_replacement_preserves_retained_rows_removes_omissions_and_snapshots_new_lines(): void
    {
        $removed = $this->eligibleVariant(['size' => 'Removed']);
        $retained = $this->eligibleVariant(['size' => 'Retained']);
        $new = $this->eligibleVariant([
            'size' => 'New Size',
            'type_series' => 'New Series',
            'thickness' => 'New Thickness',
            'unit' => 'roll',
            'quantity_mode' => 'fractional',
            'current_stock' => '20.000',
            'low_stock_threshold' => '5.000',
        ]);
        $this->coverVariant($new);
        $purchaseOrder = $this->createOrder([$this->line($removed), $this->line($retained)]);
        $retainedItem = $purchaseOrder->items()->where('product_variant_id', $retained->id)->sole();
        $retainedId = $retainedItem->id;
        $retainedSnapshots = $retainedItem->only($this->snapshotKeys());

        $updated = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Replacement Supplier',
            null,
            [$this->line($new, '1.25', '17.5'), $this->line($retained, '4', '22')],
        );

        $this->assertSame([$retained->id, $new->id], $updated->items->pluck('product_variant_id')->all());
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_order_id' => $purchaseOrder->id, 'product_variant_id' => $removed->id]);
        $retainedAfter = $updated->items->firstWhere('product_variant_id', $retained->id);
        $newItem = $updated->items->firstWhere('product_variant_id', $new->id);
        $this->assertSame($retainedId, $retainedAfter->id);
        $this->assertSame($retainedSnapshots, $retainedAfter->only($this->snapshotKeys()));
        $this->assertSame($new->product->name, $newItem->product_name_snapshot);
        $this->assertSame('New Size', $newItem->size_snapshot);
        $this->assertSame('New Series', $newItem->type_series_snapshot);
        $this->assertSame('New Thickness', $newItem->thickness_snapshot);
        $this->assertSame('roll', $newItem->unit_snapshot);
        $this->assertSame('1.250', $newItem->ordered_quantity);
        $this->assertSame('17.50', $newItem->expected_unit_cost);
    }

    public function test_new_lines_must_be_active_and_initialized_but_need_not_be_low_stock_or_uncovered(): void
    {
        $base = $this->eligibleVariant(['size' => 'Base']);
        $purchaseOrder = $this->createOrder([$this->line($base)]);
        $healthyCovered = $this->eligibleVariant([
            'size' => 'Healthy Covered',
            'current_stock' => '50.000',
            'low_stock_threshold' => '5.000',
        ]);
        $this->coverVariant($healthyCovered);

        $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Eligible Supplier',
            null,
            [$this->line($base), $this->line($healthyCovered)],
        );
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_variant_id' => $healthyCovered->id,
        ]);

        $uninitialized = $this->variant($this->product($this->category()), ['size' => 'Uninitialized']);
        $archivedVariant = $this->eligibleVariant(['size' => 'Archived Variant']);
        $archivedVariant->status = ProductVariant::STATUS_ARCHIVED;
        $archivedVariant->save();
        $inactiveProductVariant = $this->eligibleVariant(['size' => 'Inactive Product']);
        $inactiveProductVariant->product->status = Product::STATUS_ARCHIVED;
        $inactiveProductVariant->product->save();
        $inactiveCategoryVariant = $this->eligibleVariant(['size' => 'Inactive Category']);
        $inactiveCategoryVariant->product->category->status = Category::STATUS_ARCHIVED;
        $inactiveCategoryVariant->product->category->save();

        foreach ([$uninitialized, $archivedVariant, $inactiveProductVariant, $inactiveCategoryVariant] as $ineligible) {
            $before = $this->storedState($purchaseOrder);
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $this->updatePurchaseOrder->revision($purchaseOrder),
                    'Should Roll Back',
                    'Should roll back',
                    [$this->line($base, '9', '90'), $this->line($ineligible)],
                ),
                'items.1.product_variant_id',
            );
            $this->assertSame($before, $this->storedState($purchaseOrder));
        }
    }

    public function test_archived_variant_retained_line_may_be_edited_removed_but_not_readded(): void
    {
        $this->assertArchivedRetainedPolicy('variant');
    }

    public function test_archived_product_retained_line_may_be_edited_removed_but_not_readded(): void
    {
        $this->assertArchivedRetainedPolicy('product');
    }

    public function test_archived_category_retained_line_may_be_edited_removed_but_not_readded(): void
    {
        $this->assertArchivedRetainedPolicy('category');
    }

    public function test_item_shape_line_bounds_and_duplicate_variants_are_rejected(): void
    {
        $variant = $this->eligibleVariant();
        $purchaseOrder = $this->createOrder([$this->line($variant)]);
        $revision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $invalidCollections = [
            [null, 'items'],
            [[], 'items'],
            [array_fill(0, 101, $this->line($variant)), 'items'],
            [['not-an-array'], 'items.0'],
            [[['product_variant_id' => $variant->id, 'ordered_quantity' => '1']], 'items.0'],
            [[array_merge($this->line($variant), ['status' => 'completed'])], 'items.0'],
            [[array_merge($this->line($variant), ['product_variant_id' => 'invalid'])], 'items.0.product_variant_id'],
            [[$this->line($variant), $this->line($variant)], 'items.1.product_variant_id'],
        ];

        foreach ($invalidCollections as [$items, $key]) {
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $revision,
                    'Shape Supplier',
                    null,
                    $items,
                ),
                $key,
            );
        }
        $this->assertSame(1, $purchaseOrder->items()->count());
    }

    public function test_one_and_one_hundred_final_lines_are_accepted_but_zero_and_over_one_hundred_are_not(): void
    {
        $base = $this->eligibleVariant(['size' => 'Boundary 1']);
        $purchaseOrder = $this->createOrder([$this->line($base)]);
        $this->assertServiceValidation(
            fn () => $this->updatePurchaseOrder->execute(
                $this->admin,
                $purchaseOrder,
                $this->updatePurchaseOrder->revision($purchaseOrder),
                'Boundary Supplier',
                null,
                [],
            ),
            'items',
        );

        $lines = [$this->line($base)];
        for ($index = 2; $index <= 100; $index++) {
            $lines[] = $this->line($this->eligibleVariant(['size' => "Boundary {$index}"]));
        }
        $updated = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Boundary Supplier',
            null,
            array_reverse($lines),
        );
        $this->assertCount(100, $updated->items);
        $this->assertSame(
            $updated->items->pluck('product_variant_id')->sort()->values()->all(),
            $updated->items->pluck('product_variant_id')->all(),
        );

        $this->assertServiceValidation(
            fn () => $this->updatePurchaseOrder->execute(
                $this->admin,
                $purchaseOrder,
                $this->updatePurchaseOrder->revision($purchaseOrder),
                'Boundary Supplier',
                null,
                array_merge($lines, [$this->line($base)]),
            ),
            'items',
        );
    }

    public function test_quantity_and_cost_use_exact_creation_semantics_for_retained_and_new_lines(): void
    {
        $whole = $this->eligibleVariant(['size' => 'Whole']);
        $fractional = $this->eligibleVariant(['size' => 'Fractional', 'quantity_mode' => 'fractional']);
        $purchaseOrder = $this->createOrder([$this->line($whole)]);

        $updated = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Decimal Supplier',
            null,
            [
                $this->line($whole, '0002', '0'),
                $this->line($fractional, '99999999999.999', '9999999999.99'),
            ],
        );
        $this->assertSame('2.000', $updated->items->firstWhere('product_variant_id', $whole->id)->ordered_quantity);
        $this->assertSame('0.00', $updated->items->firstWhere('product_variant_id', $whole->id)->expected_unit_cost);
        $this->assertSame('99999999999.999', $updated->items->firstWhere('product_variant_id', $fractional->id)->ordered_quantity);
        $this->assertSame('9999999999.99', $updated->items->firstWhere('product_variant_id', $fractional->id)->expected_unit_cost);

        $invalids = [
            ['1.001', '1', 'items.0.ordered_quantity'],
            ['0', '1', 'items.0.ordered_quantity'],
            ['-1', '1', 'items.0.ordered_quantity'],
            ['1.0000', '1', 'items.0.ordered_quantity'],
            ['999999999999.000', '1', 'items.0.ordered_quantity'],
            ['1', '-1', 'items.0.expected_unit_cost'],
            ['1', '1.001', 'items.0.expected_unit_cost'],
            ['1', '99999999999.99', 'items.0.expected_unit_cost'],
            ['1', '1e2', 'items.0.expected_unit_cost'],
        ];
        foreach ($invalids as [$quantity, $cost, $key]) {
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $this->updatePurchaseOrder->revision($purchaseOrder),
                    'Decimal Supplier',
                    null,
                    [$this->line($whole, $quantity, $cost), $this->line($fractional)],
                ),
                $key,
            );
        }
    }

    public function test_text_revision_and_atomicity_validation_failures_leave_all_state_unchanged(): void
    {
        $first = $this->eligibleVariant(['size' => 'Atomic First']);
        $second = $this->eligibleVariant(['size' => 'Atomic Second']);
        $invalid = $this->variant($this->product($this->category()), ['size' => 'Atomic Invalid']);
        $purchaseOrder = $this->createOrder([$this->line($first), $this->line($second)]);
        $before = $this->storedState($purchaseOrder);
        $stockBefore = [$first->current_stock, $second->current_stock, $invalid->current_stock];
        $movements = StockMovement::query()->count();

        foreach ([
            ['', null, 'supplier_name'],
            [str_repeat('é', 151), null, 'supplier_name'],
            ['Valid Supplier', str_repeat('é', 1001), 'notes'],
        ] as [$supplier, $notes, $key]) {
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $this->updatePurchaseOrder->revision($purchaseOrder),
                    $supplier,
                    $notes,
                    [$this->line($first)],
                ),
                $key,
            );
        }

        $this->assertServiceValidation(
            fn () => $this->updatePurchaseOrder->execute(
                $this->admin,
                $purchaseOrder,
                $this->updatePurchaseOrder->revision($purchaseOrder),
                'Changed Header',
                'Changed notes',
                [$this->line($first, '9', '90'), $this->line($invalid)],
            ),
            'items.1.product_variant_id',
        );
        $this->assertSame($before, $this->storedState($purchaseOrder));
        $this->assertSame($stockBefore, [$first->fresh()->current_stock, $second->fresh()->current_stock, $invalid->fresh()->current_stock]);
        $this->assertSame($movements, StockMovement::query()->count());
    }

    public function test_stale_revisions_after_header_quantity_and_line_set_changes_reject_without_partial_writes(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Stale Base']);
        $second = $this->eligibleVariant(['size' => 'Stale Second']);

        foreach (['header', 'quantity', 'add', 'remove'] as $change) {
            $purchaseOrder = $this->createOrder([$this->line($variant), $this->line($second)]);
            $staleRevision = $this->updatePurchaseOrder->revision($purchaseOrder);
            if ($change === 'header') {
                $purchaseOrder->supplier_name = 'Concurrent Supplier';
                $purchaseOrder->save();
            } elseif ($change === 'quantity') {
                $item = $purchaseOrder->items()->where('product_variant_id', $variant->id)->sole();
                $item->ordered_quantity = '8.000';
                $item->save();
            } elseif ($change === 'add') {
                $third = $this->eligibleVariant(['size' => 'Concurrent Add '.Str::uuid()]);
                $this->manualItem($purchaseOrder, $third);
            } else {
                $purchaseOrder->items()->where('product_variant_id', $second->id)->delete();
            }
            $concurrentState = $this->storedState($purchaseOrder);

            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $staleRevision,
                    'Stale Overwrite',
                    'Must not apply',
                    [$this->line($variant, '7', '70')],
                ),
                'expected_revision',
            );
            $this->assertSame($concurrentState, $this->storedState($purchaseOrder));
        }
    }

    public function test_create_token_semantics_follow_current_edited_state_without_duplicates(): void
    {
        $variant = $this->eligibleVariant(['size' => 'Token Variant']);
        $token = Str::uuid()->toString();
        $stateA = [$this->line($variant, '2', '25.50')];
        $purchaseOrder = $this->createOrder($stateA, 'Supplier A', 'Notes A', $token);
        $snapshot = $purchaseOrder->items()->sole()->product_name_snapshot;

        $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Supplier B',
            'Notes B',
            [$this->line($variant, '3', '30')],
        );

        $this->assertServiceValidation(
            fn () => $this->createPurchaseOrder->execute($this->admin, $token, 'Supplier A', 'Notes A', $stateA),
            'submission_token',
        );
        $replayedB = $this->createPurchaseOrder->execute(
            $this->admin,
            $token,
            'Supplier B',
            'Notes B',
            [$this->line($variant, '3.000', '30.00')],
        );
        $this->assertSame($purchaseOrder->id, $replayedB->id);
        $this->assertFalse($replayedB->wasRecentlyCreated);
        $this->assertSame($snapshot, $replayedB->items->sole()->product_name_snapshot);

        $otherAdmin = User::factory()->admin()->create();
        $this->assertServiceValidation(
            fn () => $this->createPurchaseOrder->execute(
                $otherAdmin,
                $token,
                'Supplier B',
                'Notes B',
                [$this->line($variant, '3', '30')],
            ),
            'submission_token',
        );

        $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Supplier A',
            'Notes A',
            $stateA,
        );
        $replayedA = $this->createPurchaseOrder->execute($this->admin, $token, 'Supplier A', 'Notes A', $stateA);
        $this->assertSame($purchaseOrder->id, $replayedA->id);

        $purchaseOrder->status = PurchaseOrder::STATUS_COMPLETED;
        $purchaseOrder->save();
        $statusReplay = $this->createPurchaseOrder->execute($this->admin, $token, 'Supplier A', 'Notes A', $stateA);
        $this->assertSame($purchaseOrder->id, $statusReplay->id);
        $this->assertSame(1, PurchaseOrder::query()->where('submission_token', $token)->count());
    }

    public function test_expected_revision_must_be_a_trimmed_case_normalized_sha256_value(): void
    {
        $variant = $this->eligibleVariant();
        $purchaseOrder = $this->createOrder([$this->line($variant)]);

        foreach ([null, '', 'not-a-revision', str_repeat('g', 64), str_repeat('a', 63)] as $invalid) {
            $this->assertServiceValidation(
                fn () => $this->updatePurchaseOrder->execute(
                    $this->admin,
                    $purchaseOrder,
                    $invalid,
                    'Revision Supplier',
                    null,
                    [$this->line($variant)],
                ),
                'expected_revision',
            );
        }

        $revision = $this->updatePurchaseOrder->revision($purchaseOrder);
        $updated = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            '  '.strtoupper($revision).'  ',
            'Revision Supplier',
            null,
            [$this->line($variant)],
        );
        $this->assertSame('Revision Supplier', $updated->supplier_name);
    }

    /** @param array<string, mixed> $attributes */
    private function eligibleVariant(array $attributes = []): ProductVariant
    {
        $variant = $this->variant($this->product($this->category()), $attributes);
        $this->initialize($variant);

        return $variant;
    }

    /** @return array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string} */
    private function line(ProductVariant $variant, string $quantity = '2', string $cost = '25.50'): array
    {
        return [
            'product_variant_id' => (int) $variant->getKey(),
            'ordered_quantity' => $quantity,
            'expected_unit_cost' => $cost,
        ];
    }

    /** @param list<array{product_variant_id: int, ordered_quantity: string, expected_unit_cost: string}> $items */
    private function createOrder(
        array $items,
        string $supplier = 'Original Supplier',
        ?string $notes = 'Original notes',
        ?string $token = null,
    ): PurchaseOrder {
        return $this->createPurchaseOrder->execute(
            $this->admin,
            $token ?? Str::uuid()->toString(),
            $supplier,
            $notes,
            $items,
        );
    }

    private function manualItem(PurchaseOrder $purchaseOrder, ProductVariant $variant): PurchaseOrderItem
    {
        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $purchaseOrder->id;
        $item->product_variant_id = $variant->id;
        $item->product_name_snapshot = $variant->product->name;
        $item->size_snapshot = $variant->size;
        $item->type_series_snapshot = $variant->type_series;
        $item->thickness_snapshot = $variant->thickness;
        $item->unit_snapshot = $variant->unit;
        $item->ordered_quantity = '1.000';
        $item->expected_unit_cost = '1.00';
        $item->save();

        return $item;
    }

    /** @return list<string> */
    private function snapshotKeys(): array
    {
        return [
            'product_name_snapshot', 'size_snapshot', 'type_series_snapshot',
            'thickness_snapshot', 'unit_snapshot',
        ];
    }

    /** @return array<string, mixed> */
    private function storedState(PurchaseOrder $purchaseOrder): array
    {
        $fresh = $purchaseOrder->fresh();

        return [
            'header' => $fresh->only([
                'submission_token', 'created_by', 'supplier_name', 'status', 'notes', 'parent_purchase_order_id',
            ]),
            'items' => $fresh->items()
                ->orderBy('product_variant_id')
                ->get()
                ->map(fn (PurchaseOrderItem $item): array => $item->only([
                    'id', 'product_variant_id', 'product_name_snapshot', 'size_snapshot',
                    'type_series_snapshot', 'thickness_snapshot', 'unit_snapshot',
                    'ordered_quantity', 'expected_unit_cost',
                ]))
                ->all(),
        ];
    }

    private function assertArchivedRetainedPolicy(string $level): void
    {
        $target = $this->eligibleVariant(['size' => "Archived {$level}"]);
        $keep = $this->eligibleVariant(['size' => "Keep {$level}"]);
        $purchaseOrder = $this->createOrder([$this->line($target), $this->line($keep)]);
        $targetItem = $purchaseOrder->items()->where('product_variant_id', $target->id)->sole();
        $targetId = $targetItem->id;
        $snapshots = $targetItem->only($this->snapshotKeys());

        if ($level === 'variant') {
            $target->status = ProductVariant::STATUS_ARCHIVED;
            $target->save();
        } elseif ($level === 'product') {
            $target->product->status = Product::STATUS_ARCHIVED;
            $target->product->save();
        } else {
            $target->product->category->status = Category::STATUS_ARCHIVED;
            $target->product->category->save();
        }

        $retained = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Archived Retained Supplier',
            null,
            [$this->line($target, '7', '70'), $this->line($keep)],
        );
        $retainedItem = $retained->items->firstWhere('product_variant_id', $target->id);
        $this->assertSame($targetId, $retainedItem->id);
        $this->assertSame($snapshots, $retainedItem->only($this->snapshotKeys()));
        $this->assertSame('7.000', $retainedItem->ordered_quantity);
        $this->assertSame('70.00', $retainedItem->expected_unit_cost);

        $removed = $this->updatePurchaseOrder->execute(
            $this->admin,
            $purchaseOrder,
            $this->updatePurchaseOrder->revision($purchaseOrder),
            'Archived Retained Supplier',
            null,
            [$this->line($keep)],
        );
        $this->assertNull($removed->items->firstWhere('product_variant_id', $target->id));

        $this->assertServiceValidation(
            fn () => $this->updatePurchaseOrder->execute(
                $this->admin,
                $purchaseOrder,
                $this->updatePurchaseOrder->revision($purchaseOrder),
                'Archived Retained Supplier',
                null,
                [$this->line($keep), $this->line($target)],
            ),
            'items.1.product_variant_id',
        );
        $this->assertSame(1, $purchaseOrder->items()->count());
    }
}
