<?php

namespace Tests\Feature\Reports;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockDamageItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Procurement\PurchaseOrderCreationTestCase;

final class DamagedItemsReportTest extends PurchaseOrderCreationTestCase
{
    public function test_admin_only_get_route_and_reports_index_entry(): void
    {
        $url = route('reports.damaged-items');
        $this->get($url)->assertRedirect('/login');

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        $this->assertGuest();

        $staff = User::factory()->create();
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();

        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->createSalesTablesForReportsIndex();
        $this->get(route('reports.index'))->assertOk()->assertSee($url, false)->assertSee('Damaged Items Report');

        $route = Route::getRoutes()->getByName('reports.damaged-items');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $this->call($method, $url)->assertMethodNotAllowed();
        }
    }

    public function test_empty_states_and_one_historical_damage_row(): void
    {
        $url = route('reports.damaged-items');
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('No damaged receiving records yet.');

        $product = $this->product($this->category(), ['name' => 'Original catalog']);
        $variant = $this->variant($product, ['size' => 'Size S', 'type_series' => 'Series A', 'thickness' => '2 mm', 'unit' => 'm', 'quantity_mode' => 'fractional']);
        $order = $this->order('Historic Supplier');
        $line = $this->line($order, $variant, 'Stored Product');
        $receipt = $this->receipt($order, '2026-09-23 14:05:00');
        $damage = $this->damage($line, $receipt, '0.125', '<Bent on arrival>');

        $product->name = 'Changed current catalog';
        $product->save();
        $variant->status = ProductVariant::STATUS_ARCHIVED;
        $variant->save();

        $response = $this->get($url)->assertOk();
        $response->assertSee('data-report-damage="'.$damage->id.'"', false)
            ->assertSee('href="'.route('purchase-orders.show', $order->id).'"', false)
            ->assertSee('PO #'.$order->id)->assertSee('Historic Supplier')
            ->assertSee($receipt->restockNumber())->assertSee('Stored Product')
            ->assertSee('Size S')->assertSee('Series A')->assertSee('2 mm')
            ->assertSee('0.125 m')->assertSee('&lt;Bent on arrival&gt;', false)
            ->assertSee($this->admin->name)->assertSee('Sep 23, 2026 2:05 PM')
            ->assertDontSee('Changed current catalog')->assertDontSee('<Bent on arrival>', false);

        $this->get(route('reports.damaged-items', ['supplier' => 'missing']))
            ->assertOk()->assertSee('No damaged items match these filters.')
            ->assertDontSee('No damaged receiving records yet.');
    }

    public function test_each_damage_record_stays_separate_and_child_uses_its_own_po(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $otherVariant = $this->variant($this->product($this->category()));
        $source = $this->order('Source Supplier');
        $sourceLine = $this->line($source, $variant, 'Source Item');
        $otherLine = $this->line($source, $otherVariant, 'Other Item');
        $firstReceipt = $this->receipt($source, '2026-09-20 09:00:00');
        $first = $this->damage($sourceLine, $firstReceipt, '1.000');
        $second = $this->damage($otherLine, $firstReceipt, '2.000');
        $laterReceipt = $this->receipt($source, '2026-09-21 09:00:00');
        $third = $this->damage($sourceLine, $laterReceipt, '3.000');

        $child = $this->order('Child Supplier', parent: $source);
        $childLine = $this->line($child, $variant, 'Child Item');
        $fourth = $this->damage($childLine, $this->receipt($child, '2026-09-22 09:00:00'), '4.000');

        $response = $this->actingAs($this->admin)->get(route('reports.damaged-items'))->assertOk();
        foreach ([$first, $second, $third, $fourth] as $damage) {
            $response->assertSee('data-report-damage="'.$damage->id.'"', false);
        }
        $this->assertSame(4, substr_count($response->getContent(), 'data-report-damage='));
        $response->assertSeeInOrder([
            'data-report-damage="'.$fourth->id.'"',
            'data-report-damage="'.$third->id.'"',
            'data-report-damage="'.$second->id.'"',
            'data-report-damage="'.$first->id.'"',
        ], false);
        $this->get(route('reports.damaged-items', ['po' => $child->id]))
            ->assertSee('PO #'.$child->id)->assertSee('Child Supplier')
            ->assertDontSee('PO #'.$source->id)->assertDontSee('Source Supplier')
            ->assertDontSee('Parent:')->assertDontSee('Outstanding');
    }

    public function test_supplier_item_snapshot_and_exact_po_filters(): void
    {
        $variant = $this->variant($this->product($this->category(), ['name' => 'Old Catalog']), [
            'size' => 'Needle_Size', 'type_series' => 'Needle%Series', 'thickness' => 'Needle!Thickness',
        ]);
        $firstOrder = $this->order('CaseMarker 100% Supply');
        $first = $this->damage($this->line($firstOrder, $variant, 'Bolt_20%'), $this->receipt($firstOrder), '1.000');
        $secondOrder = $this->order('CaseMarker 100X Supply');
        $this->damage($this->line($secondOrder, $variant, 'BoltX20X'), $this->receipt($secondOrder), '2.000');
        $thirdOrder = $this->order('Under_score Supply');
        $this->damage($this->line($thirdOrder, $variant, 'Other Item'), $this->receipt($thirdOrder), '3.000');
        $url = 'reports.damaged-items';

        $this->actingAs($this->admin)->get(route($url, ['supplier' => 'casemarker 100%']))
            ->assertSee('data-report-damage="'.$first->id.'"', false)->assertDontSee('CaseMarker 100X Supply');
        $this->get(route($url, ['supplier' => '_']))
            ->assertSee('Under_score Supply')->assertDontSee('CaseMarker 100% Supply');
        $this->get(route($url, ['item' => 'bolt_20%']))
            ->assertSee('Bolt_20%')->assertDontSee('BoltX20X');
        foreach (['Needle_Size', 'Needle%Series', 'Needle!Thickness', 'piece'] as $snapshot) {
            $this->get(route($url, ['item' => $snapshot]))->assertSee('Bolt_20%');
        }
        $this->get(route($url, ['po' => (string) $firstOrder->id]))
            ->assertSee('Bolt_20%')->assertDontSee('BoltX20X')->assertDontSee('Other Item');

        $variant->product->update(['name' => 'New searchable catalog']);
        $this->get(route($url, ['item' => 'New searchable catalog']))
            ->assertSee('No damaged items match these filters.')->assertDontSee('Bolt_20%');
        foreach (['0', '-1', '01', 'PO #'.$firstOrder->id, '999999999999999999999'] as $invalid) {
            $this->get(route($url, ['po' => $invalid]))->assertSee('The report was not run')->assertDontSee('Bolt_20%');
        }
        foreach ([['supplier' => ['x']], ['item' => ['x']], ['po' => ['1']]] as $invalid) {
            $this->get(route($url, $invalid))->assertSee('The report was not run')->assertDontSee('Bolt_20%');
        }
    }

    public function test_receipt_timestamp_then_receipt_id_then_damage_id_ordering(): void
    {
        $variant = $this->variant($this->product($this->category()));
        $other = $this->variant($this->product($this->category()));
        $order = $this->order('Ordering Supplier');
        $line = $this->line($order, $variant, 'First');
        $otherLine = $this->line($order, $other, 'Second');
        $old = $this->damage($line, $this->receipt($order, '2026-09-19 09:00:00'), '1.000');
        $tieA = $this->receipt($order, '2026-09-20 09:00:00');
        $firstTie = $this->damage($line, $tieA, '2.000');
        $secondTie = $this->damage($otherLine, $tieA, '3.000');
        $tieB = $this->damage($line, $this->receipt($order, '2026-09-20 09:00:00'), '4.000');

        $this->actingAs($this->admin)->get(route('reports.damaged-items'))->assertOk()
            ->assertSeeInOrder([
                'data-report-damage="'.$tieB->id.'"',
                'data-report-damage="'.$secondTie->id.'"',
                'data-report-damage="'.$firstTie->id.'"',
                'data-report-damage="'.$old->id.'"',
            ], false);
    }

    public function test_get_is_read_only_private_and_query_count_is_bounded(): void
    {
        $variant = $this->variant($this->product($this->category()), ['current_stock' => '9.375']);
        $orders = [];
        foreach (range(1, 8) as $number) {
            $order = $this->order('Supplier '.$number);
            $line = $this->line($order, $variant, 'Snapshot '.$number);
            $this->damage($line, $this->receipt($order), '1.000');
            $orders[] = $order;
        }
        $tables = ['restock_damage_items', 'restocks', 'purchase_orders', 'purchase_order_items', 'product_variants', 'stock_movements'];
        $before = array_combine($tables, array_map(fn (string $table) => DB::table($table)->count(), $tables));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($this->admin)->get(route('reports.damaged-items'))->assertOk();
        $selectCount = collect(DB::getQueryLog())->filter(fn (array $query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count();
        DB::disableQueryLog();

        $this->assertSame(8, substr_count($response->getContent(), 'data-report-damage='));
        $this->assertLessThanOrEqual(6, $selectCount);
        $response->assertDontSee('8765.43')->assertDontSee('4321.98')
            ->assertDontSee($orders[0]->submission_token)
            ->assertDontSee('expected_unit_cost')->assertDontSee('submission_token')
            ->assertDontSee('stock_before')->assertDontSee('stock_after')
            ->assertDontSee('StockMovement')->assertDontSee('Receive items')
            ->assertDontSee('Follow-up Purchase Order')->assertDontSee('Edit damage');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame('9.375', $variant->fresh()->current_stock);
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

    private function order(string $supplier, ?PurchaseOrder $parent = null): PurchaseOrder
    {
        $order = new PurchaseOrder;
        $order->parent_purchase_order_id = $parent?->id;
        $order->submission_token = Str::uuid()->toString();
        $order->created_by = $this->admin->id;
        $order->supplier_name = $supplier;
        $order->status = PurchaseOrder::STATUS_PENDING;
        $order->save();

        return $order;
    }

    private function line(PurchaseOrder $order, ProductVariant $variant, string $name): PurchaseOrderItem
    {
        $line = new PurchaseOrderItem;
        $line->purchase_order_id = $order->id;
        $line->product_variant_id = $variant->id;
        $line->product_name_snapshot = $name;
        $line->size_snapshot = $variant->size;
        $line->type_series_snapshot = $variant->type_series;
        $line->thickness_snapshot = $variant->thickness;
        $line->unit_snapshot = $variant->unit;
        $line->ordered_quantity = '10.000';
        $line->expected_unit_cost = '8765.43';
        $line->save();

        return $line;
    }

    private function receipt(PurchaseOrder $order, string $createdAt = '2026-09-20 12:00:00'): Restock
    {
        $receipt = new Restock;
        $receipt->submission_token = Str::uuid()->toString();
        $receipt->purchase_order_id = $order->id;
        $receipt->recorded_by = $this->admin->id;
        $receipt->total_cost = '4321.98';
        $receipt->created_at = $createdAt;
        $receipt->save();

        return $receipt;
    }

    private function damage(PurchaseOrderItem $line, Restock $receipt, string $quantity, string $note = 'Damaged on delivery'): RestockDamageItem
    {
        return RestockDamageItem::create([
            'restock_id' => $receipt->id,
            'purchase_order_item_id' => $line->id,
            'product_variant_id' => $line->product_variant_id,
            'product_name_snapshot' => $line->product_name_snapshot,
            'size_snapshot' => $line->size_snapshot,
            'type_series_snapshot' => $line->type_series_snapshot,
            'thickness_snapshot' => $line->thickness_snapshot,
            'unit_snapshot' => $line->unit_snapshot,
            'damaged_quantity' => $quantity,
            'damage_note' => $note,
        ]);
    }
}
