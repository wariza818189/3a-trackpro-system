<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Inventory\RestockTestCase;

final class PurchaseOrderFollowUpHttpTest extends RestockTestCase
{
    public function test_only_active_admin_may_access_follow_up_routes(): void
    {
        [$admin, $source, $items] = $this->fixture();
        $payload = $this->payloadFor($items[0]);
        $this->get(route('purchase-orders.follow-up.create', $source))->assertRedirect('/login');
        $this->post(route('purchase-orders.follow-up.store', $source), $payload)->assertRedirect('/login');

        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('purchase-orders.follow-up.create', $source))->assertForbidden();
        $this->post(route('purchase-orders.follow-up.store', $source), $payload)->assertForbidden();

        $disabled = User::factory()->admin()->disabled()->create();
        $this->actingAs($disabled)->get(route('purchase-orders.follow-up.create', $source))->assertRedirect('/login');
        $this->post(route('purchase-orders.follow-up.store', $source), $payload)->assertRedirect('/login');

        $unsupported = User::factory()->create();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('users')->where('id', $unsupported->id)->update(['role' => 'owner']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        $this->actingAs($unsupported->fresh())->get(route('purchase-orders.follow-up.create', $source))->assertForbidden();
        $this->post(route('purchase-orders.follow-up.store', $source), $payload)->assertForbidden();

        $this->actingAs($admin)->get(route('purchase-orders.follow-up.create', $source))->assertOk();
    }

    public function test_form_lists_only_eligible_lines_and_contains_no_transfer_quantity_input(): void
    {
        [$admin, $source, $items] = $this->fixture(3);
        $this->recordAccepted($admin, $source, $items[1], '3.000', '66.66');
        $this->actingAs($admin)->post(route('purchase-orders.follow-up.store', $source), $this->payloadFor($items[2], supplier: 'First Follow-up'))
            ->assertRedirect();

        $response = $this->actingAs($admin)->get(route('purchase-orders.follow-up.create', $source))->assertOk();
        $response->assertSee('data-follow-up-line="'.$items[0]->id.'"', false)
            ->assertDontSee('data-follow-up-line="'.$items[1]->id.'"', false)
            ->assertDontSee('data-follow-up-line="'.$items[2]->id.'"', false)
            ->assertSee('value="Source Supplier"', false)
            ->assertSee('value="12.34"', false)
            ->assertSee('Ordered')->assertSee('Accepted')->assertSee('Transferred')->assertSee('Outstanding')
            ->assertDontSee('name="transfer_quantity"', false);
        $this->assertMatchesRegularExpression('/name="submission_token" value="[0-9a-f-]{36}"/', $response->getContent());
    }

    public function test_validation_failure_preserves_token_supplier_selection_and_cost(): void
    {
        [$admin, $source, $items] = $this->fixture();
        $token = Str::uuid()->toString();
        $payload = $this->payloadFor($items[0], $token, 'Edited Supplier', '9.999');

        $this->actingAs($admin)
            ->from(route('purchase-orders.follow-up.create', $source))
            ->post(route('purchase-orders.follow-up.store', $source), $payload)
            ->assertRedirect(route('purchase-orders.follow-up.create', $source))
            ->assertSessionHasErrors('items.0.expected_unit_cost');

        $this->get(route('purchase-orders.follow-up.create', $source))->assertOk()
            ->assertSee('value="'.$token.'"', false)
            ->assertSee('value="Edited Supplier"', false)
            ->assertSee('value="9.999"', false)
            ->assertSee('name="items[0][selected]" value="1" checked', false);
    }

