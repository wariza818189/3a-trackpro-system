<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PurchaseOrderBrowsingTest extends PurchaseOrderCreationTestCase
{
    public function test_index_and_show_are_available_only_to_active_admins(): void
    {
        $purchaseOrder = $this->purchaseOrder('Authorization Supplier');

        $this->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertRedirect('/login');

        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchase-orders.show', $purchaseOrder))->assertForbidden();

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->actingAs($disabled)->get(route('purchase-orders.show', $purchaseOrder))->assertRedirect('/login');

        $this->actingAs($this->admin)->get(route('purchase-orders.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('purchase-orders.show', $purchaseOrder))->assertOk();
        $this->actingAs($this->admin)->get('/purchase-orders/999999')->assertNotFound();
    }

    public function test_index_orders_by_created_time_then_id_and_displays_safe_operational_fields(): void
    {
        $older = $this->purchaseOrder('Older Supplier', createdAt: '2026-09-19 09:00:00');
        $tieLow = $this->purchaseOrder('Tie Low Supplier', createdAt: '2026-09-20 09:00:00');
        $tieHigh = $this->purchaseOrder('Tie High Supplier', createdAt: '2026-09-20 09:00:00');
        $newest = $this->purchaseOrder('Newest Supplier', PurchaseOrder::STATUS_COMPLETED, '2026-09-21 09:00:00');

        $variant = $this->variant($this->product($this->category()), ['size' => 'Indexed']);
        $this->purchaseOrderItem($newest, $variant, 'Indexed Snapshot');

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.index'));

        $response->assertOk()
            ->assertSeeInOrder(['Newest Supplier', 'Tie High Supplier', 'Tie Low Supplier', 'Older Supplier'])
            ->assertSee($this->admin->name)
            ->assertSee('completed')
            ->assertSee('data-po-create-action', false)
            ->assertSee(route('purchase-orders.create'), false)
            ->assertSee(route('purchase-orders.show', $newest), false)
            ->assertSee('data-po-line-count>1<', false)
            ->assertSee('data-po-edit', false)
            ->assertDontSee($newest->submission_token)
            ->assertDontSee('data-po-delete', false)
            ->assertDontSee('Receive items');
        $this->assertSame(1, $newest->items()->count());
        $this->assertGreaterThan($tieHigh->id, $newest->id);
        $this->assertGreaterThan($tieLow->id, $tieHigh->id);
        $this->assertGreaterThan($older->id, $tieLow->id);
    }

    public function test_index_paginates_twenty_rows_and_preserves_filters(): void
    {
        for ($index = 1; $index <= 21; $index++) {
            $this->purchaseOrder(
                'Needle Supplier '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                createdAt: '2026-09-20 09:00:00',
            );
        }

        $first = $this->actingAs($this->admin)->get(route('purchase-orders.index', ['supplier' => 'Needle']));
        $first->assertOk()->assertSee('supplier=Needle', false);
        $this->assertSame(20, substr_count($first->getContent(), 'data-po-index-row='));

        $second = $this->get(route('purchase-orders.index', ['supplier' => 'Needle', 'page' => 2]));
        $second->assertOk();
        $this->assertSame(1, substr_count($second->getContent(), 'data-po-index-row='));
    }

    public function test_supplier_search_is_substring_case_tolerant_and_escapes_like_wildcards(): void
    {
        $this->purchaseOrder('CaseMarker Industrial');
        $this->purchaseOrder('Unrelated Trading');
        $this->purchaseOrder('Literal 100% Supply');
        $this->purchaseOrder('Literal 100X Supply');
        $this->purchaseOrder('Under_score Supply');
        $this->purchaseOrder('UnderXscore Supply');

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.index', ['supplier' => 'marker ind']))
            ->assertOk()
            ->assertSee('CaseMarker Industrial')
            ->assertDontSee('Unrelated Trading');

        $this->get(route('purchase-orders.index', ['supplier' => '%']))
            ->assertOk()
            ->assertSee('Literal 100% Supply')
            ->assertDontSee('Literal 100X Supply');

        $this->get(route('purchase-orders.index', ['supplier' => '_']))
            ->assertOk()
            ->assertSee('Under_score Supply')
            ->assertDontSee('UnderXscore Supply');
    }

    public function test_status_filter_is_exact_and_invalid_status_fails_safely(): void
    {
        $this->purchaseOrder('Pending Filter Supplier');
        $this->purchaseOrder('Completed Filter Supplier', PurchaseOrder::STATUS_COMPLETED);
        $this->purchaseOrder('Partial Filter Supplier', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.index', ['status' => PurchaseOrder::STATUS_PENDING]))
            ->assertOk()
            ->assertSee('Pending Filter Supplier')
            ->assertDontSee('Completed Filter Supplier')
            ->assertDontSee('Partial Filter Supplier');

        $this->get(route('purchase-orders.index', ['status' => PurchaseOrder::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSee('Completed Filter Supplier')
            ->assertDontSee('Pending Filter Supplier');

        $this->from(route('purchase-orders.index'))
            ->get(route('purchase-orders.index', ['status' => 'invented']))
            ->assertRedirect(route('purchase-orders.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_index_has_distinct_empty_states_for_no_orders_and_no_filter_matches(): void
    {
        $this->actingAs($this->admin)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('No Purchase Orders have been created yet.');

        $this->purchaseOrder('Existing Supplier');
        $this->get(route('purchase-orders.index', ['supplier' => 'Missing Supplier']))
            ->assertOk()
            ->assertSee('No matching Purchase Orders were found.');
    }

    public function test_show_uses_historical_snapshots_and_exact_saved_decimals(): void
    {
        $product = $this->product($this->category(), ['name' => 'Current Catalog Product']);
        $variant = $this->variant($product, [
            'size' => 'Current Size',
            'type_series' => 'Current Series',
            'thickness' => 'Current Thickness',
            'unit' => 'piece',
        ]);
        $purchaseOrder = $this->purchaseOrder('Historical Supplier', notes: 'Historical planning note');
        $this->purchaseOrderItem($purchaseOrder, $variant, 'Saved Product Name', [
            'size_snapshot' => 'Saved Size',
            'type_series_snapshot' => 'Saved Series',
            'thickness_snapshot' => 'Saved Thickness',
            'unit_snapshot' => 'kg',
            'ordered_quantity' => '12.375',
            'expected_unit_cost' => '45.60',
        ]);

        $product->name = 'Renamed Current Product';
        $product->save();
        $variant->size = 'Renamed Current Size';
        $variant->type_series = 'Renamed Current Series';
        $variant->thickness = 'Renamed Current Thickness';
        $variant->unit = 'roll';
        $variant->save();

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.show', $purchaseOrder));

        $response->assertOk()
            ->assertSee('Historical Supplier')
            ->assertSee('Historical planning note')
            ->assertSee($this->admin->name)
            ->assertSee('Saved Product Name')
            ->assertSee('Saved Size')
            ->assertSee('Saved Series')
            ->assertSee('Saved Thickness')
            ->assertSee('12.375 kg')
            ->assertSee('₱45.60')
            ->assertSee('Edit Purchase Order')
            ->assertSee(route('purchase-orders.edit', $purchaseOrder), false)
            ->assertDontSee('Renamed Current Product')
            ->assertDontSee('Renamed Current Size')
            ->assertDontSee('Renamed Current Series')
            ->assertDontSee('Renamed Current Thickness')
            ->assertDontSee($purchaseOrder->submission_token)
            ->assertDontSee('Receive items');
    }

    public function test_nonpending_child_order_remains_readable_with_parent_reference(): void
    {
        $parent = $this->purchaseOrder('Parent Supplier');
        $child = $this->purchaseOrder(
            'Child Supplier',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            parent: $parent,
        );

        $this->actingAs($this->admin)
            ->get(route('purchase-orders.show', $child))
            ->assertOk()
            ->assertSee('partially received')
            ->assertSee('Parent Purchase Order')
            ->assertSee('PO #'.$parent->id)
            ->assertSee(route('purchase-orders.show', $parent), false)
            ->assertDontSee('Edit Purchase Order')
            ->assertDontSee('Receive items');
    }

    public function test_pending_orders_offer_edit_actions_but_nonpending_orders_are_view_only(): void
    {
        $pending = $this->purchaseOrder('Pending Editable Supplier');
        $completed = $this->purchaseOrder('Completed Read Only Supplier', PurchaseOrder::STATUS_COMPLETED);

        $index = $this->actingAs($this->admin)->get(route('purchase-orders.index'));
        $index->assertOk()
            ->assertSee(route('purchase-orders.edit', $pending), false)
            ->assertDontSee(route('purchase-orders.edit', $completed), false);

        $this->get(route('purchase-orders.show', $pending))
            ->assertOk()
            ->assertSee('Edit Purchase Order')
            ->assertSee(route('purchase-orders.edit', $pending), false);
        $this->get(route('purchase-orders.show', $completed))
            ->assertOk()
            ->assertDontSee('Edit Purchase Order')
            ->assertDontSee(route('purchase-orders.edit', $completed), false);
    }

    public function test_purchase_order_route_inventory_has_exactly_six_named_routes_and_no_mutation_extras(): void
    {
        $expected = [
            'purchase-orders.index' => ['GET', 'HEAD'],
            'purchase-orders.create' => ['GET', 'HEAD'],
            'purchase-orders.store' => ['POST'],
            'purchase-orders.show' => ['GET', 'HEAD'],
            'purchase-orders.edit' => ['GET', 'HEAD'],
            'purchase-orders.update' => ['PATCH'],
        ];

        foreach ($expected as $name => $methods) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame($methods, $route->methods());
        }

        foreach (['purchase-orders.destroy', 'purchase-orders.delete', 'purchase-orders.cancel', 'purchase-orders.receive'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name));
        }

        $this->assertCount(
            6,
            collect(Route::getRoutes()->getRoutes())->filter(
                fn ($route): bool => str_starts_with((string) $route->getName(), 'purchase-orders.'),
            ),
        );
    }

    private function purchaseOrder(
        string $supplier,
        string $status = PurchaseOrder::STATUS_PENDING,
        string $createdAt = '2026-09-20 12:00:00',
        ?PurchaseOrder $parent = null,
        ?string $notes = null,
    ): PurchaseOrder {
        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->parent_purchase_order_id = $parent?->getKey();
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = $this->admin->getKey();
        $purchaseOrder->supplier_name = $supplier;
        $purchaseOrder->status = $status;
        $purchaseOrder->notes = $notes;
        $purchaseOrder->created_at = $createdAt;
        $purchaseOrder->updated_at = $createdAt;
        $purchaseOrder->save();

        return $purchaseOrder;
    }

    /** @param array<string, string> $overrides */
    private function purchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        ProductVariant $variant,
        string $productName,
        array $overrides = [],
    ): PurchaseOrderItem {
        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $purchaseOrder->getKey();
        $item->product_variant_id = $variant->getKey();
        $item->product_name_snapshot = $productName;
        $item->size_snapshot = $overrides['size_snapshot'] ?? $variant->size;
        $item->type_series_snapshot = $overrides['type_series_snapshot'] ?? $variant->type_series;
        $item->thickness_snapshot = $overrides['thickness_snapshot'] ?? $variant->thickness;
        $item->unit_snapshot = $overrides['unit_snapshot'] ?? $variant->unit;
        $item->ordered_quantity = $overrides['ordered_quantity'] ?? '1.000';
        $item->expected_unit_cost = $overrides['expected_unit_cost'] ?? '1.00';
        $item->save();

        return $item;
    }
}
