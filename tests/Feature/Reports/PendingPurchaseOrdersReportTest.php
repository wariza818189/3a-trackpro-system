<?php

namespace Tests\Feature\Reports;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Procurement\PurchaseOrderCreationTestCase;

final class PendingPurchaseOrdersReportTest extends PurchaseOrderCreationTestCase
{
    public function test_authorization_get_only_route_and_reports_entry_point(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recorded_by');
            $table->string('status');
            $table->decimal('total_amount', 16, 2);
            $table->timestamp('created_at');
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id');
            $table->decimal('quantity', 14, 3);
            $table->string('unit_snapshot');
        });

        $url = route('reports.pending-purchase-orders');
        $this->get($url)->assertRedirect('/login');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $staff = User::factory()->create();
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->get(route('reports.index'))->assertOk()->assertSee($url, false)->assertSee('Pending Purchase Orders Report');

        $route = Route::getRoutes()->getByName('reports.pending-purchase-orders');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_empty_state_and_open_status_inclusion_exclude_terminal_and_zero_demand(): void
    {
        $this->actingAs($this->admin)->get(route('reports.pending-purchase-orders'))
            ->assertOk()->assertSee('No open Purchase Orders with outstanding demand')->assertSee('data-report-count>0<', false);

        $variant = $this->variant($this->product($this->category()));
        $pending = $this->order('Pending Supplier');
        $partial = $this->order('Partial Supplier', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $completed = $this->order('Completed Supplier', PurchaseOrder::STATUS_COMPLETED);
        $closed = $this->order('Closed Supplier', PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER);
        $zero = $this->order('Zero Supplier');
        foreach ([$pending, $partial, $completed, $closed, $zero] as $order) {
            $item = $this->item($order, $variant, '1.000');
            if ($order->is($zero)) {
                $this->accept($item, '1.000');
            }
        }
        $fulfilledSibling = $this->item($pending, $this->variant($this->product($this->category())), '1.000', 'Fulfilled Sibling');
        $this->accept($fulfilledSibling, '1.000');

        $this->get(route('reports.pending-purchase-orders'))->assertOk()
            ->assertSee('data-report-count>2<', false)
            ->assertSee('Pending Supplier')->assertSee('Partial Supplier')
            ->assertDontSee('Completed Supplier')->assertDontSee('Closed Supplier')->assertDontSee('Zero Supplier')
            ->assertDontSee('Fulfilled Sibling');
    }

    public function test_fractional_evidence_and_follow_up_lineage_are_exact(): void
    {
        $variant = $this->variant($this->product($this->category(), ['name' => 'Original Product']), ['unit' => 'm', 'quantity_mode' => 'fractional']);
        $source = $this->order('Source Supplier', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $line = $this->item($source, $variant, '7.375', 'Historical Product');
        $this->accept($line, '1.125');
        $this->accept($line, '0.250');
        $child = $this->order('Child Supplier', parent: $source);
        $childLine = $this->item($child, $variant, '2.250', 'Child Product');
        $this->transfer($line, $childLine, '2.250');

        $response = $this->actingAs($this->admin)->get(route('reports.pending-purchase-orders'))->assertOk();
        $response->assertSee('data-report-count>2<', false)
            ->assertSee('data-report-po="'.$source->id.'"', false)
            ->assertSee('data-report-po="'.$child->id.'"', false)
            ->assertSee('Historical Product')->assertSee('Child Product')
            ->assertSeeInOrder(['7.375', '1.375', '2.250', '3.750'])
            ->assertSee('Parent:')->assertSee('Follow-up children:');
    }

    public function test_fully_transferred_source_is_excluded_and_child_remains(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $source = $this->order('Transferred Source', PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER);
        $sourceLine = $this->item($source, $variant, '3.125');
        $child = $this->order('Open Child', parent: $source);
        $childLine = $this->item($child, $variant, '3.125');
        $this->transfer($sourceLine, $childLine, '3.125');

        $this->actingAs($this->admin)->get(route('reports.pending-purchase-orders'))->assertOk()
            ->assertSee('data-report-count>1<', false)
            ->assertSee('Open Child')->assertSee('Parent:')->assertSee('PO #'.$source->id)
            ->assertDontSee('Transferred Source')->assertDontSee('data-report-po="'.$source->id.'"', false);
    }

    public function test_supplier_and_status_filters_escape_wildcards_and_fail_safely(): void
    {
        $variant = $this->variant($this->product($this->category()));
        foreach ([
            ['CaseMarker 100% Supply', PurchaseOrder::STATUS_PENDING],
            ['CaseMarker 100X Supply', PurchaseOrder::STATUS_PARTIALLY_RECEIVED],
            ['Under_score Supply', PurchaseOrder::STATUS_PENDING],
            ['UnderXscore Supply', PurchaseOrder::STATUS_PENDING],
        ] as [$supplier, $status]) {
            $this->item($this->order($supplier, $status), $variant, '1.000');
        }

        $url = 'reports.pending-purchase-orders';
        $this->actingAs($this->admin)->get(route($url, ['supplier' => 'casemarker 100%']))
            ->assertOk()->assertSee('CaseMarker 100% Supply')->assertDontSee('CaseMarker 100X Supply');
        $this->get(route($url, ['supplier' => '_']))
            ->assertOk()->assertSee('Under_score Supply')->assertDontSee('UnderXscore Supply');
        $this->get(route($url, ['status' => 'pending']))
            ->assertOk()->assertSee('CaseMarker 100% Supply')->assertDontSee('CaseMarker 100X Supply');
        $this->get(route($url, ['status' => 'partially_received']))
            ->assertOk()->assertSee('CaseMarker 100X Supply')->assertDontSee('CaseMarker 100% Supply');
        foreach (['completed', 'invented'] as $invalid) {
            $this->get(route($url, ['status' => $invalid]))->assertOk()
                ->assertSee('The report was not run')->assertDontSee('CaseMarker 100% Supply');
        }
        $this->get(route($url, ['status' => ['pending']]))->assertOk()->assertSee('The report was not run');
    }

    public function test_ordering_privacy_and_read_only_state(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
        });
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '9.375']);
        $older = $this->order('Older Supplier', createdAt: '2026-09-20 09:00:00');
        $tieLow = $this->order('Tie Low Supplier', createdAt: '2026-09-21 09:00:00');
        $tieHigh = $this->order('Tie High Supplier', createdAt: '2026-09-21 09:00:00');
        foreach ([$older, $tieLow, $tieHigh] as $order) {
            $this->item($order, $variant, '1.000');
        }
        $before = [];
        foreach (['purchase_orders', 'purchase_order_items', 'restocks', 'restock_items', 'purchase_order_item_transfers', 'product_variants', 'stock_movements', 'audit_logs'] as $table) {
            if (Schema::hasTable($table)) {
                $before[$table] = DB::table($table)->count();
            }
        }

        $response = $this->actingAs($this->admin)->get(route('reports.pending-purchase-orders'))->assertOk();
        $response->assertSeeInOrder(['Tie High Supplier', 'Tie Low Supplier', 'Older Supplier'])
            ->assertDontSee('8765.43')->assertDontSee($tieHigh->submission_token)
            ->assertDontSee('expected_unit_cost')->assertDontSee('purchase cost')
            ->assertDontSee('data-po-edit')->assertDontSee('data-po-create-action')
            ->assertDontSee('Receive items')->assertDontSee('Follow-up Purchase Order');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame('9.375', $variant->fresh()->current_stock);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $tieHigh->fresh()->status);
    }

    private function order(string $supplier, string $status = PurchaseOrder::STATUS_PENDING, string $createdAt = '2026-09-20 12:00:00', ?PurchaseOrder $parent = null): PurchaseOrder
    {
        $order = new PurchaseOrder;
        $order->parent_purchase_order_id = $parent?->id;
        $order->submission_token = Str::uuid()->toString();
        $order->created_by = $this->admin->id;
        $order->supplier_name = $supplier;
        $order->status = $status;
        $order->notes = null;
        $order->created_at = $createdAt;
        $order->updated_at = $createdAt;
        $order->save();

        return $order;
    }

    private function item(PurchaseOrder $order, ProductVariant $variant, string $quantity, string $name = 'Snapshot Product'): PurchaseOrderItem
    {
        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $order->id;
        $item->product_variant_id = $variant->id;
        $item->product_name_snapshot = $name;
        $item->size_snapshot = $variant->size;
        $item->type_series_snapshot = $variant->type_series;
        $item->thickness_snapshot = $variant->thickness;
        $item->unit_snapshot = $variant->unit;
        $item->ordered_quantity = $quantity;
        $item->expected_unit_cost = '8765.43';
        $item->save();

        return $item;
    }

    private function accept(PurchaseOrderItem $item, string $quantity): void
    {
        $restock = DB::table('restocks')->insertGetId(['purchase_order_id' => $item->purchase_order_id, 'recorded_by' => $this->admin->id, 'created_at' => now()]);
        DB::table('restock_items')->insert(['restock_id' => $restock, 'purchase_order_item_id' => $item->id, 'quantity' => $quantity]);
    }

    private function transfer(PurchaseOrderItem $source, PurchaseOrderItem $target, string $quantity): void
    {
        DB::table('purchase_order_item_transfers')->insert([
            'source_purchase_order_item_id' => $source->id,
            'target_purchase_order_item_id' => $target->id,
            'quantity' => $quantity,
            'created_by' => $this->admin->id,
            'created_at' => now(),
        ]);
    }
}
