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

final class UnfulfilledItemsReportTest extends PurchaseOrderCreationTestCase
{
    public function test_authorization_get_only_route_and_reports_entry_point(): void
    {
        $url = route('reports.unfulfilled-items');
        $this->get($url)->assertRedirect('/login');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $staff = User::factory()->create();
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get($url)->assertOk();

        $this->createSalesTablesForReportsIndex();
        $this->get(route('reports.index'))->assertOk()->assertSee($url, false)->assertSee('Unfulfilled Items Report');

        $route = Route::getRoutes()->getByName('reports.unfulfilled-items');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->assertNull(Route::getRoutes()->getByName('reports.unfulfilled-items.store'));
        $this->assertSame(1, substr_count(file_get_contents(resource_path('views/layouts/app.blade.php')), 'reports.index'));
    }

    public function test_empty_state_and_open_statuses_exclude_zero_and_terminal_lines(): void
    {
        $this->actingAs($this->admin)->get(route('reports.unfulfilled-items'))
            ->assertOk()->assertSee('No unfulfilled Purchase Order items')->assertSee('data-report-count>0<', false);

        $variant = $this->variant($this->product($this->category()));
        $pending = $this->item($this->order('Pending Supplier'), $variant, '2.000', 'Pending Line');
        $partial = $this->item($this->order('Partial Supplier', PurchaseOrder::STATUS_PARTIALLY_RECEIVED), $variant, '2.000', 'Partial Line');
        $zero = $this->item($this->order('Zero Supplier'), $variant, '1.000', 'Zero Line');
        $this->accept($zero, '1.000');
        $this->item($this->order('Completed Supplier', PurchaseOrder::STATUS_COMPLETED), $variant, '1.000', 'Completed Line');
        $this->item($this->order('Closed Supplier', PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER), $variant, '1.000', 'Closed Line');

        $this->get(route('reports.unfulfilled-items'))->assertOk()
            ->assertSee('data-report-count>2<', false)
            ->assertSee('data-report-line="'.$pending->id.'"', false)
            ->assertSee('data-report-line="'.$partial->id.'"', false)
            ->assertDontSee('Zero Line')->assertDontSee('Completed Line')->assertDontSee('Closed Line');
    }