    public function test_admin_post_creates_child_from_selected_subset_and_equivalent_replay_returns_same_child(): void
    {
        [$admin, $source, $items] = $this->fixture(2);
        $token = Str::uuid()->toString();
        $payload = $this->payloadFor($items[0], $token, 'Replacement Supplier', '44.50');

        $response = $this->actingAs($admin)->post(route('purchase-orders.follow-up.store', $source), $payload);
        $child = PurchaseOrder::query()->where('parent_purchase_order_id', $source->id)->sole();
        $response->assertRedirect(route('purchase-orders.show', $child));
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $child->status);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $source->fresh()->status);
        $this->assertSame('2.000', $child->items()->sole()->ordered_quantity);
        $this->assertSame('44.50', $child->items()->sole()->expected_unit_cost);
        $this->assertSame('3.000', $items[1]->outstandingQuantity());

        $this->post(route('purchase-orders.follow-up.store', $source), $payload)
            ->assertRedirect(route('purchase-orders.show', $child));
        $this->assertSame(2, PurchaseOrder::query()->count());
        $this->assertSame(3, PurchaseOrderItem::query()->count());
        $this->assertSame(1, PurchaseOrderItemTransfer::query()->count());

        $sourcePage = $this->get(route('purchase-orders.show', $source))->assertOk();
        $sourcePage->assertSee(route('purchase-orders.show', $child), false)
            ->assertSee(route('purchase-orders.follow-up.create', $source), false)
            ->assertDontSee(route('purchase-orders.edit', $source), false)
            ->assertSee('data-po-receive', false);
        $this->get(route('purchase-orders.show', $child))->assertOk()
            ->assertSee('Parent Purchase Order')
            ->assertSee(route('purchase-orders.show', $source), false)
            ->assertDontSee(route('purchase-orders.edit', $child), false)
            ->assertSee('data-po-receive', false);
    }

    public function test_closed_source_replay_succeeds_and_conflicting_reuse_is_controlled(): void
    {
        [$admin, $source, $items] = $this->fixture();
        $token = Str::uuid()->toString();
        $payload = $this->payloadFor($items[0], $token);
        $this->actingAs($admin)->post(route('purchase-orders.follow-up.store', $source), $payload)->assertRedirect();
        $child = PurchaseOrder::query()->where('parent_purchase_order_id', $source->id)->sole();
        $this->assertSame(PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER, $source->fresh()->status);

        $this->post(route('purchase-orders.follow-up.store', $source), $payload)
            ->assertRedirect(route('purchase-orders.show', $child));
        $conflict = $payload;
        $conflict['supplier_name'] = 'Conflicting Supplier';
        $this->followingRedirects()->post(route('purchase-orders.follow-up.store', $source), $conflict)
            ->assertOk()
            ->assertSee('already associated with a different Purchase Order')
            ->assertDontSee('data-po-follow-up', false);
        $this->assertSame(2, PurchaseOrder::query()->count());
        $this->assertSame(1, PurchaseOrderItemTransfer::query()->count());
    }

    public function test_foreign_item_submission_is_rejected_without_mutation(): void
    {
        [$admin, $source] = $this->fixture();
        [, $other, $otherItems] = $this->fixture();
        $before = [PurchaseOrder::query()->count(), PurchaseOrderItem::query()->count()];

        $this->actingAs($admin)->post(
            route('purchase-orders.follow-up.store', $source),
            $this->payloadFor($otherItems[0]),
        )->assertRedirect(route('purchase-orders.follow-up.create', $source))
            ->assertSessionHasErrors('items.0.source_purchase_order_item_id');

        $this->assertSame($before, [PurchaseOrder::query()->count(), PurchaseOrderItem::query()->count()]);
        $this->assertSame(0, PurchaseOrderItemTransfer::query()->count());
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $source->fresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $other->fresh()->status);
    }

    public function test_staff_sees_lineage_and_quantities_with_costs_and_actions_redacted(): void
    {
        [$admin, $source, $items] = $this->fixture(2);
        $this->actingAs($admin)->post(
            route('purchase-orders.follow-up.store', $source),
            $this->payloadFor($items[0], supplier: 'Lineage Supplier', cost: '77.77'),
        )->assertRedirect();
        $child = PurchaseOrder::query()->where('parent_purchase_order_id', $source->id)->sole();
        $this->recordAccepted($admin, $source, $items[1], '2.000', '88.88');
        $staff = User::factory()->create();

        $sourceHtml = $this->actingAs($staff)->get(route('purchase-orders.show', $source))->assertOk()
            ->assertSee(route('purchase-orders.show', $child), false)
            ->assertSee('Transferred quantity')
            ->assertDontSee('data-po-follow-up', false)
            ->assertDontSee('data-po-edit', false)
            ->getContent();
        $childHtml = $this->get(route('purchase-orders.show', $child))->assertOk()
            ->assertSee(route('purchase-orders.show', $source), false)
            ->assertDontSee('data-po-follow-up', false)
            ->assertDontSee('data-po-edit', false)
            ->getContent();
        foreach ([$sourceHtml, $childHtml] as $html) {
            $this->assertStringNotContainsString('Expected unit cost', $html);
            $this->assertStringNotContainsString('77.77', $html);
            $this->assertStringNotContainsString('88.88', $html);
            $this->assertStringNotContainsString('177.76', $html);
        }
        $this->actingAs($admin)->get(route('purchase-orders.show', $child))->assertOk()->assertSee('₱77.77');
    }

    /** @return array{User, PurchaseOrder, list<PurchaseOrderItem>} */
    private function fixture(int $lineCount = 1): array
    {
        $admin = User::factory()->admin()->create();
        $product = $this->product($this->category());
        $source = new PurchaseOrder;
        $source->submission_token = Str::uuid()->toString();
        $source->created_by = $admin->id;
        $source->supplier_name = 'Source Supplier';
        $source->status = PurchaseOrder::STATUS_PENDING;
        $source->save();
        $items = [];
        for ($index = 0; $index < $lineCount; $index++) {
            $variant = $this->variant($product, ['size' => 'Size '.$index]);
            $item = new PurchaseOrderItem;
            $item->purchase_order_id = $source->id;
            $item->product_variant_id = $variant->id;
            $item->product_name_snapshot = 'HTTP Product '.$index;
            $item->size_snapshot = $variant->size;
            $item->type_series_snapshot = $variant->type_series;
            $item->thickness_snapshot = $variant->thickness;
            $item->unit_snapshot = $variant->unit;
            $item->ordered_quantity = (string) ($index + 2).'.000';
            $item->expected_unit_cost = '12.34';
            $item->save();
            $items[] = $item;
        }

        return [$admin, $source, $items];
    }

    /** @return array<string, mixed> */
    private function payloadFor(
        PurchaseOrderItem $item,
        ?string $token = null,
        string $supplier = 'Child Supplier',
        string $cost = '15.00',
    ): array {
        return [
            'submission_token' => $token ?? Str::uuid()->toString(),
            'supplier_name' => $supplier,
            'notes' => 'Follow-up note',
            'items' => [[
                'selected' => '1',
                'source_purchase_order_item_id' => $item->id,
                'expected_unit_cost' => $cost,
            ]],
        ];
    }

    private function recordAccepted(
        User $actor,
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        string $quantity,
        string $unitCost,
    ): void {
        $total = bcmul($quantity, $unitCost, 2);
        $restockId = DB::table('restocks')->insertGetId([
            'submission_token' => Str::uuid()->toString(),
            'purchase_order_id' => $purchaseOrder->id,
            'recorded_by' => $actor->id,
            'reference_text' => 'HTTP receipt',
            'notes' => null,
            'total_cost' => $total,
            'created_at' => now(),
        ]);
        DB::table('restock_items')->insert([
            'restock_id' => $restockId,
            'product_variant_id' => $item->product_variant_id,
            'purchase_order_item_id' => $item->id,
            'product_name_snapshot' => $item->product_name_snapshot,
            'size_snapshot' => $item->size_snapshot,
            'type_series_snapshot' => $item->type_series_snapshot,
            'thickness_snapshot' => $item->thickness_snapshot,
            'unit_snapshot' => $item->unit_snapshot,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $total,
            'created_at' => now(),
        ]);
    }
}
