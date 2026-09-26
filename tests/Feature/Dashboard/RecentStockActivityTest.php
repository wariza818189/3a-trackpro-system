<?php

namespace Tests\Feature\Dashboard;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Inventory\MovementActivityTestCase;

class RecentStockActivityTest extends MovementActivityTestCase
{
    public function test_admin_and_staff_see_section_link_and_empty_state(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->create()] as $viewer) {
            $this->actingAs($viewer)->get(route('home'))->assertOk()
                ->assertSee('Recent Stock Activity')->assertSee('View Movement History')
                ->assertSee(route('inventory.movements.index'))->assertSee('No stock movement records yet.');
        }
    }

    public function test_guest_and_disabled_viewer_are_denied(): void
    {
        $this->get(route('home'))->assertRedirect('/login');
        $this->actingAs(User::factory()->disabled()->create())->get(route('home'))->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_latest_five_types_share_history_semantics_without_pagination(): void
    {
        $movements = $this->allTypes();
        $older = $this->movement(overrides: ['created_at' => '2026-09-26 10:15:00']);
        $this->actingAs(User::factory()->create());
        $history = $this->get(route('inventory.movements.index'))->assertOk()->viewData('movements')->getCollection();
        $dashboard = $this->get(route('home'))->assertOk();
        $rows = $dashboard->viewData('recentMovements');
        $this->assertSame($history->take(5)->values()->all(), $rows->all());
        $this->assertSame(array_reverse(array_map(fn (StockMovement $movement): int => $movement->id, $movements)), $rows->pluck('id')->all());
        $this->assertSame(5, substr_count($dashboard->getContent(), 'data-movement-id='));
        $dashboard->assertDontSee('data-movement-id="'.$older->id.'"', false)
            ->assertDontSee('page=2')->assertDontSee('Pagination Navigation')
            ->assertSee('+4.000')->assertSee('−2.500');
        foreach ($rows as $row) {
            foreach (['label', 'reference', 'product', 'variant', 'change', 'actor', 'date'] as $field) {
                $dashboard->assertSee($row[$field]);
            }
        }
    }

    public function test_po_reference_and_archived_catalog_with_disabled_actor_use_shared_presentation(): void
    {
        $movement = $this->movement(StockMovement::TYPE_RESTOCK, purchaseOrder: true);
        DB::table('products')->where('id', $movement->variant->product_id)->update(['status' => 'archived']);
        DB::table('product_variants')->where('id', $movement->product_variant_id)->update(['status' => 'archived']);
        DB::table('users')->where('id', $movement->performed_by)->update(['status' => 'disabled']);
        $this->actingAs(User::factory()->create());
        $historyRow = $this->get(route('inventory.movements.index'))->assertOk()->viewData('movements')->first();
        $dashboard = $this->get(route('home'))->assertOk();
        $this->assertSame($historyRow, $dashboard->viewData('recentMovements')->first());
        $dashboard->assertSee($movement->variant->product->name)->assertSee($movement->performedBy->name)
            ->assertSee($movement->restockItem->restock->restockNumber().' · PO #'.$movement->restockItem->restock->purchase_order_id);
    }

    public function test_get_and_head_are_read_only_with_all_movement_types(): void
    {
        $this->allTypes();
        $this->actingAs(User::factory()->create());
        $this->assertReadOnly(route('home'));
    }

    public function test_query_count_stays_bounded_as_the_movement_history_grows(): void
    {
        $this->allTypes();
        $this->actingAs(User::factory()->create());
        $small = $this->requestQueryCount(route('home'));
        for ($index = 0; $index < 4; $index++) {
            $this->allTypes();
        }
        $large = $this->requestQueryCount(route('home'));
        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(14, $large);
    }
}
