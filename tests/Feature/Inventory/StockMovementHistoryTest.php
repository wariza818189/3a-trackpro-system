<?php

namespace Tests\Feature\Inventory;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class StockMovementHistoryTest extends MovementActivityTestCase
{
    public function test_admin_and_staff_can_view_history_and_navigation(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('inventory.movements.index'))->assertOk()
                ->assertSee('Movement History')->assertSee('No stock movement records yet.')
                ->assertSee(route('inventory.movements.index'));
        }
        $route = Route::getRoutes()->getByName('inventory.movements.index');
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertNotContains('can:access-admin', $route->gatherMiddleware());
        $this->post(route('inventory.movements.index'))->assertStatus(405);
    }

    public function test_guest_is_denied(): void
    {
        $this->get(route('inventory.movements.index'))->assertRedirect('/login');
    }

    public function test_disabled_viewer_is_denied_and_logged_out(): void
    {
        $this->actingAs(User::factory()->disabled()->create())
            ->get(route('inventory.movements.index'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_all_five_types_show_stored_quantities_context_and_references_once(): void
    {
        $movements = $this->allTypes();
        $response = $this->actingAs(User::factory()->create())->get(route('inventory.movements.index'))->assertOk();
        $rows = $response->viewData('movements')->getCollection()->keyBy('id');
        $this->assertCount(5, $rows);
        $this->assertSame(5, substr_count($response->getContent(), 'data-movement-id='));
        $labels = ['Opening Inventory', 'Stock In', 'Sale', 'Stock Correction', 'Sale Void'];
        foreach ($movements as $index => $movement) {
            $row = $rows[$movement->id];
            $this->assertSame($labels[$index], $row['label']);
            $this->assertSame((string) $movement->quantity_before, $row['before']);
            $this->assertSame((string) $movement->quantity_after, $row['after']);
            $this->assertSame($movement->reason, $row['reason']);
            $this->assertSame($movement->performedBy->name, $row['actor']);
            $this->assertSame($movement->variant->product->name, $row['product']);
            $this->assertSame('Movement size · Movement series · 2mm · kg', $row['variant']);
            $this->assertSame('Sep 27, 2026 10:15 AM', $row['date']);
            $this->assertSame('2026-09-27T10:15:00+08:00', $row['datetime']);
            foreach (['label', 'reference', 'change', 'before', 'after', 'product', 'variant', 'actor', 'date'] as $field) {
                $response->assertSee($row[$field]);
            }
            if ($row['reason'] !== null) {
                $response->assertSee($row['reason']);
            }
        }
        $this->assertSame('Opening Inventory', $rows[$movements[0]->id]['reference']);
        $this->assertSame($movements[1]->restockItem->restock->restockNumber(), $rows[$movements[1]->id]['reference']);
        $this->assertSame($movements[2]->saleItem->sale->receiptNumber(), $rows[$movements[2]->id]['reference']);
        $this->assertSame('Stock Correction', $rows[$movements[3]->id]['reference']);
        $this->assertSame($movements[4]->saleItem->sale->receiptNumber().' · Sale Void', $rows[$movements[4]->id]['reference']);
        $response->assertSee('+4.000')->assertSee('−2.500')->assertDontSee('PO #')->assertDontSee('123.000');
    }

    public function test_po_context_is_derived_only_from_the_linked_restock(): void
    {
        $manual = $this->movement(StockMovement::TYPE_RESTOCK);
        $po = $this->movement(StockMovement::TYPE_RESTOCK, purchaseOrder: true);
        $response = $this->actingAs(User::factory()->create())->get(route('inventory.movements.index'))->assertOk();
        $rows = $response->viewData('movements')->getCollection()->keyBy('id');
        $this->assertSame($manual->restockItem->restock->restockNumber(), $rows[$manual->id]['reference']);
        $this->assertSame($po->restockItem->restock->restockNumber().' · PO #'.$po->restockItem->restock->purchase_order_id, $rows[$po->id]['reference']);
        $response->assertSee($rows[$po->id]['reference']);
    }

    public function test_archived_catalog_disabled_actor_and_escaped_reason_remain_visible(): void
    {
        $movement = $this->movement(overrides: ['reason' => '<script>alert("reason")</script>']);
        DB::table('products')->where('id', $movement->variant->product_id)->update(['status' => 'archived']);
        DB::table('product_variants')->where('id', $movement->product_variant_id)->update(['status' => 'archived']);
        DB::table('users')->where('id', $movement->performed_by)->update(['status' => 'disabled']);
        $this->actingAs(User::factory()->create())->get(route('inventory.movements.index'))->assertOk()
            ->assertSee($movement->variant->product->name)->assertSee($movement->performedBy->name)
            ->assertSee($movement->reason)->assertDontSee($movement->reason, false);
    }

    public function test_twenty_per_page_newest_first_with_id_ties_and_null_time_last(): void
    {
        $null = $this->movement(overrides: ['created_at' => null]);
        $ids = [];
        for ($index = 0; $index < 20; $index++) {
            $ids[] = $this->movement()->id;
        }
        $olderHigherId = $this->movement(overrides: ['created_at' => '2026-09-26 10:00:00']);
        $this->actingAs(User::factory()->create());
        $first = $this->get(route('inventory.movements.index'))->assertOk()->assertSee('page=2');
        $this->assertSame(array_reverse($ids), $first->viewData('movements')->getCollection()->pluck('id')->all());
        $this->assertSame(20, substr_count($first->getContent(), 'data-movement-id='));
        $first->assertSeeInOrder(array_map(fn (int $id): string => 'data-movement-id="'.$id.'"', array_reverse($ids)), false);
        $second = $this->get(route('inventory.movements.index', ['page' => 2]))->assertOk();
        $rows = $second->viewData('movements')->getCollection();
        $this->assertSame([$olderHigherId->id, $null->id], $rows->pluck('id')->all());
        $this->assertSame('—', $rows->last()['date']);
        $this->assertNull($rows->last()['datetime']);
    }

    public function test_zero_opening_and_positive_correction_have_explicit_three_decimal_signs(): void
    {
        $this->movement(StockMovement::TYPE_INITIAL_STOCK, ['quantity_change' => '0.000', 'quantity_after' => '0.000']);
        $this->movement(overrides: ['quantity_change' => '0.125', 'quantity_after' => '10.125']);
        $this->actingAs(User::factory()->create())->get(route('inventory.movements.index'))->assertOk()
            ->assertSee('+0.000')->assertSee('+0.125')->assertSee('10.125');
    }

    public function test_get_and_head_do_not_write_or_change_domain_records(): void
    {
        $this->allTypes();
        $this->actingAs(User::factory()->create());
        $this->assertReadOnly(route('inventory.movements.index'));
    }

    public function test_query_count_is_constant_for_five_and_twenty_rows_with_all_relations(): void
    {
        $this->allTypes();
        $this->actingAs(User::factory()->create());
        $small = $this->requestQueryCount(route('inventory.movements.index'));
        for ($index = 0; $index < 3; $index++) {
            $this->allTypes();
        }
        $large = $this->requestQueryCount(route('inventory.movements.index'));
        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(10, $large);
    }
}
