<?php

namespace Tests\Feature\Procurement;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Inventory\RestockTestCase;

final class PurchaseOrderReceivingHttpTest extends RestockTestCase
{
    public function test_admin_and_staff_can_browse_and_receive_while_guests_disabled_and_staff_mutations_are_denied(): void
    {
        [$admin, $staff, $purchaseOrder, $item] = $this->fixture();
        $this->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertRedirect('/login');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertRedirect('/login');

        $disabled = User::factory()->disabled()->create();
        $this->actingAs($disabled)->get(route('purchase-orders.index'))->assertRedirect('/login');
        $this->actingAs($disabled)->get(route('purchase-orders.receive.create', $purchaseOrder))->assertRedirect('/login');

        $unsupported = User::factory()->create();
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('users')->where('id', $unsupported->id)->update(['role' => 'owner']);
        DB::statement('PRAGMA ignore_check_constraints = OFF');
        $unsupported->refresh();
        $this->actingAs($unsupported)->get(route('purchase-orders.index'))->assertForbidden();
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertForbidden();
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertForbidden();
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '1', '70'))
            ->assertForbidden();

        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertOk()->assertDontSee('data-po-create-action');
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()->assertSee('data-po-receive')->assertDontSee('data-po-edit');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertSee('data-receive-token');
        $this->get(route('purchase-orders.create'))->assertForbidden();
        $this->post(route('purchase-orders.store'), [])->assertForbidden();
        $this->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
        $this->patch(route('purchase-orders.update', $purchaseOrder), [])->assertForbidden();

        $this->actingAs($admin)->get(route('purchase-orders.index'))->assertOk()->assertSee('data-po-create-action');
        $this->get(route('purchase-orders.create'))->assertOk();
        $this->get(route('purchase-orders.edit', $purchaseOrder))->assertOk();
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertSee((string) $item->id);
    }

    public function test_form_displays_outstanding_lines_and_preserves_token_and_input_after_validation_error(): void
    {
        [, $staff, $purchaseOrder, $item, , $otherItem] = $this->fixture(twoLines: true);
        $form = $this->actingAs($staff)->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk();
        $form->assertSee('Ordered')->assertSee('Accepted')->assertSee('Outstanding')
            ->assertSee('5.000 piece')->assertSee('Actual unit cost');
        $this->assertMatchesRegularExpression('/name="submission_token" value="[0-9a-f-]{36}"/', $form->getContent());

        $token = Str::uuid()->toString();
        $this->from(route('purchase-orders.receive.create', $purchaseOrder))
            ->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '1', '-1', $token))
            ->assertRedirect(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertSessionHasErrors('items.0.actual_unit_cost');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertOk()->assertSee('value="'.$token.'"', false)->assertSee('value="1"', false);

        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($otherItem, '2', '31.25'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $form = $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk();
        $form->assertSee('data-receive-line="'.$item->id.'"', false)
            ->assertDontSee('data-receive-line="'.$otherItem->id.'"', false);
    }

    public function test_partial_and_full_http_receipts_show_history_and_redact_staff_costs(): void
    {
        [$admin, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $purchaseOrder),
            $this->receiptPayload($item, '2', '73.45', reference: 'DR-26C'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame('2.000', $variant->fresh()->current_stock);
        $staffHtml = $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('3.000 piece')->assertSee('DR-26C')->assertSee('Receipt history')->getContent();
        $this->assertStringNotContainsString('91.27', $staffHtml);
        $this->assertStringNotContainsString('73.45', $staffHtml);
        $this->assertStringNotContainsString('146.90', $staffHtml);
        $this->assertStringNotContainsString('Expected unit cost', $staffHtml);
        $this->assertStringNotContainsString('Actual unit cost:', $staffHtml);
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertOk()->assertDontSee('91.27');
        $this->get(route('purchase-orders.index'))->assertOk()->assertDontSee('91.27')->assertDontSee('73.45');

        $this->actingAs($admin)->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('₱91.27')->assertSee('₱73.45')->assertSee('₱146.90');
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '3', '74.00'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_COMPLETED, $purchaseOrder->fresh()->status);
        $this->assertSame('5.000', $variant->fresh()->current_stock);
        $this->get(route('purchase-orders.show', $purchaseOrder))->assertOk()
            ->assertSee('0.000 piece')->assertDontSee('data-po-receive');
        $this->get(route('purchase-orders.receive.create', $purchaseOrder))->assertStatus(409);
    }

    public function test_full_post_replays_after_completion_and_conflicting_token_has_no_second_mutation(): void
    {
        [, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $payload = $this->receiptPayload($item, '5', '73.45');
        $this->actingAs($staff)->post(route('purchase-orders.receive.store', $purchaseOrder), $payload)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $payload)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHas('success', 'This receipt was already recorded.');
        $conflict = $this->receiptPayload($item, '4', '73.45', $payload['submission_token']);
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $conflict)
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('submission_token');
        $this->assertSame(1, Restock::query()->count());
        $this->assertSame(1, RestockItem::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RESTOCK)->count());
        $this->assertSame('5.000', $variant->fresh()->current_stock);
    }

    public function test_over_receipt_and_empty_delivery_report_errors_without_mutation(): void
    {
        [, $staff, $purchaseOrder, $item, $variant] = $this->fixture();
        $this->actingAs($staff)->from(route('purchase-orders.receive.create', $purchaseOrder))
            ->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '6', '70'))
            ->assertRedirect(route('purchase-orders.receive.create', $purchaseOrder))
            ->assertSessionHasErrors('items.0.accepted_quantity');
        $this->post(route('purchase-orders.receive.store', $purchaseOrder), $this->receiptPayload($item, '', ''))
            ->assertSessionHasErrors('items');
        $this->assertSame(0, Restock::query()->count());
        $this->assertSame('0.000', $variant->fresh()->current_stock);
    }

    /** @return array{User, User, PurchaseOrder, PurchaseOrderItem, ProductVariant, ?PurchaseOrderItem} */
    private function fixture(bool $twoLines = false): array
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->create();
        $product = $this->product($this->category());
        $variant = $this->variant($product, ['current_stock' => '0.000']);
        $this->initialize($variant, $admin);
        $purchaseOrder = new PurchaseOrder;
        $purchaseOrder->submission_token = Str::uuid()->toString();
        $purchaseOrder->created_by = $admin->id;
        $purchaseOrder->supplier_name = 'Receiving Supplier';
        $purchaseOrder->status = PurchaseOrder::STATUS_PENDING;
        $purchaseOrder->save();
        $item = $this->line($purchaseOrder, $variant, '5.000', '91.27');
        $other = null;
        if ($twoLines) {
            $secondVariant = $this->variant($product, ['size' => 'Second']);
            $this->initialize($secondVariant, $admin);
            $other = $this->line($purchaseOrder, $secondVariant, '2.000', '18.50');
        }

        return [$admin, $staff, $purchaseOrder, $item, $variant, $other];
    }

    private function line(PurchaseOrder $purchaseOrder, ProductVariant $variant, string $quantity, string $cost): PurchaseOrderItem
    {
        $item = new PurchaseOrderItem;
        $item->purchase_order_id = $purchaseOrder->id;
        $item->product_variant_id = $variant->id;
        $item->product_name_snapshot = 'Receiving Product';
        $item->size_snapshot = $variant->size;
        $item->type_series_snapshot = $variant->type_series;
        $item->thickness_snapshot = $variant->thickness;
        $item->unit_snapshot = $variant->unit;
        $item->ordered_quantity = $quantity;
        $item->expected_unit_cost = $cost;
        $item->save();

        return $item;
    }

    /** @return array<string, mixed> */
    private function receiptPayload(PurchaseOrderItem $item, string $quantity, string $cost, ?string $token = null, ?string $reference = null): array
    {
        return [
            'submission_token' => $token ?? Str::uuid()->toString(),
            'reference_text' => $reference,
            'notes' => null,
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'accepted_quantity' => $quantity,
                'actual_unit_cost' => $cost,
            ]],
        ];
    }
}