    public function test_fractional_receipts_transfer_and_child_are_exact_without_double_counting(): void
    {
        $variant = $this->variant($this->product($this->category()), ['unit' => 'm', 'quantity_mode' => 'fractional']);
        $source = $this->order('Source Supplier', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $sourceLine = $this->item($source, $variant, '7.375', 'Source Snapshot');
        $this->accept($sourceLine, '1.125');
        $this->accept($sourceLine, '0.250');
        $child = $this->order('Child Supplier', parent: $source);
        $childLine = $this->item($child, $variant, '2.250', 'Child Snapshot');
        $this->transfer($sourceLine, $childLine, '2.250');

        $response = $this->actingAs($this->admin)->get(route('reports.unfulfilled-items'))->assertOk();
        $response->assertSee('data-report-count>2<', false)
            ->assertSeeInOrder(['Source Snapshot', '7.375', '1.375', '2.250', '3.750'])
            ->assertSee('data-report-line="'.$childLine->id.'"', false)
            ->assertSee('Parent:')->assertSee('Follow-up children:');

        $closed = $this->order('Closed Source', PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER);
        $closedLine = $this->item($closed, $variant, '3.125', 'Closed Source Snapshot');
        $openChild = $this->order('Open Child', parent: $closed);
        $openChildLine = $this->item($openChild, $variant, '3.125', 'Open Child Snapshot');
        $this->transfer($closedLine, $openChildLine, '3.125');
        $this->get(route('reports.unfulfilled-items'))->assertOk()
            ->assertSee('data-report-count>3<', false)
            ->assertSee('data-report-line="'.$openChildLine->id.'"', false)
            ->assertDontSee('data-report-line="'.$closedLine->id.'"', false)
            ->assertDontSee('Closed Source Snapshot');
    }

    public function test_each_line_keeps_po_supplier_unit_and_historical_snapshot_identity(): void
    {
        $product = $this->product($this->category(), ['name' => 'Current Original']);
        $variant = $this->variant($product, ['unit' => 'm', 'quantity_mode' => 'fractional']);
        $first = $this->item($this->order('Alpha Supplier'), $variant, '1.250', 'Historic Alpha');
        $second = $this->item($this->order('Beta Supplier'), $variant, '2.500', 'Historic Beta');
        $piece = $this->item($this->order('Gamma Supplier'), $this->variant($this->product($this->category()), ['unit' => 'piece']), '3.000', 'Piece Snapshot');
        $product->name = 'Changed Catalog Name';
        $product->save();

        $this->actingAs($this->admin)->get(route('reports.unfulfilled-items'))->assertOk()
            ->assertSee('data-report-count>3<', false)
            ->assertSee('data-report-line="'.$first->id.'"', false)
            ->assertSee('data-report-line="'.$second->id.'"', false)
            ->assertSee('data-report-line="'.$piece->id.'"', false)
            ->assertSee('Historic Alpha')->assertSee('Historic Beta')->assertSee('Piece Snapshot')
            ->assertSee('Alpha Supplier')->assertSee('Beta Supplier')->assertSee('Gamma Supplier')
            ->assertDontSee('Changed Catalog Name');
    }

    public function test_supplier_item_status_and_po_filters_are_narrow_and_fail_safely(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $a = $this->order('CaseMarker 100% Supply');
        $b = $this->order('CaseMarker 100X Supply', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $c = $this->order('Under_score Supply');
        $this->item($a, $variant, '1.000', 'Bolt_20%');
        $this->item($b, $variant, '1.000', 'BoltX20X');
        $this->item($c, $variant, '1.000', 'Other Item');
        $url = 'reports.unfulfilled-items';

        $this->actingAs($this->admin)->get(route($url, ['supplier' => 'casemarker 100%']))
            ->assertSee('CaseMarker 100% Supply')->assertDontSee('CaseMarker 100X Supply');
        $this->get(route($url, ['supplier' => '_']))
            ->assertSee('Under_score Supply')->assertDontSee('CaseMarker 100% Supply');
        $this->get(route($url, ['item' => 'bolt_20%']))
            ->assertSee('Bolt_20%')->assertDontSee('BoltX20X')->assertDontSee('Other Item');
        $this->get(route($url, ['status' => 'pending']))
            ->assertSee('CaseMarker 100% Supply')->assertDontSee('CaseMarker 100X Supply');
        $this->get(route($url, ['status' => 'partially_received']))
            ->assertSee('CaseMarker 100X Supply')->assertDontSee('CaseMarker 100% Supply');
        $this->get(route($url, ['po' => (string) $a->id]))
            ->assertSee('data-report-count>1<', false)->assertSee('CaseMarker 100% Supply')->assertDontSee('CaseMarker 100X Supply');

        $markedVariant = $this->variant($this->product($this->category()), [
            'size' => 'Needle_Size', 'type_series' => 'Needle%Series',
            'thickness' => 'Needle!Thickness', 'unit' => 'm', 'quantity_mode' => 'fractional',
        ]);
        $this->item($this->order('Marked Supplier'), $markedVariant, '1.000', 'RareLine');
        foreach (['Needle_Size', 'Needle%Series', 'Needle!Thickness', 'm'] as $snapshotSearch) {
            $this->get(route($url, ['supplier' => 'Marked Supplier', 'item' => $snapshotSearch]))
                ->assertSee('data-report-count>1<', false)->assertSee('RareLine')->assertDontSee('Bolt_20%');
        }

        foreach ([['status' => 'completed'], ['status' => ['pending']], ['po' => 'PO #'.$a->id], ['po' => '0'], ['po' => '-1'], ['po' => '999999999999999999999'], ['item' => ['Bolt']], ['supplier' => ['Supply']]] as $filters) {
            $this->get(route($url, $filters))->assertOk()->assertSee('The report was not run')->assertDontSee('Bolt_20%');
        }
    }

    public function test_oldest_po_then_po_id_then_line_id_ordering(): void
    {
        $variantA = $this->variant($this->product($this->category()));
        $variantB = $this->variant($this->product($this->category()));
        $older = $this->order('Older Supplier', createdAt: '2026-09-19 09:00:00');
        $tieA = $this->order('Tie A Supplier', createdAt: '2026-09-20 09:00:00');
        $tieB = $this->order('Tie B Supplier', createdAt: '2026-09-20 09:00:00');
        $oldLine = $this->item($older, $variantA, '1.000', 'Oldest Line');
        $firstTie = $this->item($tieA, $variantA, '1.000', 'First Tie Line');
        $secondTie = $this->item($tieA, $variantB, '1.000', 'Second Tie Line');
        $last = $this->item($tieB, $variantA, '1.000', 'Last Line');

        $this->actingAs($this->admin)->get(route('reports.unfulfilled-items'))->assertOk()
            ->assertSeeInOrder([
                'data-report-line="'.$oldLine->id.'"',
                'data-report-line="'.$firstTie->id.'"',
                'data-report-line="'.$secondTie->id.'"',
                'data-report-line="'.$last->id.'"',
            ], false);
    }

    public function test_privacy_read_only_state_and_bounded_select_count(): void
    {
        Schema::create('audit_logs', fn (Blueprint $table) => $table->id());
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '9.375']);
        $orders = [];
        foreach (range(1, 8) as $number) {
            $order = $this->order('Supplier '.$number);
            $this->item($order, $variant, '1.000', 'Snapshot '.$number);
            $orders[] = $order;
        }

        $tables = ['purchase_orders', 'purchase_order_items', 'restocks', 'restock_items', 'purchase_order_item_transfers', 'product_variants', 'stock_movements', 'audit_logs'];
        $before = array_combine($tables, array_map(fn (string $table) => DB::table($table)->count(), $tables));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($this->admin)->get(route('reports.unfulfilled-items'))->assertOk();
        $selectCount = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count();
        DB::disableQueryLog();

        $response->assertSee('data-report-count>8<', false)
            ->assertDontSee('8765.43')->assertDontSee($orders[0]->submission_token)
            ->assertDontSee('expected_unit_cost')->assertDontSee('purchase cost')
            ->assertDontSee('data-po-edit')->assertDontSee('data-po-create-action')
            ->assertDontSee('Receive items')->assertDontSee('Follow-up Purchase Order');
        $this->assertLessThanOrEqual(7, $selectCount);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame('9.375', $variant->fresh()->current_stock);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $orders[0]->fresh()->status);
    }

    private function createSalesTablesForReportsIndex(): void
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

    private function item(PurchaseOrder $order, ProductVariant $variant, string $quantity, string $name): PurchaseOrderItem
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
