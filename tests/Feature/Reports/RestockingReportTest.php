<?php

namespace Tests\Feature\Reports;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockDamageItem;
use App\Models\RestockItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Inventory\RestockTestCase;

final class RestockingReportTest extends RestockTestCase
{
    public function test_admin_only_get_route_and_reports_index_link(): void
    {
        $url = route('reports.restocking');
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->admin()->disabled()->create())->get($url)->assertRedirect('/login');
        $this->assertGuest();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()
            ->assertSee('No accepted stock-in history yet.');
        $this->head($url)->assertOk();
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('unit_snapshot')->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
        });
        $this->get(route('reports.index'))->assertOk()->assertSee($url, false)->assertSee('Restocking Report');

        $route = Route::getRoutes()->getByName('reports.restocking');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        foreach (['auth', 'active', 'can:access-admin'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->call($method, $url)->assertMethodNotAllowed();
        }
    }

    public function test_manual_stock_in_uses_historical_snapshots_actual_cost_and_exact_units(): void
    {
        $staff = User::factory()->create(['name' => 'Receiving Staff']);
        $product = $this->product($this->category(), ['name' => 'Original Bolt']);
        $whole = $this->variant($product, ['size' => 'M8', 'type_series' => 'Grade A', 'thickness' => '3 mm']);
        $fractional = $this->variant($product, ['size' => 'Bulk', 'unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($whole, $staff);
        $this->initialize($fractional, $staff);

        $token = Str::uuid()->toString();
        $this->actingAs($staff)->post(route('stock-in.store'), [
            'submission_token' => $token,
            'items' => [
                ['product_variant_id' => $whole->id, 'quantity' => '2', 'unit_cost' => '31.25'],
                ['product_variant_id' => $fractional->id, 'quantity' => '0.125', 'unit_cost' => '12.40'],
            ],
        ])->assertSessionHasNoErrors();
        $receipt = Restock::query()->sole();
        $items = $receipt->items()->orderBy('id')->get();

        $product->name = 'Current Bolt';
        $product->save();
        DB::table('product_variants')->where('id', $whole->id)->update([
            'size' => 'Current Size', 'status' => ProductVariant::STATUS_ARCHIVED,
        ]);
        DB::table('product_variants')->where('id', $fractional->id)->update(['cost_price' => '999.99']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('reports.restocking'))->assertOk();
        $response->assertSee($receipt->restockNumber())->assertSee('Stock In')
            ->assertSee('Original Bolt')->assertSee('M8')->assertSee('Grade A')->assertSee('3 mm')
            ->assertSee('2.000 piece')->assertSee('0.125 kg')
            ->assertSee('₱31.25')->assertSee('₱62.50')->assertSee('₱12.40')->assertSee('₱1.55')
            ->assertSee($staff->name)->assertSee($receipt->created_at->format('M j, Y g:i A'))
            ->assertDontSee('Current Bolt')->assertDontSee('Current Size')->assertDontSee('999.99')
            ->assertDontSee($token)->assertDontSee('PO #');
        $this->assertSame(2, substr_count($response->getContent(), 'data-report-restock-item='));
        foreach ($items as $item) {
            $response->assertSee('data-report-restock-item="'.$item->id.'"', false);
        }
    }

    public function test_po_receiving_shows_only_accepted_lines_and_excludes_damage_only_receipt(): void
    {
        $staff = User::factory()->create(['name' => 'PO Receiver']);
        $product = $this->product($this->category(), ['name' => 'Historic Pipe']);
        $variant = $this->variant($product, ['size' => 'Long', 'unit' => 'm', 'quantity_mode' => 'fractional']);
        $this->initialize($variant, $staff);
        [$order, $line] = $this->purchaseOrderLine($variant);

        $damageToken = Str::uuid()->toString();
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), [
            'submission_token' => $damageToken,
            'items' => [['purchase_order_item_id' => $line->id, 'damaged_quantity' => '9.000', 'damage_note' => 'Private damage note']],
        ])->assertSessionHasNoErrors();
        $damageReceipt = Restock::query()->sole();
        $this->assertSame(0, RestockItem::query()->count());
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.restocking'))->assertOk()
            ->assertSee('No accepted stock-in history yet.')->assertDontSee($damageReceipt->restockNumber());

        $token = Str::uuid()->toString();
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $order), [
            'submission_token' => $token,
            'items' => [[
                'purchase_order_item_id' => $line->id,
                'accepted_quantity' => '0.125', 'actual_unit_cost' => '73.45',
                'damaged_quantity' => '7.000', 'damage_note' => 'Another private note',
            ]],
        ])->assertSessionHasNoErrors();
        $accepted = RestockItem::query()->sole();
        $receipt = $accepted->restock;

        $product->name = 'Current Pipe';
        $product->save();
        DB::table('product_variants')->where('id', $variant->id)->update(['cost_price' => '888.88']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('reports.restocking'))->assertOk();
        $response->assertSee('data-report-restock-item="'.$accepted->id.'"', false)
            ->assertSee($receipt->restockNumber())->assertSee('Purchase Order')
            ->assertSee('PO #'.$order->id)->assertSee('Actual Supplier')
            ->assertSee('Historic Pipe')->assertSee('Long')->assertSee('0.125 m')
            ->assertSee('₱73.45')->assertSee('₱9.18')->assertSee($staff->name)
            ->assertSee($receipt->created_at->format('M j, Y g:i A'))
            ->assertDontSee($damageReceipt->restockNumber())
            ->assertDontSee('Private damage note')->assertDontSee('Another private note')
            ->assertDontSee('9.000 m')->assertDontSee('7.000 m')
            ->assertDontSee('25.50')->assertDontSee('888.88')->assertDontSee('Current Pipe')
            ->assertDontSee($token)->assertDontSee($damageToken)
            ->assertDontSee('Outstanding')->assertDontSee('Follow-up')->assertDontSee('Receive items');
        $this->assertSame(1, substr_count($response->getContent(), 'data-report-restock-item='));
        $this->assertSame(2, RestockDamageItem::query()->count());
    }

    public function test_newest_receipt_and_line_ties_are_stable_and_get_is_read_only_with_bounded_selects(): void
    {
        $staff = User::factory()->create();
        $product = $this->product($this->category(), ['name' => 'Ordered Item']);
        $firstVariant = $this->variant($product);
        $secondVariant = $this->variant($product, ['size' => 'Other', 'unit' => 'kg', 'quantity_mode' => 'fractional']);
        $this->initialize($firstVariant, $staff);
        $this->initialize($secondVariant, $staff);
        $receipts = [];
        foreach (range(1, 8) as $number) {
            $this->actingAs($staff)->post(route('stock-in.store'), [
                'submission_token' => Str::uuid()->toString(),
                'items' => [
                    ['product_variant_id' => $firstVariant->id, 'quantity' => '1', 'unit_cost' => '2'],
                    ['product_variant_id' => $secondVariant->id, 'quantity' => '0.125', 'unit_cost' => '4'],
                ],
            ])->assertSessionHasNoErrors();
            $receipt = Restock::query()->orderByDesc('id')->firstOrFail();
            DB::table('restocks')->where('id', $receipt->id)->update([
                'created_at' => match ($number) {
                    1 => '2026-09-22 09:00:00',
                    2 => '2026-09-20 09:00:00',
                    default => '2026-09-21 09:00:00',
                },
            ]);
            $receipts[] = $receipt;
        }
        $before = [];
        foreach (['restocks', 'restock_items', 'product_variants', 'stock_movements', 'purchase_orders', 'restock_damage_items'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $expectedItemIds = [];
        foreach ([$receipts[0], ...array_reverse(array_slice($receipts, 2)), $receipts[1]] as $receipt) {
            array_push($expectedItemIds, ...$receipt->items()->orderByDesc('id')->pluck('id')->all());
        }

        $admin = User::factory()->admin()->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($admin)->get(route('reports.restocking'))->assertOk();
        $queries = collect(DB::getQueryLog());
        $selectCount = $queries->filter(fn (array $query) => str_starts_with(strtolower(ltrim($query['query'])), 'select'))->count();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $selectCount);
        $this->assertFalse($queries->contains(fn (array $query) => preg_match('/\A\s*(?:insert|update|delete|replace)\b/i', $query['query']) === 1));
        $this->assertSame(16, substr_count($response->getContent(), 'data-report-restock-item='));
        $response->assertSeeInOrder(array_map(fn (int $id) => 'data-report-restock-item="'.$id.'"', $expectedItemIds), false)
            ->assertDontSee('StockMovement')->assertDontSee('submission_token')
            ->assertDontSee('Delete')->assertDontSee('Edit');
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    /** @return array{PurchaseOrder, PurchaseOrderItem} */
    private function purchaseOrderLine(ProductVariant $variant): array
    {
        $order = new PurchaseOrder;
        $order->submission_token = Str::uuid()->toString();
        $order->created_by = User::factory()->admin()->create()->id;
        $order->supplier_name = 'Actual Supplier';
        $order->status = PurchaseOrder::STATUS_PENDING;
        $order->save();

        $line = new PurchaseOrderItem;
        $line->purchase_order_id = $order->id;
        $line->product_variant_id = $variant->id;
        $line->product_name_snapshot = $variant->product->name;
        $line->size_snapshot = $variant->size;
        $line->type_series_snapshot = $variant->type_series;
        $line->thickness_snapshot = $variant->thickness;
        $line->unit_snapshot = $variant->unit;
        $line->ordered_quantity = '5.000';
        $line->expected_unit_cost = '25.50';
        $line->save();

        return [$order, $line];
    }
}
